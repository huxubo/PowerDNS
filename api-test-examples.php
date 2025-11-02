<?php
/**
 * PowerDNS API 完整测试示例
 * 
 * 此文件包含了 PowerDNS API 的所有主要功能的测试示例
 * 包括服务器信息、区域管理、记录管理、搜索等功能
 * 
 * 使用方法：
 * 1. 确保 API 服务已启动
 * 2. 修改下面的配置信息
 * 3. 运行: php api-test-examples.php
 */

// ==================== 配置部分 ====================
$apiConfig = [
    'base_url' => 'http://localhost/powerdns-api',  // 修改为你的API地址
    'api_key' => 'powerdns-api-key-change-me',      // 修改为你的API密钥
    'server_id' => 'localhost',                     // 服务器ID
];

// ==================== 通用函数 ====================

/**
 * 发送HTTP请求
 * 
 * @param string $method HTTP方法
 * @param string $url 请求URL
 * @param array $data 请求数据
 * @param array $headers 额外的请求头
 * @return array 响应数据
 */
function sendRequest($method, $url, $data = null, $headers = []) {
    global $apiConfig;
    
    $ch = curl_init();
    
    // 构建完整URL
    $fullUrl = $apiConfig['base_url'] . $url;
    
    // 设置基本选项
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // 设置请求头
    $requestHeaders = [
        'X-API-Key: ' . $apiConfig['api_key'],
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if (!empty($headers)) {
        $requestHeaders = array_merge($requestHeaders, $headers);
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    
    // 设置请求数据
    if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => $error,
            'http_code' => 0,
            'data' => null
        ];
    }
    
    $responseData = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $responseData,
        'raw_response' => $response
    ];
}

/**
 * 打印测试结果
 * 
 * @param string $testName 测试名称
 * @param array $result 测试结果
 */
function printResult($testName, $result) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "测试: $testName\n";
    echo str_repeat("-", 60) . "\n";
    
    echo "状态码: {$result['http_code']}\n";
    echo "成功: " . ($result['success'] ? '✅' : '❌') . "\n";
    
    if (!$result['success'] && isset($result['error'])) {
        echo "错误: {$result['error']}\n";
    }
    
    echo "\n响应数据:\n";
    echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

/**
 * 打印步骤信息
 * 
 * @param string $message 信息内容
 */
function printStep($message) {
    echo "\n" . str_repeat("·", 60) . "\n";
    echo "步骤: $message\n";
    echo str_repeat("·", 60) . "\n";
}

// ==================== 测试用例 ====================

/**
 * 1. 测试服务器信息
 */
function testServerInfo() {
    printStep("获取服务器信息");
    
    $result = sendRequest('GET', '/api/v1/servers');
    printResult("获取服务器列表", $result);
    
    if ($result['success'] && !empty($result['data'])) {
        $server = $result['data'][0];
        printResult("服务器详情", [
            'success' => true,
            'http_code' => 200,
            'data' => $server
        ]);
    }
}

/**
 * 2. 测试区域管理
 */
function testZoneManagement() {
    global $apiConfig;
    
    printStep("区域管理测试");
    
    // 2.1 列出所有区域
    $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/zones");
    printResult("列出所有区域", $result);
    
    // 2.2 创建新区域
    $zoneData = [
        'name' => 'test-example.com.',
        'kind' => 'Native',
        'nameservers' => [
            'ns1.test-example.com.',
            'ns2.test-example.com.'
        ],
        'soa' => [
            'primary' => 'ns1.test-example.com.',
            'hostmaster' => 'hostmaster.test-example.com.',
            'refresh' => 3600,
            'retry' => 1800,
            'expire' => 604800,
            'minimum' => 86400,
            'serial' => date('Ymd') . '01'
        ]
    ];
    
    $result = sendRequest('POST', "/api/v1/servers/{$apiConfig['server_id']}/zones", $zoneData);
    printResult("创建新区域", $result);
    
    // 如果创建成功，保存区域信息用于后续测试
    $zoneName = null;
    if ($result['success'] && isset($result['data']['name'])) {
        $zoneName = $result['data']['name'];
    }
    
    // 2.3 获取区域详情
    if ($zoneName) {
        $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName));
        printResult("获取区域详情", $result);
    }
    
    return $zoneName;
}

/**
 * 3. 测试记录管理
 */
