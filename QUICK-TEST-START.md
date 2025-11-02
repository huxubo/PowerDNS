# PowerDNS API 测试示例 - 快速开始

## 📁 已创建的测试文件

我已经为这个PowerDNS API项目生成了完整的测试示例，包含以下文件：

### 🚀 核心测试文件
1. **`api-test-examples.php`** - 完整的PHP测试脚本（推荐）
2. **`curl-test-examples.sh`** - 基于curl的Shell测试脚本
3. **`quick-test.php`** - 快速验证脚本

### 📋 配置和工具
4. **`test-config.example.php`** - 测试配置示例
5. **`postman-collection.json`** - Postman测试集合
6. **`API-TEST-README.md`** - 详细使用文档
7. **`TEST-FILES-INDEX.md`** - 测试文件索引

## ⚡ 快速使用

### 方法1：PHP脚本（推荐）
```bash
# 1. 修改配置
# 编辑 api-test-examples.php 中的 $apiConfig 数组

# 2. 快速测试
php quick-test.php

# 3. 完整测试
php api-test-examples.php
```

### 方法2：Shell脚本
```bash
# 1. 修改配置
# 编辑 curl-test-examples.sh 中的配置变量

# 2. 给脚本执行权限
chmod +x curl-test-examples.sh

# 3. 运行测试
./curl-test-examples.sh
```

### 方法3：Postman
1. 导入 `postman-collection.json` 到Postman
2. 修改环境变量：`base_url`、`api_key`、`server_id`
3. 运行测试集合

## 🔧 配置要求

在使用前，请修改以下配置：

### API基础URL
```php
'base_url' => 'http://your-domain.com/powerdns-api'
```

### API密钥
```php
'api_key' => 'your-actual-api-key'
```

### 服务器ID
```php
'server_id' => 'localhost'
```

## 📊 测试覆盖

✅ **服务器管理** - 获取服务器信息、统计信息  
✅ **区域管理** - 创建、查询、删除DNS区域  
✅ **记录管理** - A、AAAA、CNAME、MX、TXT记录  
✅ **搜索功能** - 记录搜索和查询  
✅ **缓存管理** - 清除缓存  
✅ **错误处理** - 各种错误情况测试  
✅ **性能测试** - 响应时间和QPS测试  
✅ **特殊功能** - 根记录CNAME支持  

## 🎯 特色功能

- **根记录CNAME支持**：测试API的特殊功能
- **完整错误处理**：包含各种边界情况
- **性能基准测试**：QPS和响应时间测试
- **多种测试方式**：PHP、Shell、Postman三种选择
- **中文文档**：详细的中文使用说明
- **自动化测试**：可自动清理测试数据

## 📖 详细文档

查看 `API-TEST-README.md` 获取：
- 详细的安装和配置说明
- 所有测试用例的说明
- 故障排除指南
- 性能优化建议
- 自定义测试方法

## ⚠️ 注意事项

1. 请在测试环境中运行，避免影响生产数据
2. 确保API服务已正常启动
3. 检查数据库连接是否正常
4. 验证API密钥配置是否正确

## 🆘 获取帮助

如果遇到问题：
1. 查看 `API-TEST-README.md` 的故障排除部分
2. 检查项目的 `README.md` 和 `INSTALL.md`
3. 运行 `php quick-test.php` 进行快速诊断

---

**现在你可以开始测试PowerDNS API了！** 🎉