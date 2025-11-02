# PowerDNS API 完整测试示例

本文档提供了完整的 PowerDNS API 测试示例，包含多种测试方式和详细的用例，帮助您验证 API 的所有功能。

## 📁 文件说明

| 文件名 | 类型 | 描述 |
|--------|------|------|
| `api-test-examples.php` | PHP脚本 | 完整的PHP测试脚本，包含所有API功能测试 |
| `curl-test-examples.sh` | Shell脚本 | 基于curl的命令行测试脚本 |
| `postman-collection.json` | Postman集合 | 可导入Postman的完整测试集合 |
| `API-TEST-README.md` | 文档 | 本说明文档 |

## 🚀 快速开始

### 前置条件

1. **API服务已部署**：确保 PowerDNS API 服务已正常运行
2. **配置文件**：已创建并配置 `config/config.php`
3. **数据库**：数据库架构已导入，连接正常
4. **PHP环境**：PHP 7.4+ （用于PHP脚本测试）
5. **curl工具**：命令行HTTP客户端
6. **jq工具**：JSON格式化工具（可选，用于Shell脚本）

### 配置API连接

在使用测试脚本之前，需要修改配置信息：

#### PHP脚本配置
编辑 `api-test-examples.php` 文件中的配置部分：
```php
$apiConfig = [
    'base_url' => 'http://your-domain.com/powerdns-api',  // 修改为你的API地址
    'api_key' => 'your-actual-api-key',                   // 修改为你的API密钥
    'server_id' => 'localhost',                           // 服务器ID
];
```

#### Shell脚本配置
可以通过以下方式配置：
```bash
# 方式1：修改脚本中的变量
API_BASE_URL="http://your-domain.com/powerdns-api"
API_KEY="your-actual-api-key"
SERVER_ID="localhost"

# 方式2：使用环境变量
export API_BASE_URL="http://your-domain.com/powerdns-api"
export API_KEY="your-actual-api-key"
export SERVER_ID="localhost"
./curl-test-examples.sh
```

#### Postman配置
导入 `postman-collection.json` 后，修改环境变量：
- `base_url`: API基础URL
- `api_key`: API密钥
- `server_id`: 服务器ID

## 📋 测试用例覆盖

### 1. 服务器管理
- ✅ 获取服务器列表
- ✅ 获取服务器详情
- ✅ 获取统计信息

### 2. 区域管理
- ✅ 列出所有区域
- ✅ 创建新区域
- ✅ 获取区域详情
- ✅ 删除区域

### 3. 记录管理
- ✅ 添加A记录
- ✅ 添加AAAA记录
- ✅ 添加CNAME记录
- ✅ 添加MX记录
- ✅ 添加TXT记录
- ✅ 根记录CNAME（特殊功能）
- ✅ 删除记录

### 4. 搜索功能
- ✅ 搜索记录
- ✅ 按域名搜索

### 5. 缓存管理
- ✅ 清除特定域名缓存
- ✅ 清除所有缓存

### 6. 错误处理
- ✅ 无效API Key
- ✅ 访问不存在的区域
- ✅ 无效请求格式

### 7. 性能测试
- ✅ 响应时间测试
- ✅ QPS测试

## 🛠️ 使用方法

### 方法1：PHP脚本测试

```bash
# 运行完整测试
php api-test-examples.php

# 查看帮助
php api-test-examples.php --help
```

**特点：**
- 完整的测试覆盖
- 详细的错误处理
- 美观的输出格式
- 自动性能测试
- 中文输出

### 方法2：Shell脚本测试

```bash
# 给脚本执行权限
chmod +x curl-test-examples.sh

# 运行完整测试
./curl-test-examples.sh

# 查看帮助
./curl-test-examples.sh --help

# 仅运行性能测试
./curl-test-examples.sh --perf

# 仅清理测试数据
./curl-test-examples.sh --cleanup
```

**特点：**
- 轻量级，无PHP依赖
- 彩色输出
- 支持环境变量配置
- 可选择性运行测试

### 方法3：Postman测试

1. **导入集合**：
   - 打开Postman
   - 点击 Import
   - 选择 `postman-collection.json` 文件

2. **配置环境变量**：
   - 点击环境设置
   - 修改 `base_url`、`api_key`、`server_id` 等变量

