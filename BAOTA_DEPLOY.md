# 宝塔面板部署指南

## 快速部署步骤

### 1. 创建网站

在宝塔面板中创建网站：
- 点击"网站" → "添加站点"
- 输入域名或使用服务器 IP
- 选择 PHP 版本：**PHP 7.4 或更高**
- 数据库选择：MySQL
- 网站根目录：例如 `/www/wwwroot/powerdns-api`

### 2. 配置伪静态（URL 重写）

#### 如果使用 Nginx（推荐）

在网站设置中，点击"伪静态"，添加以下规则：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# 禁止访问隐藏文件
location ~ /\. {
    deny all;
}

# 禁止访问配置文件目录
location ~ /config/ {
    deny all;
}
```

**注意**：`fastcgi_pass` 的值可能因 PHP 版本不同而不同，常见的有：
- `unix:/tmp/php-cgi-74.sock` (PHP 7.4)
- `unix:/tmp/php-cgi-80.sock` (PHP 8.0)
- `unix:/tmp/php-cgi.sock` (默认)

可以在"软件商店" → "PHP" → "设置" → "配置文件"中查看实际的 sock 文件路径。

#### 如果使用 Apache

项目已包含 `.htaccess` 文件，无需额外配置。确保启用了 `mod_rewrite` 模块。

### 3. 配置数据库

#### 3.1 创建数据库

在宝塔面板中：
1. 点击"数据库"
2. 点击"添加数据库"
3. 填写信息：
   - 数据库名：`powerdns`
   - 用户名：`powerdns`
   - 密码：使用宝塔生成的强密码
4. 点击"提交"

#### 3.2 导入数据库架构

1. 在数据库列表中，找到 `powerdns` 数据库
2. 点击"管理" → "导入"
3. 选择项目中的 `database/schema.sql` 文件
4. 点击"导入"

或者使用命令行（在宝塔终端中）：

```bash
cd /www/wwwroot/powerdns-api
mysql -u powerdns -p powerdns < database/schema.sql
```

### 4. 配置 API

#### 4.1 创建配置文件

在项目根目录下执行：

```bash
cd /www/wwwroot/powerdns-api
cp config/config.example.php config/config.php
```

或者在宝塔文件管理器中：
1. 进入 `config` 目录
2. 复制 `config.example.php`
3. 将副本重命名为 `config.php`

#### 4.2 编辑配置文件

编辑 `config/config.php`：

```php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'powerdns',
        'username' => 'powerdns',
        'password' => '在宝塔数据库中看到的密码',  // 修改这里
    ],
    
    'api' => [
        'key' => '生成一个强密码',  // 修改这里，用于 API 认证
    ],
    
    // 其他配置保持默认即可
];
```

**生成强 API Key：**
在宝塔终端中执行：
```bash
openssl rand -hex 32
```

或使用在线工具生成 64 位随机字符串。

### 5. 设置文件权限

在宝塔终端中执行：

```bash
cd /www/wwwroot/powerdns-api

# 设置所有者为 www 用户（宝塔默认用户）
chown -R www:www /www/wwwroot/powerdns-api

# 保护配置文件
chmod 600 config/config.php

# 创建日志目录
mkdir -p logs
chown www:www logs
chmod 755 logs
```

### 6. 安装 PHP 扩展

确保安装了以下 PHP 扩展：

1. 在宝塔面板，点击"软件商店"
2. 找到对应的 PHP 版本（如 PHP 7.4）
3. 点击"设置" → "安装扩展"
4. 确保安装以下扩展：
   - ✅ mysqli 或 pdo_mysql（通常已安装）
   - ✅ opcache（可选，提升性能）
   - ✅ mbstring（通常已安装）

### 7. 测试部署

#### 7.1 访问首页

在浏览器中访问：`http://你的域名/` 或 `http://服务器IP/`

应该看到类似以下的 JSON 响应：

```json
{
  "message": "PowerDNS API - PHP Implementation",
  "version": "PHP-PowerDNS-API-1.0.0",
  "endpoints": {
    "servers": "/api/v1/servers",
    "documentation": "https://doc.powerdns.com/authoritative/http-api/"
  }
}
```

#### 7.2 测试 API

使用 curl 或 Postman 测试：

```bash
curl -H "X-API-Key: 你的API密钥" http://你的域名/api/v1/servers
```

应该返回服务器列表：

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

## 常见问题排查

### 问题 1：访问显示 404 Not Found 或目录列表

**原因**：
- 伪静态配置未生效
- index.php 文件不存在

**解决方案**：
1. 确认已配置伪静态规则（Nginx 或 Apache）
2. 检查 `/www/wwwroot/你的项目/index.php` 文件是否存在
3. 检查文件权限：`ls -la index.php`

