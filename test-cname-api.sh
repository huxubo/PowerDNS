#!/bin/bash

# PowerDNS API CNAME 独占性规则测试脚本
# 测试与官方 PowerDNS API 完全一致的 CNAME 记录独占性逻辑

API_BASE="http://localhost/api/v1"
API_KEY="test-api-key"

echo "=== PowerDNS API CNAME 独占性规则测试 ==="
echo "API 基础地址: $API_BASE"
echo "API 密钥: $API_KEY"
echo ""

# 函数：执行 API 请求
api_request() {
    local method=$1
    local url=$2
    local data=$3
    
    echo "请求: $method $url"
    if [ -n "$data" ]; then
        echo "数据: $data"
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X "$method" \
            -H "Content-Type: application/json" \
            -H "X-API-Key: $API_KEY" \
            -d "$data" \
            "$API_BASE$url")
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X "$method" \
            -H "X-API-Key: $API_KEY" \
            "$API_BASE$url")
    fi
    
    http_code=$(echo "$response" | tail -n1 | cut -d: -f2)
    body=$(echo "$response" | sed '$d')
    
    echo "状态码: $http_code"
    echo "响应: $body"
    echo "---"
}

# 测试场景 1: 创建测试区域
echo "1. 创建测试区域"
api_request "POST" "/servers/localhost/zones" '{
    "name": "test-cname.example.com.",
    "kind": "Native",
    "nameservers": ["ns1.example.com."]
}'

# 等待区域创建完成
sleep 2

# 测试场景 2: 在根记录添加 A 记录
echo "2. 在根记录添加 A 记录"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "test-cname.example.com.",
        "type": "A",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "192.168.1.10",
            "disabled": false
        }]
    }]
}'

# 测试场景 3: 尝试在已存在 A 记录的根记录上添加 CNAME（应该失败）
echo "3. 尝试在已存在 A 记录的根记录上添加 CNAME（应该失败）"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "test-cname.example.com.",
        "type": "CNAME",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "target.example.com.",
            "disabled": false
        }]
    }]
}'

# 测试场景 4: 删除 A 记录，添加 CNAME 记录
echo "4. 删除 A 记录"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "test-cname.example.com.",
        "type": "A",
        "changetype": "DELETE"
    }]
}'

echo "5. 添加 CNAME 记录"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "test-cname.example.com.",
        "type": "CNAME",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "target.example.com.",
            "disabled": false
        }]
    }]
}'

# 测试场景 5: 尝试在已存在 CNAME 记录的根记录上添加 A 记录（应该失败）
echo "6. 尝试在已存在 CNAME 记录的根记录上添加 A 记录（应该失败）"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "test-cname.example.com.",
        "type": "A",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "192.168.1.20",
            "disabled": false
        }]
    }]
}'

# 测试场景 6: 在子记录上测试 CNAME 独占性
echo "7. 在 www 子记录上添加 CNAME"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "www.test-cname.example.com.",
        "type": "CNAME",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "test-cname.example.com.",
            "disabled": false
        }]
    }]
}'

echo "8. 尝试在已存在 CNAME 的 www 记录上添加 A 记录（应该失败）"
api_request "PATCH" "/servers/localhost/zones/test-cname.example.com." '{
    "rrsets": [{
        "name": "www.test-cname.example.com.",
        "type": "A",
        "ttl": 3600,
        "changetype": "REPLACE",
        "records": [{
            "content": "192.168.1.30",
            "disabled": false
        }]
    }]
}'

# 测试场景 7: 查询区域详情，验证记录内容
echo "9. 查询区域详情"
api_request "GET" "/servers/localhost/zones/test-cname.example.com."

# 清理：删除测试区域
echo "10. 清理：删除测试区域"
api_request "DELETE" "/servers/localhost/zones/test-cname.example.com."

echo ""
echo "=== 测试结果总结 ==="
echo "预期结果："
echo "- 场景 3 和 6 和 8 应该返回 HTTP 422 状态码"
echo "- 错误消息格式: 'RRset {name} IN {type}: Conflicts with pre-existing RRset'"
echo "- 其他场景应该成功执行"
echo ""
echo "如果结果符合预期，说明 CNAME 独占性规则实现正确！"
echo "与官方 PowerDNS API 行为完全一致。"