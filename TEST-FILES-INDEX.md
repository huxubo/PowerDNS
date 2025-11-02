# PowerDNS API 测试文件索引

本文档列出了项目中所有与测试相关的文件及其用途。

## 📂 测试文件列表

### 核心测试文件

| 文件名 | 类型 | 描述 | 用法 |
|--------|------|------|------|
| [`api-test-examples.php`](./api-test-examples.php) | PHP脚本 | 完整的PHP测试脚本 | `php api-test-examples.php` |
| [`curl-test-examples.sh`](./curl-test-examples.sh) | Shell脚本 | 基于curl的命令行测试 | `./curl-test-examples.sh` |
| [`quick-test.php`](./quick-test.php) | PHP脚本 | 快速验证API功能 | `php quick-test.php` |

### 配置文件

| 文件名 | 类型 | 描述 | 用法 |
|--------|------|------|------|
| [`test-config.example.php`](./test-config.example.php) | PHP配置 | 测试配置示例 | 复制为 `test-config.php` 并修改 |

### 工具文件

| 文件名 | 类型 | 描述 | 用法 |
|--------|------|------|------|
| [`postman-collection.json`](./postman-collection.json) | Postman集合 | Postman测试集合 | 导入到Postman |

### 文档文件

| 文件名 | 类型 | 描述 | 用法 |
|--------|------|------|------|
| [`API-TEST-README.md`](./API-TEST-README.md) | Markdown文档 | 详细的测试说明文档 | 阅读使用指南 |
| [`TEST-FILES-INDEX.md`](./TEST-FILES-INDEX.md) | Markdown文档 | 本索引文件 | 快速了解测试文件 |

## 🚀 快速开始

### 1. 选择测试方式

根据你的环境和需求选择合适的测试方式：

#### PHP开发者
```bash
# 快速验证
php quick-test.php

# 完整测试
php api-test-examples.php
```

#### 命令行用户
```bash
# 给脚本执行权限
chmod +x curl-test-examples.sh

# 运行测试
./curl-test-examples.sh
```

#### GUI用户
1. 打开Postman
2. 导入 `postman-collection.json`
3. 修改环境变量
4. 运行测试

### 2. 配置API连接

修改相应的配置文件：
- PHP脚本：修改脚本中的 `$apiConfig` 数组
- Shell脚本：修改脚本变量或使用环境变量
- Postman：修改环境变量

### 3. 运行测试

按照对应文件的使用说明运行测试。

## 📋 测试覆盖范围

### 功能测试
- ✅ 服务器信息获取
- ✅ 区域管理（增删改查）
- ✅ 记录管理（A、AAAA、CNAME、MX、TXT等）
- ✅ 搜索功能
- ✅ 缓存管理
- ✅ 统计信息

### 特殊功能测试
- ✅ 根记录CNAME支持
- ✅ 批量操作
- ✅ 错误处理

### 性能测试
- ✅ 响应时间测试
- ✅ 并发测试
- ✅ QPS测试

### 边界测试
- ✅ 无效API Key
- ✅ 不存在的资源
- ✅ 无效数据格式

## 🛠️ 自定义测试

### 添加新的测试用例

#### PHP脚本
在 `api-test-examples.php` 中添加新函数：
```php
function customTest() {
    printStep("自定义测试");
    $result = sendRequest('GET', '/api/v1/custom-endpoint');
    printResult("自定义测试", $result);
}
```

#### Shell脚本
在 `curl-test-examples.sh` 中添加新函数：
```bash
test_custom() {
    print_step "自定义测试"
    local response=$(api_request "GET" "/api/v1/custom-endpoint")
    # 处理响应
}
```

#### Postman
在Postman中添加新的请求到集合中。

### 配置自定义测试数据

编辑 `test-config.example.php` 文件中的测试数据配置。

## 🐛 故障排除

### 常见问题

1. **连接失败**
   - 检查API服务是否启动
   - 验证URL是否正确
   - 确认网络连接

2. **认证失败**
   - 验证API Key是否正确
   - 检查配置文件中的密钥设置

3. **权限问题**
   - 确保Shell脚本有执行权限
   - 检查文件和目录权限

4. **依赖问题**
   - 安装必要的PHP扩展
   - 安装curl和jq工具

### 调试技巧

- 使用 `-v` 参数查看详细请求信息
- 检查API日志文件
- 使用浏览器开发者工具查看请求

## 📈 性能基准

### 参考指标

| 测试类型 | 预期结果 | 说明 |
|---------|---------|------|
| 基本请求响应时间 | < 100ms | 简单的GET请求 |
| 复杂操作响应时间 | < 500ms | 创建/更新操作 |
| 并发处理能力 | > 50 QPS | 10个并发请求 |
| 错误处理 | < 1s | 错误响应时间 |

### 性能优化建议

1. 启用PHP OPcache
2. 使用数据库连接池
3. 配置适当的缓存策略
4. 优化数据库查询

## 🔗 相关文档

- [项目主README](./README.md) - 项目总体介绍
- [安装指南](./INSTALL.md) - 详细的安装步骤
- [宝塔部署指南](./BAOTA_DEPLOY.md) - 宝塔面板部署
- [故障排除](./TROUBLESHOOTING.md) - 常见问题解决

## 🤝 贡献

欢迎提交新的测试用例和改进建议：

1. Fork项目
2. 创建功能分支
3. 提交更改
4. 发起Pull Request

## 📝 更新记录

- **2024-11-01**: 创建完整的测试套件
- 包含PHP、Shell、Postman三种测试方式
- 覆盖所有主要API功能
- 包含性能测试和错误处理测试

---

**提示**: 建议在测试环境中运行这些测试，避免影响生产数据。