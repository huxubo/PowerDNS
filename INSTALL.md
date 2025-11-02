# PowerDNS API 快速安装指南

## 一键安装脚本（推荐）

### Ubuntu/Debian

```bash
#!/bin/bash
# PowerDNS API 一键安装脚本

set -e

echo "=== PowerDNS API 安装开始 ==="

# 1. 安装依赖
echo "安装系统依赖..."
sudo apt update
sudo apt install -y php8.0 php8.0-fpm php8.0-mysql php8.0-json php8.0-mbstring php8.0-opcache
sudo apt install -y mysql-server nginx git

# 2. 克隆项目
echo "下载项目..."
cd /var/www
sudo git clone https://github.com/yourusername/powerdns-api.git
cd powerdns-api

# 3. 配置数据库
echo "配置数据库..."
DB_PASSWORD=$(openssl rand -base64 16)
API_KEY=$(openssl rand -hex 32)

sudo mysql << EOF
CREATE DATABASE IF NOT EXISTS powerdns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'powerdns'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'localhost';
FLUSH PRIVILEGES;
EOF

# 4. 导入数据库架构
echo "导入数据库..."
mysql -u powerdns -p"${DB_PASSWORD}" powerdns < database/schema.sql

# 5. 配置 API
echo "配置 API..."
sudo cp config/config.example.php config/config.php
sudo sed -i "s/'password' => ''/'password' => '${DB_PASSWORD}'/" config/config.php
sudo sed -i "s/'key' => 'powerdns-api-key-change-me'/'key' => '${API_KEY}'/" config/config.php

# 6. 设置权限
echo "设置文件权限..."
sudo chown -R www-data:www-data /var/www/powerdns-api
sudo chmod 600 /var/www/powerdns-api/config/config.php
sudo mkdir -p /var/www/powerdns-api/logs
sudo chown www-data:www-data /var/www/powerdns-api/logs

# 7. 配置 Nginx
echo "配置 Nginx..."
sudo tee /etc/nginx/sites-available/powerdns-api > /dev/null << 'NGINX'
server {
    listen 80;
    server_name localhost;
    root /var/www/powerdns-api/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/powerdns-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.0-fpm

# 8. 测试安装
echo "测试 API..."
sleep 2
response=$(curl -s -o /dev/null -w "%{http_code}" -H "X-API-Key: ${API_KEY}" http://localhost/api/v1/servers)

if [ "$response" = "200" ]; then
    echo "=== 安装成功！==="
    echo ""
    echo "API 地址: http://localhost/api/v1"
    echo "API Key: ${API_KEY}"
    echo "数据库密码: ${DB_PASSWORD}"
    echo ""
    echo "请保存以上信息！"
    echo ""
    echo "测试命令:"
    echo "curl -H \"X-API-Key: ${API_KEY}\" http://localhost/api/v1/servers"
else
    echo "=== 安装可能存在问题 ==="
    echo "HTTP 响应码: $response"
    echo "请检查日志: sudo tail -f /var/log/nginx/error.log"
fi
```

保存为 `install.sh` 并执行：

```bash
chmod +x install.sh
./install.sh
```

## 手动安装步骤

### 第一步：安装系统依赖

#### Ubuntu/Debian

```bash
sudo apt update
sudo apt install -y php8.0 php8.0-fpm php8.0-mysql php8.0-json php8.0-mbstring
sudo apt install -y mysql-server nginx
```

#### CentOS/RHEL

```bash
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
sudo yum-config-manager --enable remi-php80
sudo yum install -y php php-fpm php-mysqlnd php-json php-mbstring
sudo yum install -y mysql-server httpd
```

### 第二步：下载项目

```bash
cd /var/www
sudo git clone <repository-url> powerdns-api
cd powerdns-api
```

### 第三步：配置数据库

```bash
# 登录 MySQL
sudo mysql

# 执行以下 SQL
CREATE DATABASE powerdns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'powerdns'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 导入数据库架构
mysql -u powerdns -p powerdns < database/schema.sql
```

### 第四步：配置 API

```bash
# 复制配置文件
sudo cp config/config.example.php config/config.php

# 编辑配置
sudo nano config/config.php
```

修改以下配置：

```php
'database' => [
    'username' => 'powerdns',
    'password' => 'your_password',  // 修改为您的密码
],

'api' => [
    'key' => 'your-secure-api-key',  // 修改为强密码
],
```

生成安全的 API Key：

```bash
openssl rand -hex 32
```

### 第五步：设置权限

```bash
# 设置所有者
sudo chown -R www-data:www-data /var/www/powerdns-api

# 设置目录权限
sudo find /var/www/powerdns-api -type d -exec chmod 755 {} \;

# 设置文件权限
sudo find /var/www/powerdns-api -type f -exec chmod 644 {} \;

# 保护配置文件
sudo chmod 600 /var/www/powerdns-api/config/config.php

# 创建日志目录
sudo mkdir -p /var/www/powerdns-api/logs
sudo chown www-data:www-data /var/www/powerdns-api/logs
```

### 第六步：配置 Web 服务器

#### Nginx

