<?php
/**
 * PowerDNS API 快速测试脚本
 * 
 * 这是一个简化版的测试脚本，用于快速验证API是否正常工作
 */

// 配置
$apiConfig = [
    'base_url' => 'http://localhost/powerdns-api',
    'api_key' => 'powerdns-api-key-change-me',
    'server_id' => 'localhost',
];

/**
 * 简单的HTTP请求函数
 */
function quickRequest($method, $url, $data = null) {
    global $apiConfig;
    
    $ch = curl_init();
    $fullUrl = $apiConfig['base_url'] . $url;
    
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = [
        'X-API-Key: ' . $apiConfig['api_key'],
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => 0];
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

/**
 * 快速测试函数
 */
function runQuickTest() {
    global $apiConfig;
    
    echo "PowerDNS API 快速测试\n";
    echo str_repeat("=", 40) . "\n";
    echo "API地址: {$apiConfig['base_url']}\n";
    echo "服务器ID: {$apiConfig['server_id']}\n\n";
    
    // 测试1: 基本连接
    echo "1. 测试API连接...\n";
    $result = quickRequest('GET', '/api/v1/servers');
    
    if ($result['success']) {
        echo "✅ API连接成功\n";
        echo "   状态码: {$result['http_code']}\n";
        if (!empty($result['data'])) {
            $server = $result['data'][0];
            echo "   服务器: {$server['id']}\n";
            echo "   版本: {$server['version']}\n";
        }
    } else {
        echo "❌ API连接失败\n";
        echo "   状态码: {$result['http_code']}\n";
        if (isset($result['error'])) {
            echo "   错误: {$result['error']}\n";
        }
        if (isset($result['data']['error'])) {
            echo "   API错误: {$result['data']['error']}\n";
        }
        echo "\n请检查:\n";
        echo "- API服务是否启动\n";
        echo "- URL地址是否正确\n";
        echo "- API Key是否正确\n";
        return false;
    }
    
    // 测试2: 区域列表
    echo "\n2. 测试区域列表...\n";
    $result = quickRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/zones");
    
    if ($result['success']) {
        echo "✅ 区域列表获取成功\n";
        echo "   区域数量: " . count($result['data']) . "\n";
    } else {
        echo "❌ 区域列表获取失败\n";
        echo "   状态码: {$result['http_code']}\n";
    }
    
    // 测试3: 创建测试区域
    echo "\n3. 测试创建区域...\n";
    $zoneData = [
        'name' => 'quick-test.example.com.',
        'kind' => 'Native',
        'nameservers' => ['ns1.test.com.', 'ns2.test.com.']
    ];
    
    $result = quickRequest('POST', "/api/v1/servers/{$apiConfig['server_id']}/zones", $zoneData);
    
    if ($result['success']) {
        echo "✅ 区域创建成功\n";
        echo "   区域名称: {$result['data']['name']}\n";
        $zoneName = $result['data']['name'];
    } else {
        echo "❌ 区域创建失败\n";
        echo "   状态码: {$result['http_code']}\n";
        if (isset($result['data']['error'])) {
            echo "   错误: {$result['data']['error']}\n";
        }
        $zoneName = null;
    }
    
    // 测试4: 添加记录（如果区域创建成功）
    if ($zoneName) {
        echo "\n4. 测试添加记录...\n";
        $recordData = [
            'rrsets' => [
                [
                    'name' => 'www.' . $zoneName,
                    'type' => 'A',
                    'ttl' => 3600,
                    'changetype' => 'REPLACE',
                    'records' => [
                        ['content' => '192.168.1.100', 'disabled' => false]
                    ]
                ]
            ]
        ];
        
        $result = quickRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/$zoneName", $recordData);
        
        if ($result['success']) {
            echo "✅ 记录添加成功\n";
        } else {
            echo "❌ 记录添加失败\n";
            echo "   状态码: {$result['http_code']}\n";
        }
        
        // 清理测试区域
        echo "\n5. 清理测试区域...\n";
        $result = quickRequest('DELETE', "/api/v1/servers/{$apiConfig['server_id']}/zones/$zoneName");
        
        if ($result['success']) {
            echo "✅ 测试区域已删除\n";
        } else {
            echo "⚠️  测试区域删除失败，请手动清理\n";
        }
    }
    
    echo "\n" . str_repeat("=", 40) . "\n";
    echo "快速测试完成！\n";
    
    return true;
}

// 检查是否在命令行中运行
if (php_sapi_name() === 'cli') {
    runQuickTest();
} else {
    echo "请在命令行中运行此脚本: php quick-test.php\n";
}