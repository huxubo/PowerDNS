# 文档目录

本目录包含 PowerDNS API 项目的各类文档。

## 部署相关文档

### 宝塔面板部署（推荐新手使用）

- **[../BAOTA_DEPLOY.md](../BAOTA_DEPLOY.md)** - 宝塔面板完整部署指南
  - 详细的步骤说明
  - 常见问题解决方案
  - 安全配置建议
  
- **[BAOTA_SCREENSHOTS.md](BAOTA_SCREENSHOTS.md)** - 宝塔面板配置图文说明
  - 每个配置步骤的详细说明
  - 配置参数解释
  - 验证方法
  
- **[baota-nginx-rewrite.conf](baota-nginx-rewrite.conf)** - Nginx 伪静态规则
  - 直接复制使用的 Nginx 配置
  - 包含安全规则
  - 适配不同 PHP 版本

### 通用部署文档

- **[../INSTALL.md](../INSTALL.md)** - 详细安装指南
  - Ubuntu/Debian 安装
  - CentOS/RHEL 安装
  - Docker 部署
  - 手动配置步骤

## 功能文档

- **[API使用指南.md](API使用指南.md)** - API 接口使用说明
  - 所有 API 端点详解
  - 请求和响应示例
  - 认证方式说明

- **[CNAME展平技术说明.md](CNAME展平技术说明.md)** - CNAME 技术说明
  - 根记录 CNAME 支持说明
  - 技术实现细节
  - 与标准 PowerDNS 的差异

- **[部署指南.md](部署指南.md)** - 综合部署指南
  - 生产环境部署建议
  - 性能优化配置
  - 监控和维护

## 快速开始

### 如果您使用宝塔面板：

1. 阅读 [../BAOTA_DEPLOY.md](../BAOTA_DEPLOY.md)
2. 运行快速配置脚本：`bash quick-setup.sh`
3. 按照提示完成剩余配置

### 如果您使用其他环境：

1. 阅读 [../INSTALL.md](../INSTALL.md)
2. 按照对应系统的步骤进行安装
3. 参考 API 使用指南开始使用

## 部署工具

项目根目录提供了以下工具帮助部署：

- **quick-setup.sh** - 快速配置脚本
  ```bash
  chmod +x quick-setup.sh
  ./quick-setup.sh
  ```

- **check-deployment.php** - 部署环境检查
  ```bash
  php check-deployment.php
  ```

## 获取帮助

如遇到问题：

1. 查看对应文档的"常见问题"部分
2. 运行 `check-deployment.php` 诊断问题
3. 查看项目 Issues
4. 提交新的 Issue

## 文档贡献

欢迎改进文档！如发现错误或有改进建议，请提交 Pull Request。