```bash
sudo nano /etc/nginx/sites-available/powerdns-api
```

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/powerdns-api/public;
    index index.php;
    
    access_log /var/log/nginx/powerdns-api-access.log;
    error_log /var/log/nginx/powerdns-api-error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
    
    location ~ /config/ {
        deny all;
    }
}
```

```bash
# 启用站点
sudo ln -s /etc/nginx/sites-available/powerdns-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### Apache

```bash
sudo nano /etc/apache2/sites-available/powerdns-api.conf
```

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/powerdns-api/public
    
    <Directory /var/www/powerdns-api/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/powerdns-api-error.log
    CustomLog ${APACHE_LOG_DIR}/powerdns-api-access.log combined
</VirtualHost>
```

```bash
# 启用模块和站点
sudo a2enmod rewrite
sudo a2ensite powerdns-api.conf
sudo systemctl restart apache2
```

### 第七步：测试安装

```bash
# 测试 API
curl -H "X-API-Key: your-api-key" http://localhost/api/v1/servers

# 应该返回类似以下的 JSON：
# [
#   {
#     "id": "localhost",
#     "type": "Server",
#     "version": "PHP-PowerDNS-API-1.0.0",
#     ...
#   }
# ]
```

## Docker 安装（可选）

### 创建 Dockerfile

```dockerfile
FROM php:8.0-fpm

# 安装扩展
RUN docker-php-ext-install pdo pdo_mysql

# 复制项目文件
COPY . /var/www/powerdns-api

# 设置权限
RUN chown -R www-data:www-data /var/www/powerdns-api

WORKDIR /var/www/powerdns-api

EXPOSE 9000
```

### 创建 docker-compose.yml

```yaml
version: '3'

services:
  web:
    image: nginx:latest
    ports:
      - "80:80"
    volumes:
      - ./:/var/www/powerdns-api
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
      
  php:
    build: .
    volumes:
      - ./:/var/www/powerdns-api
    depends_on:
      - mysql
      
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: powerdns
      MYSQL_USER: powerdns
      MYSQL_PASSWORD: powerdnspass
    volumes:
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

### 启动容器

```bash
docker-compose up -d
```

## 验证安装

### 1. 检查 API 服务

```bash
curl -H "X-API-Key: your-api-key" http://localhost/api/v1/servers
```

### 2. 检查数据库连接

```bash
mysql -u powerdns -p powerdns -e "SHOW TABLES;"
```

应该显示以下表：
- domains
- records
- domainmetadata
- cryptokeys
- tsigkeys
- api_keys
- cname_flatten_cache

### 3. 测试创建区域

```bash
curl -X POST http://localhost/api/v1/servers/localhost/zones \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test.com.",
    "kind": "Native",
    "nameservers": ["ns1.test.com.", "ns2.test.com."]
  }'
```

### 4. 查看日志

```bash
# API 日志
tail -f /var/www/powerdns-api/logs/api.log

# Nginx 错误日志
sudo tail -f /var/log/nginx/error.log

# PHP 错误日志
sudo tail -f /var/log/php8.0-fpm/error.log
```

## 常见安装问题

### 问题 1：500 Internal Server Error

**原因**：文件权限或 PHP 配置问题

**解决**：
```bash
# 检查权限
ls -la /var/www/powerdns-api/public

# 重新设置权限
sudo chown -R www-data:www-data /var/www/powerdns-api

# 查看错误日志
sudo tail -f /var/log/nginx/error.log
```

### 问题 2：数据库连接失败

**原因**：数据库配置错误或服务未启动

**解决**：
```bash
# 检查 MySQL 状态
sudo systemctl status mysql

# 测试数据库连接
mysql -u powerdns -p -h localhost powerdns

# 检查配置文件
cat /var/www/powerdns-api/config/config.php
```

### 问题 3：404 Not Found

**原因**：Web 服务器重写规则未生效

**解决**：
```bash
# Nginx: 检查配置
sudo nginx -t

# Apache: 启用 rewrite 模块
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 问题 4：PHP 扩展缺失

**原因**：未安装必需的 PHP 扩展

**解决**：
```bash
# 检查已安装的扩展
php -m

# 安装缺失的扩展
sudo apt install -y php8.0-mysql php8.0-json php8.0-mbstring
sudo systemctl restart php8.0-fpm
```

## 安装后配置

### 1. 启用 HTTPS

```bash
# 安装 Certbot
sudo apt install -y certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d api.yourdomain.com

# 自动续期测试
sudo certbot renew --dry-run
```

### 2. 配置防火墙

```bash
# UFW
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 3. 设置自动备份

```bash
# 创建备份脚本
sudo nano /usr/local/bin/backup-powerdns.sh

# 添加到 crontab
sudo crontab -e
0 2 * * * /usr/local/bin/backup-powerdns.sh
```

### 4. 配置监控

```bash
# 安装监控工具
sudo apt install -y monit

# 配置监控规则
sudo nano /etc/monit/conf.d/powerdns-api
```

## 下一步

安装完成后，请参考以下文档：

- [API 使用指南](docs/API使用指南.md)
- [CNAME 展平技术说明](docs/CNAME展平技术说明.md)
- [部署指南](docs/部署指南.md)

## 获取帮助

- 查看日志文件
- 阅读文档
- 提交 Issue

祝您使用愉快！🎉
