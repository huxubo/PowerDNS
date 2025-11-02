# 宝塔面板配置截图说明

本文档提供宝塔面板配置的详细步骤和说明（文字版）。

## 目录

1. [创建网站](#1-创建网站)
2. [设置运行目录](#2-设置运行目录重要)
3. [配置伪静态](#3-配置伪静态)
4. [创建数据库](#4-创建数据库)
5. [安装 PHP 扩展](#5-安装-php-扩展)
6. [配置 SSL 证书](#6-配置-ssl-证书可选)

---

## 1. 创建网站

### 步骤：

1. 登录宝塔面板
2. 点击左侧菜单 **「网站」**
3. 点击顶部 **「添加站点」** 按钮

### 配置信息：

```
域名：yourdomain.com （或使用服务器IP地址）
备注：PowerDNS API
根目录：/www/wwwroot/powerdns-api （可自定义）
FTP：不创建
数据库：MySQL （选择创建数据库）
  - 数据库名：powerdns
  - 用户名：powerdns
  - 密码：（使用宝塔生成的强密码）
PHP 版本：PHP 7.4 或更高
```

### 要点：
- ✅ 记下数据库密码，稍后需要填写到配置文件中
- ✅ 如果已有数据库，可选择"不创建"

---

## 2. 设置运行目录（⚠️ 重要）

### 这是最关键的步骤！

### 步骤：

1. 在网站列表中找到刚创建的网站
2. 点击右侧 **「设置」** 按钮  
3. 在弹出的设置窗口中，点击左侧 **「网站目录」** 选项卡
4. 确认 **「网站目录」** 设置

### 配置：

```
网站目录：/www/wwwroot/powerdns-api （默认即可）
运行目录：/ （默认即可，无需修改）
```

### 说明：

项目入口文件 `index.php` 位于项目根目录，因此无需设置特殊的运行目录。

### 验证：

配置完成后，在浏览器访问网站：
- ✅ 正确：显示 JSON 格式的 API 信息或错误提示
- ❌ 错误：显示目录列表或"运行目录配置错误"页面

---

## 3. 配置伪静态

### 步骤：

1. 在网站设置窗口中，点击左侧 **「伪静态」** 选项卡
2. 清空现有内容（如果有）
3. 复制以下规则粘贴进去

### Nginx 伪静态规则：

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

location ~ /\. {
    deny all;
}

location ~ /config/ {
    deny all;
}
```

### 注意事项：

⚠️ **关于 `fastcgi_pass` 参数：**

不同 PHP 版本的 sock 文件路径不同，常见的有：

```
PHP 7.4: unix:/tmp/php-cgi-74.sock
PHP 8.0: unix:/tmp/php-cgi-80.sock
PHP 8.1: unix:/tmp/php-cgi-81.sock
默认:    unix:/tmp/php-cgi.sock
```

**如何查看实际路径？**

1. 在宝塔面板，点击 **「软件商店」**
2. 找到你安装的 PHP 版本（如 PHP-7.4）
3. 点击 **「设置」**
4. 点击 **「配置文件」**
5. 搜索 `listen =` 找到实际的 sock 文件路径

### 验证：

配置完成后，测试 API 端点：

```bash
curl http://你的域名/api/v1/servers
```

应该返回 JSON 格式的响应，而不是 404 错误。

---

## 4. 创建数据库

如果在创建网站时已经创建了数据库，可跳过此步骤。

### 步骤：

1. 点击左侧菜单 **「数据库」**
2. 点击 **「添加数据库」** 按钮

### 配置：

```
数据库名：powerdns
用户名：powerdns
密码：（使用强密码，建议点击"随机生成"）
访问权限：本地服务器
备注：PowerDNS API 数据库
```

### 导入数据库架构：

#### 方法 1：通过 phpMyAdmin

1. 在数据库列表中，点击 `powerdns` 数据库的 **「管理」** 按钮
2. 在 phpMyAdmin 中，点击 **「导入」** 选项卡
3. 点击 **「选择文件」**
4. 上传项目中的 `database/schema.sql` 文件
5. 点击 **「执行」** 按钮

#### 方法 2：通过宝塔终端

1. 点击左侧菜单 **「终端」**
2. 执行以下命令：

```bash
cd /www/wwwroot/powerdns-api
mysql -u powerdns -p powerdns < database/schema.sql
# 输入密码后回车
```

### 验证：

导入成功后，数据库中应该包含以下表：

- `domains` - 区域信息
- `records` - DNS 记录
- `domainmetadata` - 区域元数据
- `cryptokeys` - DNSSEC 密钥
- `tsigkeys` - TSIG 密钥
- `comments` - 记录注释

---

## 5. 安装 PHP 扩展

### 步骤：

1. 点击左侧菜单 **「软件商店」**
2. 在已安装软件中，找到对应的 PHP 版本（如 PHP-7.4）
3. 点击 **「设置」** 按钮
4. 点击 **「安装扩展」** 选项卡

### 必需的扩展：

检查以下扩展是否已安装（通常默认已安装）：

- ✅ **mysqli** 或 **pdo_mysql** - MySQL 数据库支持
- ✅ **mbstring** - 多字节字符串支持
- ✅ **json** - JSON 支持（PHP 7.4+ 内置）

### 推荐的扩展：

可选安装以下扩展以提升性能：

- 🔧 **opcache** - PHP 代码缓存，显著提升性能
- 🔧 **memcached** 或 **redis** - 如果需要高性能缓存

### 安装方法：

1. 在扩展列表中找到对应的扩展
2. 点击 **「安装」** 按钮
3. 等待安装完成（通常几秒到几分钟）
4. 安装完成后，重启 PHP

### 重启 PHP：

在 PHP 设置窗口中，点击 **「重载配置」** 或 **「重启」** 按钮。

---

## 6. 配置 SSL 证书（可选）

如果需要 HTTPS 访问（强烈推荐生产环境使用）：

### 步骤：

1. 在网站设置窗口中，点击 **「SSL」** 选项卡
2. 选择证书类型：

#### 选项 A：Let's Encrypt 免费证书（推荐）

```
1. 选择 "Let's Encrypt" 选项卡
2. 填写域名（确保域名已解析到服务器）
3. 填写邮箱地址
4. 点击 "申请" 按钮
5. 等待申请完成（通常几秒钟）
```

#### 选项 B：自签名证书（仅用于测试）

```
1. 选择 "自签名证书" 选项卡
2. 填写域名
3. 点击 "创建证书" 按钮
```

#### 选项 C：其他证书

```
1. 选择 "其他证书" 选项卡
2. 粘贴证书内容（.crt 或 .pem）
3. 粘贴私钥内容（.key）
4. 点击 "保存" 按钮
```

### 启用 HTTPS：

1. 证书安装成功后，开启 **「强制 HTTPS」** 开关
2. 这样所有 HTTP 请求会自动重定向到 HTTPS

### 验证：

```bash
curl https://你的域名/api/v1/servers
```

应该能正常访问，没有 SSL 证书错误。

---

## 常见问题

### Q1: 配置伪静态后仍然 404？

**可能原因：**
1. 伪静态规则中的 `fastcgi_pass` 路径不正确
2. 伪静态规则未生效

**解决方案：**
- 检查并修正 `fastcgi_pass` 路径
- 确认伪静态规则已配置
- 重启 Nginx：在宝塔终端执行 `nginx -s reload`

### Q2: 访问显示 500 错误？

**可能原因：**
1. PHP 扩展缺失
2. 文件权限问题
3. 配置文件错误

**解决方案：**
1. 查看错误日志：网站设置 → 日志 → PHP 错误日志
2. 检查文件权限：终端执行 `ls -la /www/wwwroot/powerdns-api`
3. 确保 config/config.php 存在且配置正确

### Q3: 数据库连接失败？

**解决方案：**
1. 检查 config/config.php 中的数据库配置
2. 确认数据库用户名和密码正确
3. 在宝塔面板的数据库列表中，检查数据库是否存在
4. 测试数据库连接：
   ```bash
   mysql -u powerdns -p -h localhost powerdns
   ```

---

## 配置检查清单

完成配置后，请确认以下各项：

- [ ] 网站已创建
- [ ] 伪静态规则已配置
- [ ] 数据库已创建并导入架构
- [ ] config/config.php 已创建并配置正确
- [ ] PHP 扩展已安装
- [ ] 文件权限设置正确（所有者为 www 或 www-data）
- [ ] 访问网站首页能看到 JSON 响应
- [ ] API 端点可以正常访问

---

## 下一步

配置完成后：

1. **测试 API：**
   ```bash
   curl -H "X-API-Key: 你的API密钥" http://你的域名/api/v1/servers
   ```

2. **查看完整 API 文档：**
   阅读项目根目录的 `README.md` 文件

3. **运行部署检查脚本：**
   ```bash
   php /www/wwwroot/powerdns-api/check-deployment.php
   ```

4. **配置定期备份：**
   宝塔面板 → 计划任务 → 添加备份任务

---

## 获取帮助

如遇到问题：

1. 查看 `BAOTA_DEPLOY.md` 中的常见问题部分
2. 运行 `check-deployment.php` 检查部署状态
3. 查看错误日志：网站设置 → 日志
4. 提交 Issue 到项目仓库

---

**文档版本：** 1.0.0  
**最后更新：** 2024  
**适用于：** 宝塔 Linux 面板 7.x 及以上
