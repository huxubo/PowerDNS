# 404错误修复总结

## 问题分析

访问 `GET http://studio.xx.kg/api/v1/servers/localhost/zones/108.xx.kg` 返回404错误。

经过代码分析，发现以下问题：

### 已修复问题

1. **配置文件缺失** ✅
   - 问题：`config/config.php` 文件不存在
   - 解决：已创建配置文件，使用默认设置

### 根本原因

**数据库中没有域名 `108.xx.kg` 的记录**

代码流程分析：
1. 路由匹配成功 ✅
   - 正则表达式 `#^/api/v1/servers/([^/]+)/zones/([^/]+)$#` 正确匹配
   - 提取出 `$serverId = 'localhost'`, `$zoneId = '108.xx.kg'`

2. 控制器调用正常 ✅
   - 调用 `ZoneController->getZone('localhost', '108.xx.kg')`

3. 域名查询失败 ❌
   - `Domain->getByName('108.xx.kg')` 返回 null
   - 尝试 '108.xx.kg', '108.xx.kg.', '108.xx.kg'（去点）都未找到
   - 返回404错误："区域不存在"

## 解决方案

### 方案1：创建域名记录（推荐）

运行提供的SQL脚本 `fix-domain.sql`：

```sql
USE powerdns;

-- 创建域名记录
INSERT IGNORE INTO `domains` (`name`, `type`, `account`) VALUES 
('108.xx.kg', 'NATIVE', 'default');

-- 获取域名ID并创建基本DNS记录
SET @domain_id = (SELECT id FROM domains WHERE name = '108.xx.kg.' LIMIT 1);

-- 插入基本DNS记录
INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
(@domain_id, '108.xx.kg', 'SOA', 'ns1.108.xx.kg. hostmaster.108.xx.kg. 2024010101 3600 1800 604800 86400', 3600, NULL, 0, 1),
(@domain_id, '108.xx.kg', 'NS', 'ns1.108.xx.kg.', 3600, NULL, 0, 1),
(@domain_id, '108.xx.kg', 'A', '192.168.1.100', 3600, NULL, 0, 1);
```

### 方案2：通过API创建域名

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -d '{
    "name": "108.xx.kg",
    "kind": "Native",
    "nameservers": ["ns1.108.xx.kg.", "ns2.108.xx.kg."],
    "ttl": 3600
  }' \
  "http://studio.xx.kg/api/v1/servers/localhost/zones"
```

## 验证步骤

1. **确认域名存在**
   ```sql
   SELECT * FROM domains WHERE name IN ('108.xx.kg', '108.xx.kg.');
   ```

2. **测试API请求**
   ```bash
   curl -H "X-API-Key: powerdns-api-key-change-me" \
        "http://studio.xx.kg/api/v1/servers/localhost/zones/108.xx.kg"
   ```

3. **列出所有区域**
   ```bash
   curl -H "X-API-Key: powerdns-api-key-change-me" \
        "http://studio.xx.kg/api/v1/servers/localhost/zones"
   ```

## 文件说明

- `config/config.php` - API配置文件（已创建）
- `fix-domain.sql` - SQL脚本，用于创建域名记录
- `TROUBLESHOOTING.md` - 详细的故障排查指南
- `debug.php` - 调试脚本（需要PHP环境）
- `test-routing.php` - 路由测试脚本（需要PHP环境）

## 注意事项

1. **API认证**：确保请求包含正确的API Key
2. **域名格式**：数据库存储带点的域名（如 `108.xx.kg.`），但API接受两种格式
3. **数据库连接**：确认数据库服务运行且配置正确
4. **权限设置**：确保Web服务器有权限访问所有文件

## 预期结果

修复后，API请求应该返回类似以下响应：

```json
{
    "id": "108.xx.kg",
    "name": "108.xx.kg",
    "type": "Zone",
    "kind": "NATIVE",
    "serial": 2024010101,
    "notified_serial": null,
    "masters": [],
    "dnssec": false,
    "nsec3param": "",
    "nsec3narrow": false,
    "presigned": false,
    "soa_edit": "",
    "soa_edit_api": "",
    "api_rectify": false,
    "account": "default",
    "url": "/api/v1/servers/localhost/zones/108.xx.kg",
    "rrsets": [
        {
            "name": "108.xx.kg",
            "type": "SOA",
            "ttl": 3600,
            "records": [
                {
                    "content": "ns1.108.xx.kg. hostmaster.108.xx.kg. 2024010101 3600 1800 604800 86400",
                    "disabled": false
                }
            ]
        }
    ]
}
```