#!/bin/bash

# PowerDNS API curl 测试示例
# 使用前请修改以下配置变量

# ==================== 配置部分 ====================
API_BASE_URL="http://localhost/powerdns-api"
API_KEY="powerdns-api-key-change-me"
SERVER_ID="localhost"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ==================== 通用函数 ====================

# 打印分隔线
print_separator() {
    echo -e "${BLUE}============================================================${NC}"
}

# 打印步骤
print_step() {
    echo -e "\n${YELLOW}步骤: $1${NC}"
    print_separator
}

# 打印测试结果
print_result() {
    local test_name="$1"
    local http_code="$2"
    
    echo -e "\n${GREEN}测试: $test_name${NC}"
    echo -e "状态码: $http_code"
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "结果: ✅ 成功"
    else
        echo -e "结果: ❌ 失败"
    fi
}

# 发送curl请求的通用函数
api_request() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local extra_headers="$4"
    
    local url="${API_BASE_URL}${endpoint}"
    local headers="-H \"X-API-Key: ${API_KEY}\" -H \"Content-Type: application/json\" -H \"Accept: application/json\""
    
    if [ -n "$extra_headers" ]; then
        headers="$headers $extra_headers"
    fi
    
    echo -e "${BLUE}请求: $method $url${NC}"
    
    if [ "$method" = "GET" ] || [ "$method" = "DELETE" ]; then
        eval "curl -s -w \"\\n%{http_code}\" -X $method $headers \"$url\""
    else
        if [ -n "$data" ]; then
            eval "curl -s -w \"\\n%{http_code}\" -X $method $headers -d \"$data\" \"$url\""
        else
            eval "curl -s -w \"\\n%{http_code}\" -X $method $headers \"$url\""
        fi
    fi
}

# ==================== 测试函数 ====================

# 1. 测试服务器信息
test_server_info() {
    print_step "获取服务器信息"
    
    local response=$(api_request "GET" "/api/v1/servers")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "获取服务器列表" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 2. 测试区域管理
test_zone_management() {
    print_step "区域管理测试"
    
    # 2.1 列出所有区域
    echo -e "${BLUE}2.1 列出所有区域${NC}"
    local response=$(api_request "GET" "/api/v1/servers/${SERVER_ID}/zones")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "列出所有区域" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 2.2 创建新区域
    echo -e "\n${BLUE}2.2 创建新区域${NC}"
    local zone_data='{
        "name": "test-example.com.",
        "kind": "Native",
        "nameservers": [
            "ns1.test-example.com.",
            "ns2.test-example.com."
        ]
    }'
    
    local response=$(api_request "POST" "/api/v1/servers/${SERVER_ID}/zones" "$zone_data")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "创建新区域" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 保存区域名称用于后续测试
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        export TEST_ZONE=$(echo "$body" | jq -r '.name' 2>/dev/null || echo "test-example.com.")
        echo -e "\n${GREEN}创建的区域: $TEST_ZONE${NC}"
    else
        export TEST_ZONE="test-example.com."
    fi
}

# 3. 测试记录管理
test_record_management() {
    print_step "记录管理测试"
    
    if [ -z "$TEST_ZONE" ]; then
        export TEST_ZONE="test-example.com."
    fi
    
    # 3.1 添加A记录
    echo -e "${BLUE}3.1 添加A记录${NC}"
    local record_data='{
        "rrsets": [
            {
                "name": "'$TEST_ZONE'",
                "type": "A",
                "ttl": 3600,
                "changetype": "REPLACE",
                "records": [
                    {
                        "content": "192.168.1.100",
                        "disabled": false
                    }
                ]
            }
        ]
    }'
    
    local response=$(api_request "PATCH" "/api/v1/servers/${SERVER_ID}/zones/${TEST_ZONE}" "$record_data")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "添加A记录" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 3.2 添加CNAME记录
    echo -e "\n${BLUE}3.2 添加CNAME记录${NC}"
    local record_data='{
        "rrsets": [
            {
                "name": "www.'$TEST_ZONE'",
                "type": "CNAME",
                "ttl": 3600,
                "changetype": "REPLACE",
                "records": [
                    {
                        "content": "'$TEST_ZONE'",
                        "disabled": false
                    }
                ]
            }
        ]
    }'
    
    local response=$(api_request "PATCH" "/api/v1/servers/${SERVER_ID}/zones/${TEST_ZONE}" "$record_data")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "添加CNAME记录" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 3.3 添加MX记录
    echo -e "\n${BLUE}3.3 添加MX记录${NC}"
    local record_data='{
        "rrsets": [
            {
                "name": "'$TEST_ZONE'",
                "type": "MX",
                "ttl": 3600,
                "changetype": "REPLACE",
                "records": [
                    {
                        "content": "10 mail.'$TEST_ZONE'",
                        "disabled": false
                    }
                ]
            }
        ]
    }'
    
    local response=$(api_request "PATCH" "/api/v1/servers/${SERVER_ID}/zones/${TEST_ZONE}" "$record_data")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "添加MX记录" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 3.4 获取区域详情（查看所有记录）
    echo -e "\n${BLUE}3.4 获取区域详情${NC}"
    local response=$(api_request "GET" "/api/v1/servers/${SERVER_ID}/zones/${TEST_ZONE}")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "获取区域详情" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 4. 测试搜索功能
