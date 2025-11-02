<?php
/**
 * 测试路由逻辑
 */

echo "=== 路由测试 ===\n\n";

// 模拟配置
$config = [
    'api' => [
        'version' => 'v1',
        'server_id' => 'localhost'
    ]
];

// 模拟请求
$testRequests = [
    [
        'method' => 'GET',
        'uri' => '/api/v1/servers/localhost/zones/108.xx.kg',
        'description' => '目标请求'
    ],
    [
        'method' => 'GET', 
        'uri' => '/api/v1/servers/localhost/zones/108.xx.kg/',
        'description' => '带尾部斜杠的请求'
    ],
    [
        'method' => 'GET',
        'uri' => '/api/v1/servers/localhost/zones/example.com',
        'description' => '测试域名'
    ],
    [
        'method' => 'GET',
        'uri' => '/api/v1/servers/localhost/zones',
        'description' => '列出所有区域'
    ]
];

foreach ($testRequests as $test) {
    echo "测试: {$test['description']}\n";
    echo "  请求: {$test['method']} {$test['uri']}\n";
    
    $method = $test['method'];
    $uri = $test['uri'];
    $parsedUrl = parse_url($uri);
    $path = $parsedUrl['path'];
    
    // 移除查询字符串并标准化路径
    $path = rtrim($path, '/');
    
    echo "  处理后路径: {$path}\n";
    
    // API 版本
    $apiVersion = $config['api']['version'];
    
    // 路由匹配
    $matched = false;
    
    if (preg_match("#^/api/{$apiVersion}/servers$#", $path)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/statistics$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/statistics\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/config$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/config\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/search-data$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/search-data\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/cache/flush$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/cache/flush\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/zones\n";
        echo "    服务器ID: {$matches[1]}\n";
        $matched = true;
        
        if ($method === 'GET') {
            echo "    动作: 列出区域 (ZoneController->listZones)\n";
        } elseif ($method === 'POST') {
            echo "    动作: 创建区域 (ZoneController->createZone)\n";
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones/([^/]+)$#", $path, $matches)) {
        echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/zones/:zone_id\n";
        echo "    服务器ID: {$matches[1]}\n";
        echo "    域名ID: " . urldecode($matches[2]) . "\n";
        $matched = true;
        
        if ($method === 'GET') {
            echo "    动作: 获取区域 (ZoneController->getZone)\n";
        } elseif ($method === 'PATCH') {
            echo "    动作: 更新区域 (ZoneController->updateZone)\n";
        } elseif ($method === 'DELETE') {
            echo "    动作: 删除区域 (ZoneController->deleteZone)\n";
        }
    } elseif ($path === '/' || $path === '') {
        echo "  ✓ 匹配: 根路径\n";
        $matched = true;
    }
    
    if (!$matched) {
        echo "  ✗ 未找到匹配的路由\n";
    }
    
    echo "\n";
}

echo "=== 路由测试完成 ===\n";