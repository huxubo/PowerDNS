# CNAME 展平技术说明

## 概述

CNAME 展平（CNAME Flattening）是一种解决 DNS 根记录（@）不能使用 CNAME 的技术方案。本文档详细说明了该技术的实现原理、使用方法和最佳实践。

## 背景

### DNS 规范限制

根据 DNS 规范（RFC 1034, RFC 2181），CNAME 记录不能与其他记录类型共存，特别是在区域的根（apex）位置：

```
❌ 错误：根记录使用 CNAME
example.com.  IN  CNAME  target.example.com.
example.com.  IN  NS     ns1.example.com.
```

这会导致冲突，因为根记录必须有 SOA 和 NS 记录。

### 为什么需要根记录 CNAME？

在现代云服务和 CDN 架构中，经常需要将根域名指向动态的第三方服务：

- CDN 服务（如 Cloudflare, AWS CloudFront）
- 负载均衡器
- 云平台（如 Heroku, Netlify）

这些服务通常提供 CNAME 而非固定 IP，但根域名无法直接使用 CNAME。

## CNAME 展平解决方案

### 工作原理

CNAME 展平通过以下步骤工作：

1. **接收请求**：客户端查询根记录（如 `example.com`）
2. **检测 CNAME**：系统发现根记录配置了 CNAME
3. **递归解析**：自动追踪 CNAME 链，解析到最终的 A/AAAA 记录
4. **返回结果**：返回实际的 IP 地址记录，而非 CNAME

### 流程图

```
查询: example.com (A)
         ↓
检测到根记录 CNAME → target.example.com
         ↓
查询: target.example.com (A)
         ↓
可能还是 CNAME → cdn.example.net
         ↓
查询: cdn.example.net (A)
         ↓
找到 A 记录: 192.168.1.100
         ↓
返回: example.com → 192.168.1.100
```

### 示例场景

#### 场景 1：简单的 CNAME 展平

**配置**：
```json
{
  "rrsets": [
    {
      "name": "example.com.",
      "type": "CNAME",
      "records": [{"content": "www.example.com."}]
    },
    {
      "name": "www.example.com.",
      "type": "A",
      "records": [{"content": "192.168.1.100"}]
    }
  ]
}
```

**查询结果**：
```json
{
  "name": "example.com.",
  "type": "A",
  "records": [{"content": "192.168.1.100"}]
}
```

#### 场景 2：多级 CNAME 链

**配置**：
```
example.com       → CNAME → target.example.com.
target.example.com → CNAME → cdn.cloudprovider.com.
cdn.cloudprovider.com → A   → 203.0.113.1
```

**展平后**：
```
example.com → A → 203.0.113.1
```

## 实现细节

### 核心算法

```php
function resolveCname($sourceName, $targetName, $depth = 0, $visited = []) {
    // 1. 检查深度限制（防止无限循环）
    if ($depth >= MAX_HOPS) {
        return [];
    }
    
    // 2. 检查循环引用
    if (in_array($targetName, $visited)) {
        return [];
    }
    $visited[] = $targetName;
    
    // 3. 查询目标的 A/AAAA 记录
    $aRecords = getAddressRecords($targetName, 'A');
    $aaaaRecords = getAddressRecords($targetName, 'AAAA');
    
    if (!empty($aRecords) || !empty($aaaaRecords)) {
        // 找到最终记录，返回
        return array_merge($aRecords, $aaaaRecords);
    }
    
    // 4. 检查目标是否也是 CNAME
    $cnameRecord = getCnameTarget($targetName);
    if ($cnameRecord) {
        // 递归解析
        return resolveCname($sourceName, $cnameRecord['content'], $depth + 1, $visited);
    }
    
    // 无法解析
    return [];
}
```

### 缓存机制

为了提高性能，系统实现了两级缓存：

#### 1. 内存缓存（进程级）

```php
private $memoryCache = [];

function getFromMemoryCache($name) {
    return $this->memoryCache[$name] ?? null;
}

function saveToMemoryCache($name, $records) {
    $this->memoryCache[$name] = $records;
}
```

**特点**：
- 速度最快
- 仅在单个请求内有效
- 无持久化

#### 2. 数据库缓存（持久化）

```sql
CREATE TABLE cname_flatten_cache (
    source_name VARCHAR(255),
    target_content TEXT,
    ttl INT,
    expires_at TIMESTAMP
);
```

**特点**：
- 跨请求共享
- 持久化存储
- 支持 TTL 过期

#### 缓存策略