3. **运行测试**：
   - 可以单独运行每个请求
   - 或运行整个集合进行批量测试

**特点：**
- 图形化界面
- 自动化测试脚本
- 详细的测试断言
- 支持环境变量

## 📊 测试结果示例

### 成功响应示例

```json
{
  "id": "localhost",
  "type": "Server",
  "version": "PHP-PowerDNS-API-1.0.0",
  "daemon_type": "authoritative",
  "url": "/api/v1/servers/localhost"
}
```

### 错误响应示例

```json
{
  "error": "Unauthorized",
  "message": "Invalid API key",
  "code": 401
}
```

## 🔧 高级用法

### 自定义测试用例

#### PHP脚本
可以修改 `api-test-examples.php` 添加自定义测试：

```php
function customTest() {
    printStep("自定义测试");
    
    $result = sendRequest('GET', '/api/v1/custom-endpoint');
    printResult("自定义测试", $result);
}

// 在main()函数中调用
main() {
    // ... 其他测试
    customTest();
}
```

#### Shell脚本
可以添加新的测试函数：

```bash
test_custom() {
    print_step "自定义测试"
    
    local response=$(api_request "GET" "/api/v1/custom-endpoint")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "自定义测试" "$http_code"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}
```

### 批量测试

#### 测试多个域名
```php
$testDomains = ['example1.com', 'example2.com', 'example3.com'];

foreach ($testDomains as $domain) {
    $zoneData = [
        'name' => $domain . '.',
        'kind' => 'Native',
        'nameservers' => ['ns1.' . $domain . '.', 'ns2.' . $domain . '.']
    ];
    
    $result = sendRequest('POST', "/api/v1/servers/{$apiConfig['server_id']}/zones", $zoneData);
    printResult("创建区域: $domain", $result);
}
```

### 性能基准测试

#### 并发测试
```bash
# 使用xargs进行并发测试
seq 1 100 | xargs -P 10 -I {} curl -s -H "X-API-Key: $API_KEY" "$API_BASE_URL/api/v1/servers" > /dev/null
```

## 🐛 故障排除

### 常见问题

#### 1. 连接失败
```
错误: API连接失败！
解决: 检查API服务是否启动，URL是否正确
```

#### 2. 认证失败
```
状态码: 401
解决: 检查API Key是否正确，配置文件中的key设置
```

#### 3. 数据库错误
```
状态码: 500
解决: 检查数据库连接，确保schema已导入
```

#### 4. 权限问题
```
错误: Permission denied
解决: 检查文件权限，确保脚本有执行权限
```

### 调试技巧

#### 启用详细输出
```bash
# Shell脚本中查看详细请求
curl -v -H "X-API-Key: $API_KEY" "$API_BASE_URL/api/v1/servers"
```

#### 检查响应头
```php
// PHP脚本中添加调试信息
echo "响应头: " . print_r($http_response_header, true) . "\n";
```

#### 查看日志
```bash
# 查看API日志
tail -f logs/api.log

# 查看Web服务器日志
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log
```

## 📈 性能优化建议

### 1. 数据库优化
- 确保索引已创建
- 使用连接池
- 启用查询缓存

### 2. API优化
- 启用OPcache
- 使用持久连接
- 实施速率限制

### 3. 测试优化
- 使用并发测试
- 批量操作
- 缓存测试结果

## 🤝 贡献指南

欢迎提交更多测试用例和改进建议：

1. Fork 项目
2. 创建功能分支
3. 提交更改
4. 发起 Pull Request

## 📝 更新日志

### v1.0.0 (2024-11-01)
- 初始版本发布
- 包含完整的API测试用例
- 支持PHP、Shell、Postman三种测试方式
- 包含性能测试和错误处理测试

## 📄 许可证

MIT License - 详见项目根目录的 LICENSE 文件

## 🆘 获取帮助

如果在使用过程中遇到问题：

1. 查看本文档的故障排除部分
2. 检查项目的 `README.md` 和 `INSTALL.md`
3. 提交 Issue 到项目仓库
4. 查看项目文档目录中的其他文档

---

**注意**: 在生产环境中运行测试时，请确保：
- 使用测试专用的API Key
- 在测试数据库中操作
- 避免影响生产数据
- 遵守安全最佳实践