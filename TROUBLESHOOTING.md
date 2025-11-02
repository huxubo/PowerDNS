# PowerDNS API 404错误排查指南

## 问题描述
访问 `GET http://studio.xx.kg/api/v1/servers/localhost/zones/108.xx.kg` 返回404错误。

## 已修复的问题

### 1. 配置文件缺失
**问题**: `config/config.php` 文件不存在
**解决**: 已创建 `config/config.php` 文件，使用默认配置

### 2. 路由逻辑验证
**验证结果**: 路由匹配逻辑正确
- 请求路径: `/api/v1/servers/localhost/zones/108.xx.kg`
- 匹配模式: `#^/api/v1/servers/([^/]+)/zones/([^/]+)$#`
- 应该匹配成功，提取出:
  - `$serverId = 'localhost'`
  - `$zoneId = urldecode('108.xx.kg') = '108.xx.kg'`

## 可能的剩余问题

### 1. 数据库中没有域名记录
**现象**: `ZoneController->getZone()` 方法中 `Domain->getByName('108.xx.kg')` 返回null
**检查方法**:
```sql
SELECT * FROM domains WHERE name IN ('108.xx.kg', '108.xx.kg.');
```

**解决方案**: 运行 `fix-domain.sql` 脚本创建域名记录

### 2. API认证失败
**现象**: 认证步骤失败，返回401而不是404
**检查方法**:
- 确认请求包含正确的API Key
- API Key可以通过以下方式提供:
  - Header: `X-API-Key: powerdns-api-key-change-me`
  - Header: `Authorization: Bearer powerdns-api-key-change-me`
  - Query参数: `?api_key=powerdns-api-key-change-me`

### 3. 数据库连接失败
**现象**: 程序在数据库初始化时抛出异常
**检查方法**: 确认数据库配置正确且数据库服务运行正常

## 调试步骤

### 步骤1: 验证配置文件
确认 `config/config.php` 存在且配置正确

### 步骤2: 检查数据库连接
运行 `debug.php` 脚本（需要PHP环境）

### 步骤3: 验证域名记录
```sql
USE powerdns;
SELECT * FROM domains WHERE name IN ('108.xx.kg', '108.xx.kg.');
```

### 步骤4: 创建域名记录（如果需要）
```sql
INSERT IGNORE INTO `domains` (`name`, `type`, `account`) VALUES 
('108.xx.kg', 'NATIVE', 'default');

-- 获取域名ID并创建基本DNS记录
SET @domain_id = (SELECT id FROM domains WHERE name = '108.xx.kg.' LIMIT 1);

INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
(@domain_id, '108.xx.kg', 'SOA', 'ns1.108.xx.kg. hostmaster.108.xx.kg. 2024010101 3600 1800 604800 86400', 3600, NULL, 0, 1),
(@domain_id, '108.xx.kg', 'NS', 'ns1.108.xx.kg.', 3600, NULL, 0, 1),
(@domain_id, '108.xx.kg', 'A', '192.168.1.100', 3600, NULL, 0, 1);
```

### 步骤5: 测试API请求
```bash
curl -H "X-API-Key: powerdns-api-key-change-me" \
     "http://studio.xx.kg/api/v1/servers/localhost/zones/108.xx.kg"
```

## 代码流程分析

1. **路由匹配** (index.php:139-153)
   - 正则表达式正确匹配请求路径
   - 提取服务器ID和域名ID

2. **控制器调用** (index.php:148)
   - 调用 `ZoneController->getZone('localhost', '108.xx.kg')`

3. **域名查询** (ZoneController.php:144)
   - 调用 `Domain->getByName('108.xx.kg')`
   - 尝试多种格式: '108.xx.kg', '108.xx.kg.', '108.xx.kg'（去点）

4. **响应处理** (ZoneController.php:146-152)
   - 如果找到域名，格式化并返回
   - 如果未找到，返回404错误

## 常见问题

### Q: 为什么返回404而不是其他错误？
A: 这通常意味着路由匹配成功，但是在数据库中找不到对应的域名记录。

### Q: 域名存储格式是什么？
A: 域名在数据库中以带点的格式存储（如 '108.xx.kg.'），但API可以接受带点或不带点的格式。

### Q: 如何确认域名存在？
A: 直接查询数据库或调用API列出所有区域:
```bash
curl -H "X-API-Key: powerdns-api-key-change-me" \
     "http://studio.xx.kg/api/v1/servers/localhost/zones"
```

## 文件清单

- `config/config.php` - API配置文件（已创建）
- `fix-domain.sql` - SQL脚本，用于创建域名记录
- `debug.php` - 调试脚本（需要PHP环境）
- `test-routing.php` - 路由测试脚本（需要PHP环境）
- `TROUBLESHOOTING.md` - 本文档