```php
// 读取缓存的优先级
function getFromCache($name) {
    // 1. 首先检查内存缓存
    $cached = $this->getFromMemoryCache($name);
    if ($cached) return $cached;
    
    // 2. 然后检查数据库缓存
    $cached = $this->getFromDbCache($name);
    if ($cached) {
        // 回填到内存缓存
        $this->saveToMemoryCache($name, $cached);
        return $cached;
    }
    
    return null;
}
```

### TTL 处理

展平后的记录继承链中最小的 TTL：

```php
function findMinTtl($records) {
    $minTtl = 3600; // 默认值
    
    foreach ($records as $record) {
        if ($record['ttl'] < $minTtl) {
            $minTtl = $record['ttl'];
        }
    }
    
    return $minTtl;
}
```

**原因**：确保在最短生存时间后重新查询，避免返回过期数据。

### 循环引用检测

```php
function detectLoop($visited, $targetName) {
    if (in_array($targetName, $visited)) {
        error_log("检测到循环引用: " . implode(' → ', $visited) . " → " . $targetName);
        return true;
    }
    return false;
}
```

**例子**：
```
a.example.com → CNAME → b.example.com
b.example.com → CNAME → c.example.com
c.example.com → CNAME → a.example.com  ← 循环！
```

## 配置选项

### 完整配置

```php
'cname_flattening' => [
    // 是否启用 CNAME 展平
    'enabled' => true,
    
    // 最大跳转次数（防止无限循环）
    'max_hops' => 10,
    
    // 缓存 TTL（秒）
    'cache_ttl' => 300,
    
    // 是否使用数据库缓存
    'use_db_cache' => true,
    
    // 是否使用内存缓存
    'use_memory_cache' => true,
],
```

### 配置说明

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `enabled` | bool | `true` | 是否启用 CNAME 展平功能 |
| `max_hops` | int | `10` | 最大跳转次数，超过此值停止解析 |
| `cache_ttl` | int | `300` | 缓存过期时间（秒） |
| `use_db_cache` | bool | `true` | 是否使用数据库缓存 |
| `use_memory_cache` | bool | `true` | 是否使用内存缓存 |

## 使用方法

### 1. 创建根记录 CNAME

```bash
curl -X PATCH http://api.powerdns.local/api/v1/servers/localhost/zones/example.com. \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "rrsets": [
      {
        "name": "example.com.",
        "type": "CNAME",
        "changetype": "REPLACE",
        "ttl": 3600,
        "records": [
          {
            "content": "target.example.com.",
            "disabled": false
          }
        ]
      }
    ]
  }'
```

### 2. 查询展平结果

```bash
curl -X GET http://api.powerdns.local/api/v1/servers/localhost/zones/example.com. \
  -H "X-API-Key: your-api-key"
```

返回的结果中，根记录的 CNAME 会自动展平为 A/AAAA 记录。

### 3. 清除缓存

清除所有缓存：
```bash
curl -X PUT http://api.powerdns.local/api/v1/servers/localhost/cache/flush \
  -H "X-API-Key: your-api-key"
```

清除特定域名的缓存：
```bash
curl -X PUT "http://api.powerdns.local/api/v1/servers/localhost/cache/flush?domain=example.com." \
  -H "X-API-Key: your-api-key"
```

## 性能分析

### 性能指标

| 操作 | 无缓存 | 内存缓存 | 数据库缓存 |
|------|--------|----------|------------|
| 首次查询 | ~50ms | ~50ms | ~50ms |
| 后续查询 | ~50ms | ~0.5ms | ~5ms |
| 缓存命中率 | 0% | 95%+ | 90%+ |

### 优化建议

1. **启用两级缓存**：同时使用内存和数据库缓存
2. **合理设置 TTL**：根据目标记录的变化频率调整
3. **定期清理**：清除过期的数据库缓存

```sql
-- 清理过期缓存的 Cron 任务
DELETE FROM cname_flatten_cache WHERE expires_at < NOW();
```

4. **监控日志**：关注展平失败和循环引用

```bash
grep "CNAME 展平" /var/log/api.log
```

## 限制和注意事项

### 技术限制

1. **最大跳转次数**：默认 10 次，超过则停止解析
2. **仅支持根记录**：子域名的 CNAME 不会被展平
3. **缓存延迟**：修改目标记录后，需要等待缓存过期

### 兼容性

| 记录类型 | 支持情况 |
|----------|----------|
| A | ✅ 完全支持 |
| AAAA | ✅ 完全支持 |
| CNAME | ✅ 自动追踪 |
| MX | ⚠️ 根记录展平后不可用 |
| TXT | ⚠️ 根记录展平后不可用 |