test_search() {
    print_step "搜索功能测试"
    
    local response=$(api_request "GET" "/api/v1/servers/${SERVER_ID}/search-data?q=test&max=50")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "搜索记录" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 5. 测试缓存管理
test_cache() {
    print_step "缓存管理测试"
    
    local response=$(api_request "PUT" "/api/v1/servers/${SERVER_ID}/cache/flush?domain=${TEST_ZONE}")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "清除特定域名缓存" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 6. 测试统计信息
test_statistics() {
    print_step "统计信息测试"
    
    local response=$(api_request "GET" "/api/v1/servers/${SERVER_ID}/statistics")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "获取统计信息" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 7. 测试错误处理
test_errors() {
    print_step "错误处理测试"
    
    # 7.1 无效的API Key
    echo -e "${BLUE}7.1 无效API Key测试${NC}"
    local original_key="$API_KEY"
    API_KEY="invalid-key"
    
    local response=$(api_request "GET" "/api/v1/servers")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "无效API Key" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # 恢复原始API Key
    API_KEY="$original_key"
    
    # 7.2 访问不存在的区域
    echo -e "\n${BLUE}7.2 访问不存在的区域${NC}"
    local response=$(api_request "GET" "/api/v1/servers/${SERVER_ID}/zones/nonexistent.zone.")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    print_result "访问不存在的区域" "$http_code"
    echo -e "\n响应数据:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
}

# 8. 性能测试
test_performance() {
    print_step "性能测试"
    
    echo -e "${BLUE}发送10个并发请求测试响应时间...${NC}"
    
    local start_time=$(date +%s.%N)
    
    # 连续发送10个请求
    for i in {1..10}; do
        api_request "GET" "/api/v1/servers" > /dev/null
    done
    
    local end_time=$(date +%s.%N)
    local total_time=$(echo "$end_time - $start_time" | bc)
    local avg_time=$(echo "scale=3; $total_time / 10" | bc)
    local qps=$(echo "scale=2; 1 / $avg_time" | bc)
    
    echo -e "\n${GREEN}性能测试结果:${NC}"
    echo "总时间: $(printf "%.3f" $total_time) 秒"
    echo "平均时间: $(printf "%.3f" $avg_time) 秒/请求"
    echo "QPS: $(printf "%.2f" $qps) 请求/秒"
}

# 9. 清理测试数据
cleanup() {
    print_step "清理测试数据"
    
    if [ -n "$TEST_ZONE" ]; then
        echo -e "${BLUE}删除测试区域: $TEST_ZONE${NC}"
        local response=$(api_request "DELETE" "/api/v1/servers/${SERVER_ID}/zones/${TEST_ZONE}")
        local http_code=$(echo "$response" | tail -n1)
        
        print_result "删除测试区域" "$http_code"
    fi
}

# ==================== 主程序 ====================

main() {
    echo -e "${GREEN}PowerDNS API curl 测试示例${NC}"
    print_separator
    echo "API地址: $API_BASE_URL"
    echo "服务器ID: $SERVER_ID"
    echo "开始时间: $(date '+%Y-%m-%d %H:%M:%S')"
    
    # 检查依赖
    if ! command -v curl &> /dev/null; then
        echo -e "${RED}错误: curl 未安装${NC}"
        exit 1
    fi
    
    if ! command -v jq &> /dev/null; then
        echo -e "${YELLOW}警告: jq 未安装，JSON响应将不会格式化${NC}"
    fi
    
    # 检查API连接
    print_step "检查API连接"
    local response=$(api_request "GET" "/api/v1/servers")
    local http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✅ API连接成功！${NC}"
    else
        echo -e "${RED}❌ API连接失败！请检查配置和网络连接。${NC}"
        echo -e "状态码: $http_code"
        exit 1
    fi
    
    # 执行测试
    test_server_info
    test_zone_management
    test_record_management
    test_search
    test_cache
    test_statistics
    test_errors
    test_performance
    
    # 询问是否清理
    echo -e "\n${YELLOW}是否清理测试数据？(y/N):${NC}"
    read -r cleanup_choice
    if [[ $cleanup_choice =~ ^[Yy]$ ]]; then
        cleanup
    fi
    
    print_separator
    echo -e "${GREEN}测试完成！${NC}"
    echo "结束时间: $(date '+%Y-%m-%d %H:%M:%S')"
    print_separator
}

# 显示使用说明
show_help() {
    echo "PowerDNS API curl 测试脚本"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  -h, --help     显示此帮助信息"
    echo "  -c, --cleanup  仅执行清理操作"
    echo "  -p, --perf     仅执行性能测试"
    echo ""
    echo "环境变量:"
    echo "  API_BASE_URL   API基础URL (默认: http://localhost/powerdns-api)"
    echo "  API_KEY        API密钥 (默认: powerdns-api-key-change-me)"
    echo "  SERVER_ID      服务器ID (默认: localhost)"
    echo ""
    echo "示例:"
    echo "  API_BASE_URL=http://my-api.com $0"
    echo "  API_KEY=my-secret-key $0"
}

# 处理命令行参数
case "${1:-}" in
    -h|--help)
        show_help
        exit 0
        ;;
    -c|--cleanup)
        cleanup
        exit 0
        ;;
    -p|--perf)
        test_performance
        exit 0
        ;;
    "")
        main
        ;;
    *)
        echo "未知选项: $1"
        show_help
        exit 1
        ;;
esac