function testRecordManagement($zoneName) {
    global $apiConfig;
    
    if (!$zoneName) {
        echo "\n⚠️  跳过记录管理测试（没有可用的区域）\n";
        return;
    }
    
    printStep("记录管理测试");
    
    // 3.1 添加A记录
    $recordData = [
        'rrsets' => [
            [
                'name' => 'www.' . $zoneName,
                'type' => 'A',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => '192.168.1.100',
                        'disabled' => false
                    ],
                    [
                        'content' => '192.168.1.101',
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加A记录 (www)", $result);
    
    // 3.2 添加AAAA记录
    $recordData = [
        'rrsets' => [
            [
                'name' => 'ipv6.' . $zoneName,
                'type' => 'AAAA',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => '2001:db8::1',
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加AAAA记录 (ipv6)", $result);
    
    // 3.3 添加CNAME记录
    $recordData = [
        'rrsets' => [
            [
                'name' => 'mail.' . $zoneName,
                'type' => 'CNAME',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => 'mail.example.com.',
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加CNAME记录 (mail)", $result);
    
    // 3.4 添加MX记录
    $recordData = [
        'rrsets' => [
            [
                'name' => $zoneName,
                'type' => 'MX',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => '10 mail.' . $zoneName,
                        'disabled' => false
                    ],
                    [
                        'content' => '20 backup.' . $zoneName,
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加MX记录", $result);
    
    // 3.5 添加TXT记录
    $recordData = [
        'rrsets' => [
            [
                'name' => '_dmarc.' . $zoneName,
                'type' => 'TXT',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => '"v=DMARC1; p=none; rua=mailto:dmarc@' . rtrim($zoneName, '.') . '"',
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加TXT记录 (DMARC)", $result);
    
    // 3.6 测试根记录CNAME（特殊功能）
    $recordData = [
        'rrsets' => [
            [
                'name' => $zoneName,
                'type' => 'CNAME',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => 'target.example.com.',
                        'disabled' => false
                    ]
                ]
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("添加根记录CNAME（特殊功能）", $result);
    
    // 3.7 删除记录
    $recordData = [
        'rrsets' => [
            [
                'name' => 'www.' . $zoneName,
                'type' => 'A',
                'changetype' => 'DELETE'
            ]
        ]
    ];
    
    $result = sendRequest('PATCH', "/api/v1/servers/{$apiConfig['server_id']}/zones/" . urlencode($zoneName), $recordData);
    printResult("删除A记录 (www)", $result);
}

/**
 * 4. 测试搜索功能
 */
function testSearchFunction() {
    global $apiConfig;
    
    printStep("搜索功能测试");
    
    // 4.1 搜索记录
    $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/search-data?q=test&max=50");
    printResult("搜索记录 (关键词: test)", $result);
    
    // 4.2 搜索特定域名
    $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/search-data?q=example.com&max=10");
    printResult("搜索特定域名 (example.com)", $result);
}

/**
 * 5. 测试缓存管理
 */
function testCacheManagement() {
    global $apiConfig;
    
    printStep("缓存管理测试");
    
    // 5.1 清除特定域名缓存
    $result = sendRequest('PUT', "/api/v1/servers/{$apiConfig['server_id']}/cache/flush?domain=test-example.com.");
    printResult("清除特定域名缓存", $result);
    
    // 5.2 清除所有缓存
    $result = sendRequest('PUT', "/api/v1/servers/{$apiConfig['server_id']}/cache/flush");
    printResult("清除所有缓存", $result);
}

/**
 * 6. 测试统计信息
 */
function testStatistics() {
    global $apiConfig;
    
    printStep("统计信息测试");
    
    $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/statistics");
    printResult("获取统计信息", $result);
}

/**
 * 7. 测试错误处理
 */
function testErrorHandling() {
    global $apiConfig;
    
    printStep("错误处理测试");
    
    // 7.1 无效的API Key
    $originalKey = $apiConfig['api_key'];
    $apiConfig['api_key'] = 'invalid-key';
    
    $result = sendRequest('GET', '/api/v1/servers');
    printResult("无效API Key测试", $result);
    
    // 恢复原始API Key
    $apiConfig['api_key'] = $originalKey;
    
    // 7.2 访问不存在的区域
    $result = sendRequest('GET', "/api/v1/servers/{$apiConfig['server_id']}/zones/nonexistent.zone.");
    printResult("访问不存在的区域", $result);
    
    // 7.3 无效的请求格式
    $invalidData = ['invalid' => 'data'];
    $result = sendRequest('POST', "/api/v1/servers/{$apiConfig['server_id']}/zones", $invalidData);
    printResult("无效请求格式", $result);
}

/**
 * 8. 性能测试
 */
function testPerformance() {
    global $apiConfig;
    
    printStep("性能测试");
    
    $startTime = microtime(true);
    
    // 连续发送10个请求
    for ($i = 0; $i < 10; $i++) {
        sendRequest('GET', '/api/v1/servers');
    }
    
    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    $avgTime = $totalTime / 10;
    
    echo "\n性能测试结果:\n";
    echo "总时间: " . number_format($totalTime, 3) . " 秒\n";
    echo "平均时间: " . number_format($avgTime, 3) . " 秒/请求\n";
    echo "QPS: " . number_format(1 / $avgTime, 2) . " 请求/秒\n";
}

// ==================== 主程序 ====================

function main() {
    global $apiConfig;
    
    echo "PowerDNS API 完整测试示例\n";
    echo str_repeat("=", 60) . "\n";
    echo "API地址: {$apiConfig['base_url']}\n";
    echo "服务器ID: {$apiConfig['server_id']}\n";
    echo "开始时间: " . date('Y-m-d H:i:s') . "\n";
    
    // 检查API连接
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "检查API连接...\n";
    
    $testResult = sendRequest('GET', '/api/v1/servers');
    if (!$testResult['success']) {
        echo "❌ API连接失败！请检查配置和网络连接。\n";
        echo "错误信息: " . ($testResult['error'] ?? '未知错误') . "\n";
        return;
    }
    
    echo "✅ API连接成功！\n";
    
    // 执行所有测试
    testServerInfo();
    $zoneName = testZoneManagement();
    testRecordManagement($zoneName);
    testSearchFunction();
    testCacheManagement();
    testStatistics();
    testErrorHandling();
    testPerformance();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "测试完成！\n";
    echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat("=", 60) . "\n";
}

// 运行主程序
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "请在命令行中运行此脚本: php api-test-examples.php\n";
}