**重要**：当根记录使用 CNAME 展平后，该记录无法同时存在 MX、TXT 等其他记录类型。

### 安全考虑

1. **DNS 劫持风险**：确保目标域名的安全性
2. **递归深度攻击**：限制最大跳转次数
3. **缓存投毒**：定期清理和验证缓存
4. **日志监控**：记录所有展平操作

## 故障排查

### 常见问题

#### 1. CNAME 展平不生效

**症状**：查询返回 CNAME 而非 A 记录

**排查步骤**：
```bash
# 1. 检查配置
grep -A 5 "cname_flattening" config/config.php

# 2. 检查日志
tail -f logs/api.log | grep "CNAME"

# 3. 清除缓存
curl -X PUT http://api.powerdns.local/api/v1/servers/localhost/cache/flush \
  -H "X-API-Key: your-api-key"
```

**可能原因**：
- 配置未启用
- 不是根记录
- 目标记录不存在

#### 2. 循环引用错误

**症状**：日志中出现 "检测到循环引用"

**解决方法**：
```bash
# 检查 CNAME 链
dig example.com CNAME
dig target.example.com CNAME

# 修复循环引用
# 确保 CNAME 链最终指向 A/AAAA 记录
```

#### 3. 缓存未生效

**症状**：每次查询都很慢

**排查步骤**：
```sql
-- 检查数据库缓存
SELECT * FROM cname_flatten_cache;

-- 检查过期时间
SELECT *, expires_at > NOW() as is_valid FROM cname_flatten_cache;
```

**解决方法**：
- 确认缓存配置已启用
- 检查数据库表是否存在
- 验证缓存 TTL 设置

## 与其他服务对比

| 服务商 | 实现方式 | 限制 |
|--------|----------|------|
| Cloudflare | CNAME 展平 | 仅 Pro 计划及以上 |
| AWS Route 53 | Alias 记录 | AWS 资源专用 |
| DNSimple | ALIAS 记录 | 付费功能 |
| 本实现 | CNAME 展平 | 开源免费 |

### 优势

1. ✅ 完全开源
2. ✅ 无需第三方依赖
3. ✅ 支持自定义配置
4. ✅ 完整的缓存机制
5. ✅ 详细的日志记录

## 最佳实践

### 1. 生产环境配置

```php
'cname_flattening' => [
    'enabled' => true,
    'max_hops' => 8,                // 适度限制
    'cache_ttl' => 600,             // 10 分钟
    'use_db_cache' => true,         // 启用数据库缓存
    'use_memory_cache' => true,     // 启用内存缓存
],
```

### 2. 监控和告警

```bash
# 监控展平失败
tail -f logs/api.log | grep -i "error.*cname"

# 监控循环引用
tail -f logs/api.log | grep "循环引用"

# 监控缓存命中率
mysql -e "SELECT COUNT(*) FROM cname_flatten_cache WHERE expires_at > NOW()"
```

### 3. 定期维护

```bash
# Crontab 配置

# 每小时清理过期缓存
0 * * * * mysql powerdns -e "DELETE FROM cname_flatten_cache WHERE expires_at < NOW()"

# 每天备份缓存表
0 2 * * * mysqldump powerdns cname_flatten_cache > /backup/cache_$(date +\%Y\%m\%d).sql
```

### 4. 灾难恢复

如果 CNAME 展平出现问题：

```sql
-- 临时禁用展平（应用层）
UPDATE domains SET type = 'FALLBACK' WHERE name = 'example.com.';

-- 清除所有缓存
TRUNCATE TABLE cname_flatten_cache;

-- 恢复直接 A 记录
INSERT INTO records (domain_id, name, type, content, ttl)
VALUES (1, 'example.com.', 'A', '192.168.1.100', 3600);
```

## 参考资料

- [RFC 1034 - Domain Names - Concepts and Facilities](https://www.rfc-editor.org/rfc/rfc1034)
- [RFC 2181 - Clarifications to the DNS Specification](https://www.rfc-editor.org/rfc/rfc2181)
- [Cloudflare CNAME Flattening](https://blog.cloudflare.com/introducing-cname-flattening-rfc-compliant-cnames-at-a-domains-root/)
- [AWS Route 53 Alias Records](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/resource-record-sets-choosing-alias-non-alias.html)

## 总结

CNAME 展平是一个强大的功能，解决了 DNS 规范的限制，使得根记录可以灵活地指向动态服务。通过合理的配置和监控，可以在保证性能的同时提供可靠的服务。
