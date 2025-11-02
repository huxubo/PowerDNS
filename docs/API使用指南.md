# PowerDNS API 使用指南

## 目录

1. [快速开始](#快速开始)
2. [认证](#认证)
3. [API 端点](#api-端点)
4. [根记录 CNAME 支持](#根记录-cname-支持)
5. [错误处理](#错误处理)
6. [示例](#示例)

## 快速开始

### 基本 URL 格式

```
http://your-domain.com/api/v1
```

### 认证方式

所有请求都需要在 HTTP 头中包含 API Key：

```
X-API-Key: your-api-key-here
```

或者使用 Bearer Token：

```
Authorization: Bearer your-api-key-here
```

## 认证

### 获取 API Key

默认的 API Key 配置在 `config/config.php` 文件中：

```php
'api' => [
    'key' => 'powerdns-api-key-change-me',
],
```

### 创建新的 API Key

可以通过以下 SQL 语句创建新的 API Key：

```sql
INSERT INTO api_keys (`key`, `description`, `permissions`, `active`)
VALUES ('your-new-api-key', '描述', '{"servers": ["*"], "zones": ["*"]}', 1);
```

## API 端点

### 1. 服务器管理

#### 列出所有服务器

```http
GET /api/v1/servers
```

响应：
```json
[
  {
    "id": "localhost",
    "type": "Server",
    "version": "PHP-PowerDNS-API-1.0.0",
    "daemon_type": "authoritative",
    "url": "/api/v1/servers/localhost"
  }
]
```

#### 获取服务器信息

```http
GET /api/v1/servers/localhost
```

#### 获取统计信息

```http
GET /api/v1/servers/localhost/statistics
```

响应：
```json
[
  {
    "name": "uptime",
    "type": "StatisticItem",
    "value": "1234567890"
  },
  {
    "name": "zones",
    "type": "StatisticItem",
    "value": "10"
  }
]
```

### 2. 区域管理

#### 列出所有区域

```http
GET /api/v1/servers/localhost/zones
```

可选参数：
- `limit`: 返回数量限制（默认：50）
- `offset`: 偏移量（默认：0）

#### 创建新区域

```http
POST /api/v1/servers/localhost/zones
Content-Type: application/json

{
  "name": "example.com.",
  "kind": "Native",
  "nameservers": ["ns1.example.com.", "ns2.example.com."],
  "ttl": 3600
}
```

#### 获取区域详情

```http
GET /api/v1/servers/localhost/zones/example.com.
```

响应包含完整的区域信息和所有记录（RRsets）。

#### 删除区域

```http
DELETE /api/v1/servers/localhost/zones/example.com.
```

### 3. 记录管理

#### 添加/修改记录

使用 PATCH 方法更新区域的记录集：

```http
PATCH /api/v1/servers/localhost/zones/example.com.
Content-Type: application/json

{
  "rrsets": [
    {
      "name": "www.example.com.",
      "type": "A",
      "changetype": "REPLACE",
      "ttl": 3600,
      "records": [
        {
          "content": "192.168.1.100",
          "disabled": false
        }
      ]
    }
  ]
}
```

#### 删除记录

```http
PATCH /api/v1/servers/localhost/zones/example.com.
Content-Type: application/json

{
  "rrsets": [
    {
      "name": "www.example.com.",
      "type": "A",
      "changetype": "DELETE"
    }
  ]
}
```

#### 支持的记录类型

- A - IPv4 地址
- AAAA - IPv6 地址
- CNAME - 别名
- MX - 邮件交换
- NS - 名称服务器
- TXT - 文本记录
- SRV - 服务记录
- PTR - 反向解析
- SOA - 授权起始

### 4. 搜索功能

```http
GET /api/v1/servers/localhost/search-data?q=example&max=100
```

参数：
- `q`: 搜索关键词（必需）
- `max`: 最大返回数量（默认：100）

响应：
```json
[
  {
    "object_type": "zone",
    "name": "example.com.",
    "zone_id": "example.com.",
    "type": "Native"
  },
  {
    "object_type": "record",
    "name": "www.example.com.",
    "type": "A",
    "content": "192.168.1.100",
    "zone": "example.com."
  }
]
```

### 5. 缓存管理

#### 清除缓存

```http
PUT /api/v1/servers/localhost/cache/flush
```

清除指定域名的缓存：

```http
PUT /api/v1/servers/localhost/cache/flush?domain=example.com.
```

## 根记录 CNAME 支持

### 什么是根记录 CNAME？

DNS 规范不允许在根记录（@）上使用 CNAME 记录。但本 API 允许您直接添加根记录 CNAME。

### 工作原理

1. 在根记录上设置 CNAME：
   ```json
   {
     "rrsets": [
       {
         "name": "example.com.",
         "type": "CNAME",
         "changetype": "REPLACE",
         "records": [
           {
             "content": "target.example.com.",
             "disabled": false
           }
         ]
       }
     ]
   }
   ```

2. API 会按原样存储 CNAME 记录到数据库

3. 查询时返回原始的 CNAME 记录：
   ```json
   {
     "name": "example.com.",
     "type": "CNAME",
     "ttl": 3600,
     "records": [
       {
         "content": "target.example.com.",
         "disabled": false
       }
     ]
   }
   ```

### 与官方 API 的区别

| 特性 | 官方 PowerDNS API | 本实现 |
|------|------------------|--------|
| 根记录 CNAME | ❌ 不支持 | ✅ 支持 |
| CNAME 展平 | ❌ 不支持 | 不在 API 层处理 |
| 记录存储 | - | 按原样存储 |

### 注意事项

- API 不进行 CNAME 展平处理
- 记录按原样存储和返回
- CNAME 解析由 PowerDNS 服务端或其他组件处理
- 支持多级 CNAME 链
- 支持 IPv4 (A) 和 IPv6 (AAAA) 目标

## 错误处理

### HTTP 状态码

| 状态码 | 说明 |
|--------|------|
| 200 | 成功 |
| 201 | 创建成功 |
| 204 | 无内容（删除成功） |
| 400 | 请求错误 |
| 401 | 未授权 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |
| 500 | 服务器错误 |

### 错误响应格式

```json
{
  "error": "错误消息",
  "errors": {
    "field": "详细错误信息"
  }
}
```

## 示例

### 完整的区域创建和记录管理流程

#### 1. 创建区域

```bash
curl -X POST http://api.powerdns.local/api/v1/servers/localhost/zones \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "mydomain.com.",
    "kind": "Native",
    "nameservers": ["ns1.mydomain.com.", "ns2.mydomain.com."]
  }'
```

#### 2. 添加 A 记录

```bash
curl -X PATCH http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -H "Content-Type: application/json" \
  -d '{
    "rrsets": [
      {
        "name": "www.mydomain.com.",
        "type": "A",
        "changetype": "REPLACE",
        "ttl": 3600,
        "records": [
          {"content": "192.168.1.100", "disabled": false}
        ]
      }
    ]
  }'
```

#### 3. 添加 MX 记录

```bash
curl -X PATCH http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -H "Content-Type: application/json" \
  -d '{
    "rrsets": [
      {
        "name": "mydomain.com.",
        "type": "MX",
        "changetype": "REPLACE",
        "ttl": 3600,
        "records": [
          {"content": "mail.mydomain.com.", "priority": 10, "disabled": false}
        ]
      }
    ]
  }'
```

#### 4. 添加根记录 CNAME

```bash
curl -X PATCH http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -H "Content-Type: application/json" \
  -d '{
    "rrsets": [
      {
        "name": "mydomain.com.",
        "type": "CNAME",
        "changetype": "REPLACE",
        "ttl": 3600,
        "records": [
          {"content": "www.mydomain.com.", "disabled": false}
        ]
      }
    ]
  }'
```

**注意**：本 API 支持在根记录添加 CNAME，记录会按原样存储和返回，不进行展平处理。

#### 5. 查询区域信息

```bash
curl -X GET http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me"
```

#### 6. 搜索记录

```bash
curl -X GET "http://api.powerdns.local/api/v1/servers/localhost/search-data?q=www&max=50" \
  -H "X-API-Key: powerdns-api-key-change-me"
```

#### 7. 删除记录

```bash
curl -X PATCH http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me" \
  -H "Content-Type: application/json" \
  -d '{
    "rrsets": [
      {
        "name": "www.mydomain.com.",
        "type": "A",
        "changetype": "DELETE"
      }
    ]
  }'
```

#### 8. 删除区域

```bash
curl -X DELETE http://api.powerdns.local/api/v1/servers/localhost/zones/mydomain.com. \
  -H "X-API-Key: powerdns-api-key-change-me"
```

## 高级用法

### 批量操作

可以在一个请求中修改多个记录集：

```json
{
  "rrsets": [
    {
      "name": "www.example.com.",
      "type": "A",
      "changetype": "REPLACE",
      "records": [{"content": "192.168.1.100"}]
    },
    {
      "name": "mail.example.com.",
      "type": "A",
      "changetype": "REPLACE",
      "records": [{"content": "192.168.1.200"}]
    },
    {
      "name": "ftp.example.com.",
      "type": "CNAME",
      "changetype": "REPLACE",
      "records": [{"content": "www.example.com."}]
    }
  ]
}
```

### 使用 Python 客户端

```python
import requests

class PowerDNSClient:
    def __init__(self, base_url, api_key):
        self.base_url = base_url
        self.headers = {
            'X-API-Key': api_key,
            'Content-Type': 'application/json'
        }
    
    def create_zone(self, name, nameservers):
        url = f"{self.base_url}/api/v1/servers/localhost/zones"
        data = {
            "name": name,
            "kind": "Native",
            "nameservers": nameservers
        }
        response = requests.post(url, json=data, headers=self.headers)
        return response.json()
    
    def add_record(self, zone, name, record_type, content, ttl=3600):
        url = f"{self.base_url}/api/v1/servers/localhost/zones/{zone}"
        data = {
            "rrsets": [{
                "name": name,
                "type": record_type,
                "changetype": "REPLACE",
                "ttl": ttl,
                "records": [{"content": content}]
            }]
        }
        response = requests.patch(url, json=data, headers=self.headers)
        return response.status_code == 204

# 使用示例
client = PowerDNSClient('http://api.powerdns.local', 'your-api-key')
client.create_zone('example.com.', ['ns1.example.com.', 'ns2.example.com.'])
client.add_record('example.com.', 'www.example.com.', 'A', '192.168.1.100')
```

## 性能优化建议

1. **使用缓存**：启用 CNAME 展平缓存以提高性能
2. **批量操作**：尽量在一个请求中修改多个记录
3. **数据库索引**：确保数据库表有适当的索引
4. **连接池**：使用持久化数据库连接
5. **限制查询**：使用 limit 和 offset 参数进行分页

## 故障排查

### 常见问题

1. **401 未授权错误**
   - 检查 API Key 是否正确
   - 确认 X-API-Key 头已设置

2. **404 区域不存在**
   - 确认区域名称以点（.）结尾
   - 检查区域是否已创建

3. **CNAME 展平不工作**
   - 确认配置中已启用
   - 检查日志文件中的错误信息
   - 清除缓存后重试

4. **数据库连接失败**
   - 检查数据库配置
   - 确认数据库服务正在运行
   - 验证用户权限

### 日志查看

查看 API 日志：

```bash
tail -f /path/to/logs/api.log
```

查看 PHP 错误日志：

```bash
tail -f /var/log/php-fpm/error.log
```

## 安全最佳实践

1. **更改默认 API Key**
2. **使用 HTTPS**
3. **设置 IP 白名单**
4. **定期更新 API Key**
5. **监控异常请求**
6. **限制请求频率**

## 参考资料

- [PowerDNS 官方 API 文档](https://doc.powerdns.com/authoritative/http-api/)
- [RFC 1035 - DNS 规范](https://www.rfc-editor.org/rfc/rfc1035)
- [CNAME 展平技术说明](https://blog.cloudflare.com/introducing-cname-flattening-rfc-compliant-cnames-at-a-domains-root/)
