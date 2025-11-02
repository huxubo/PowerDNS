# PowerDNS API - PHP + MySQL 实现

## 项目简介

这是一个使用原生 PHP + MySQL 实现的 PowerDNS HTTP API 完整功能版本。该实现遵循 PowerDNS 官方 API 规范，并支持在根记录 (@) 添加 CNAME 记录。

## 🚀 快速部署

### 宝塔面板部署（推荐）

如果您使用宝塔面板，请查看 **[宝塔面板部署指南](BAOTA_DEPLOY.md)** 获取详细的部署步骤。

**关键配置：**
- ⚠️ **运行目录必须设置为 `/public`**
- 需要配置 Nginx 伪静态规则
- 需要创建 `config/config.php` 配置文件

### 其他部署方式

- [详细安装指南](INSTALL.md) - 包含 Ubuntu/Debian/CentOS 等系统的安装步骤
- [Docker 部署](INSTALL.md#docker-安装可选) - 使用 Docker 快速部署

## 主要特性

- ✅ 完整实现 PowerDNS HTTP API 规范
- ✅ 原生 PHP 实现，无需框架依赖
- ✅ MySQL 数据库支持
- ✅ 支持根记录 (@) CNAME 记录
- ✅ API Key 认证
- ✅ RESTful 架构
- ✅ JSON 格式响应
- ✅ 完整的中文注释和文档

## 根记录 CNAME 支持

本实现与官方 PowerDNS API 不同，支持在根记录 (@) 添加 CNAME 记录：

- API 允许直接添加和修改根记录的 CNAME
- 不在 API 层进行 CNAME 展平处理
- 记录按原样存储和返回
- CNAME 展平由 PowerDNS 服务端或其他组件处理

## 系统要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx Web 服务器
- PHP 扩展：PDO, PDO_MySQL, JSON

## 安装步骤

### 1. 克隆项目

```bash
git clone <repository-url>
cd powerdns-api
```

### 2. 配置数据库

导入数据库架构：

```bash
mysql -u root -p < database/schema.sql
```

### 3. 配置 API

复制配置文件并修改数据库连接信息：

```bash
cp config/config.example.php config/config.php
```

编辑 `config/config.php`：

```php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'powerdns',
        'username' => 'your_username',
        'password' => 'your_password',
    ],
    'api' => [
        'key' => 'your-secure-api-key-here',
    ],
];
```

### 4. 配置 Web 服务器

#### Apache

创建虚拟主机配置：

```apache
<VirtualHost *:80>
    ServerName api.powerdns.local
    DocumentRoot /path/to/powerdns-api/public
    
    <Directory /path/to/powerdns-api/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/powerdns-api-error.log
    CustomLog ${APACHE_LOG_DIR}/powerdns-api-access.log combined
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name api.powerdns.local;
    root /path/to/powerdns-api/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## API 使用说明

### 认证

所有 API 请求都需要在 HTTP Header 中包含 API Key：

```bash
X-API-Key: your-api-key-here
```

### API 端点

#### 1. 服务器信息

**获取服务器信息**

```bash
GET /api/v1/servers
```

响应示例：
```json
[
  {
    "id": "localhost",
    "type": "Server",
    "version": "PHP-PowerDNS-API-1.0",
    "daemon_type": "authoritative",
    "url": "/api/v1/servers/localhost"
  }
]
```

#### 2. 区域管理

**列出所有区域**

```bash
GET /api/v1/servers/localhost/zones
```

**创建新区域**

```bash
POST /api/v1/servers/localhost/zones
Content-Type: application/json

{
  "name": "example.com.",
  "kind": "Native",
  "nameservers": ["ns1.example.com.", "ns2.example.com."]
}
```

**获取区域详情**

```bash
GET /api/v1/servers/localhost/zones/example.com.
```

**删除区域**

```bash
DELETE /api/v1/servers/localhost/zones/example.com.
```

#### 3. 记录管理

**更新记录集 (RRsets)**

```bash
PATCH /api/v1/servers/localhost/zones/example.com.
Content-Type: application/json

{
  "rrsets": [
    {
      "name": "www.example.com.",
      "type": "A",
      "changetype": "REPLACE",
      "records": [
        {
          "content": "192.168.1.1",
          "disabled": false
        }
      ]
    }
  ]
}
```

**根记录 CNAME 示例**

```bash
PATCH /api/v1/servers/localhost/zones/example.com.
Content-Type: application/json

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

查询时返回原始记录：
```bash
GET /api/v1/servers/localhost/zones/example.com.
```

API 会返回存储的 CNAME 记录，不进行展平处理。

#### 4. 搜索功能

**搜索记录**

```bash
GET /api/v1/servers/localhost/search-data?q=example&max=100
```

#### 5. 缓存管理

**清除缓存**

```bash
PUT /api/v1/servers/localhost/cache/flush?domain=example.com.
```

#### 6. 统计信息

**获取统计数据**

```bash
GET /api/v1/servers/localhost/statistics
```

## 数据库架构

项目使用标准的 PowerDNS 数据库架构，包含以下表：

- `domains` - 区域信息
- `records` - DNS 记录
- `domainmetadata` - 区域元数据
- `cryptokeys` - DNSSEC 密钥
- `tsigkeys` - TSIG 密钥
- `comments` - 记录注释

## 目录结构

```
powerdns-api/
├── config/              # 配置文件
│   ├── config.php       # 主配置文件
│   └── config.example.php
├── database/            # 数据库相关
│   └── schema.sql       # 数据库架构
├── public/              # Web 根目录
│   ├── .htaccess        # Apache 重写规则
│   └── index.php        # 入口文件
├── src/                 # 源代码
│   ├── api/             # API 控制器
│   │   ├── ServerController.php
│   │   ├── ZoneController.php
│   │   ├── RecordController.php
│   │   ├── SearchController.php
│   │   └── CacheController.php
│   ├── models/          # 数据模型
│   │   ├── Database.php
│   │   ├── Domain.php
│   │   └── Record.php
│   ├── services/        # 业务逻辑
│   │   └── CnameFlatteningService.php
│   └── utils/           # 工具类
│       ├── Response.php
│       └── Auth.php
├── .gitignore
└── README.md
```

## 开发说明

### 根记录 CNAME 支持

API 允许在根记录 (@) 添加 CNAME 记录：

1. 不进行任何验证限制，允许添加根记录 CNAME
2. 记录按原样存储到数据库
3. 查询时返回原始 CNAME 记录，不进行展平
4. CNAME 解析由 PowerDNS 服务端或其他组件处理

### 错误处理

API 遵循标准 HTTP 状态码：

- `200 OK` - 请求成功
- `201 Created` - 资源创建成功
- `204 No Content` - 删除成功
- `400 Bad Request` - 请求参数错误
- `401 Unauthorized` - 未授权
- `404 Not Found` - 资源不存在
- `422 Unprocessable Entity` - 数据验证失败
- `500 Internal Server Error` - 服务器错误

### 日志记录

所有操作都会记录到系统日志，包括：

- API 请求日志
- CNAME 展平操作日志
- 错误和异常日志

## 安全建议

1. **使用强 API Key**：生成足够长度的随机字符串作为 API Key
2. **HTTPS 加密**：在生产环境中始终使用 HTTPS
3. **IP 白名单**：限制 API 访问的 IP 地址范围
4. **速率限制**：实施 API 请求速率限制
5. **定期备份**：定期备份数据库
6. **更新 PHP**：保持 PHP 版本更新到最新稳定版

## 性能优化

1. **数据库索引**：已在关键字段上创建索引
2. **连接池**：使用持久化数据库连接
3. **查询缓存**：CNAME 展平结果缓存
4. **分页查询**：大量数据使用分页
5. **慢查询监控**：启用 MySQL 慢查询日志

## 测试

### 单元测试

```bash
# 待实现
php tests/run.php
```

### API 测试示例

```bash
# 测试服务器信息
curl -H "X-API-Key: your-api-key" http://api.powerdns.local/api/v1/servers

# 测试创建区域
curl -X POST -H "X-API-Key: your-api-key" \
     -H "Content-Type: application/json" \
     -d '{"name":"test.com.","kind":"Native"}' \
     http://api.powerdns.local/api/v1/servers/localhost/zones
```

## 常见问题

### Q: 为什么支持根记录 CNAME？

A: 官方 PowerDNS API 不支持在根记录添加 CNAME，但本实现允许这样做。CNAME 展平处理由 PowerDNS 服务端或其他组件负责，API 只负责存储和返回原始记录。

### Q: 支持 DNSSEC 吗？

A: 数据库架构支持 DNSSEC，但当前版本的 API 实现为基础版本。可以扩展添加完整的 DNSSEC 支持。

### Q: 可以与 PowerDNS 服务端集成吗？

A: 可以。本实现使用标准的 PowerDNS 数据库架构，可以与 PowerDNS Authoritative Server 共享同一数据库。

### Q: 如何处理大量区域？

A: 使用分页查询和数据库索引。对于超大规模部署，建议使用主从复制和读写分离。

## 贡献指南

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License

## 联系方式

如有问题或建议，请提交 Issue。

## 更新日志

### v1.0.0 (2024)

- 初始版本发布
- 实现完整的 PowerDNS API 功能
- 支持根记录 CNAME 添加
- 提供中文文档和注释