### 问题 2：500 Internal Server Error

**原因**：
- PHP 错误
- 配置文件不存在
- 数据库连接失败

**解决方案**：

1. 查看错误日志：
   - 宝塔面板 → 网站 → 设置 → 日志
   - 或查看：`/www/wwwroot/你的项目/logs/api.log`

2. 常见错误：
   - "配置文件不存在"：确保 `config/config.php` 存在
   - "数据库连接失败"：检查数据库配置是否正确
   - PHP 扩展缺失：安装必需的扩展

3. 开启 PHP 错误显示（调试用，生产环境不要开启）：
   编辑 `index.php`，第 10 行改为：
   ```php
   ini_set('display_errors', '1');
   ```

### 问题 3：API 返回 401 Unauthorized

**原因**：
- API Key 错误
- 未在请求头中包含 API Key

**解决方案**：
1. 确认 API Key 正确（查看 `config/config.php` 中的 `api.key`）
2. 确保请求包含正确的 Header：
   ```
   X-API-Key: 你的API密钥
   ```

### 问题 4：HTTPS 证书问题

**解决方案**：
1. 在宝塔面板中，进入网站设置
2. 点击"SSL"
3. 选择"Let's Encrypt" 免费证书
4. 点击"申请"
5. 开启"强制 HTTPS"

### 问题 5：跨域 CORS 问题

如果前端调用 API 出现跨域错误：

编辑 `config/config.php`：

```php
'security' => [
    'cors_enabled' => true,
    'cors_origins' => ['*'],  // 或指定具体域名 ['https://yourdomain.com']
],
```

## 性能优化建议

### 1. 启用 OPcache

在宝塔面板中：
1. 软件商店 → PHP → 设置
2. 安装扩展 → 安装 opcache
3. 配置 opcache（可选）

### 2. 启用 PHP-FPM

宝塔默认已启用，确保配置合理：
1. 软件商店 → PHP → 设置 → 性能调整
2. 根据服务器内存调整 `max_children` 等参数

### 3. 配置 MySQL 优化

1. 软件商店 → MySQL → 设置 → 性能调整
2. 选择适合服务器内存的配置方案

### 4. 启用 Gzip 压缩

在宝塔面板：
1. 网站设置 → 配置文件
2. 确保启用了 gzip 压缩

### 5. 使用 Redis 缓存（可选）

如果需要高性能缓存：
1. 软件商店 → 安装 Redis
2. 修改代码使用 Redis 缓存查询结果

## 安全建议

### 1. 修改默认端口（可选）

将 SSH 和 数据库端口改为非标准端口

### 2. 配置防火墙

在宝塔面板 → 安全：
- 仅开放必要的端口（80, 443, SSH端口）
- 开启 SSH 防暴力破解

### 3. 定期备份

在宝塔面板 → 计划任务：
- 设置每天备份网站文件
- 设置每天备份数据库
- 配置自动清理旧备份

### 4. 使用强密码

- API Key：至少 32 字符
- 数据库密码：使用宝塔生成的强密码
- 宝塔面板密码：定期更换

### 5. 限制 API 访问

编辑 `config/config.php`：

```php
'security' => [
    'allowed_ips' => [
        '192.168.1.100',  // 只允许这些 IP 访问
        '10.0.0.1',
    ],
],
```

## 更新部署

当需要更新代码时：

```bash
cd /www/wwwroot/powerdns-api
git pull origin main
chown -R www:www .
```

或在宝塔文件管理器中直接上传新文件。

## 监控和日志

### 查看访问日志

宝塔面板 → 网站 → 设置 → 日志 → 访问日志

### 查看错误日志

```bash
tail -f /www/wwwroot/powerdns-api/logs/api.log
tail -f /www/server/panel/logs/error.log
```

### 查看 PHP 错误日志

宝塔面板 → 网站 → 设置 → 日志 → PHP 错误日志

## 联系支持

如遇到部署问题，请提供以下信息：
- 宝塔面板版本
- PHP 版本
- 错误日志内容
- 访问的 URL 和返回的错误信息

## 附录：完整部署检查清单

- [ ] 创建网站
- [ ] 配置伪静态规则
- [ ] 创建数据库
- [ ] 导入数据库架构
- [ ] 复制并编辑 `config/config.php`
- [ ] 设置文件权限
- [ ] 安装必需的 PHP 扩展
- [ ] 测试首页访问
- [ ] 测试 API 端点
- [ ] 配置 SSL 证书（如需要）
- [ ] 设置自动备份
- [ ] 配置防火墙规则

完成以上步骤后，您的 PowerDNS API 应该可以正常运行了！
