<?php
/**
 * 调试脚本：帮助诊断API请求问题
 */

echo "=== PowerDNS API 调试信息 ===\n\n";

// 模拟请求数据
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/servers/localhost/zones/108.xx.kg';
$_SERVER['HTTP_HOST'] = 'studio.xx.kg';

// 加载自动加载器
require_once __DIR__ . '/src/autoload.php';

// 加载配置
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    echo "错误：配置文件不存在\n";
    exit;
}

$config = require $configFile;
echo "✓ 配置文件已加载\n";
echo "  API版本: {$config['api']['version']}\n";
echo "  服务器ID: {$config['api']['server_id']}\n\n";

// 解析请求路径
$uri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($uri);
$path = $parsedUrl['path'];
$path = rtrim($path, '/');

echo "请求信息:\n";
echo "  方法: {$_SERVER['REQUEST_METHOD']}\n";
echo "  URI: {$uri}\n";
echo "  路径: {$path}\n\n";

// 路由匹配测试
$apiVersion = $config['api']['version'];

echo "路由匹配测试:\n";

// 测试服务器路由
if (preg_match("#^/api/{$apiVersion}/servers$#", $path)) {
    echo "  ✓ 匹配: /api/{$apiVersion}/servers\n";
} elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)$#", $path, $matches)) {
    echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id\n";
    echo "    服务器ID: {$matches[1]}\n";
} elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones$#", $path, $matches)) {
    echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/zones\n";
    echo "    服务器ID: {$matches[1]}\n";
} elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones/([^/]+)$#", $path, $matches)) {
    echo "  ✓ 匹配: /api/{$apiVersion}/servers/:server_id/zones/:zone_id\n";
    echo "    服务器ID: {$matches[1]}\n";
    echo "    域名ID: " . urldecode($matches[2]) . "\n";
    
    // 这里应该调用 ZoneController->getZone
    $serverId = $matches[1];
    $zoneId = urldecode($matches[2]);
    
    echo "\n数据库查询测试:\n";
    echo "  查找域名: {$zoneId}\n";
    
    try {
        use PowerDNS\Models\Database;
        use PowerDNS\Models\Domain;
        
        // 初始化数据库
        $db = Database::getInstance($config['database']);
        echo "  ✓ 数据库连接成功\n";
        
        // 创建域名模型
        $domainModel = new Domain($db);
        
        // 检查域名
        $domain = $domainModel->getByName($zoneId);
        
        if ($domain) {
            echo "  ✓ 找到域名记录:\n";
            echo "    ID: {$domain['id']}\n";
            echo "    名称: {$domain['name']}\n";
            echo "    类型: {$domain['type']}\n";
        } else {
            echo "  ✗ 域名不存在: {$zoneId}\n";
            
            // 尝试其他格式
            $alternatives = [
                $zoneId . '.',
                rtrim($zoneId, '.')
            ];
            
            foreach ($alternatives as $alt) {
                if ($alt !== $zoneId) {
                    $domain = $domainModel->getByName($alt);
                    if ($domain) {
                        echo "  ✓ 找到域名记录（尝试: {$alt}）:\n";
                        echo "    ID: {$domain['id']}\n";
                        echo "    名称: {$domain['name']}\n";
                        echo "    类型: {$domain['type']}\n";
                        break;
                    }
                }
            }
            
            if (!$domain) {
                echo "  ✗ 所有格式都未找到域名\n";
                
                // 列出所有域名
                echo "\n现有域名列表:\n";
                $allDomains = $domainModel->getAll();
                if (empty($allDomains)) {
                    echo "  (数据库中没有域名)\n";
                } else {
                    foreach ($allDomains as $domain) {
                        echo "  - {$domain['name']} (ID: {$domain['id']})\n";
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "  ✗ 数据库错误: " . $e->getMessage() . "\n";
    }
    
} elseif ($path === '/' || $path === '') {
    echo "  ✓ 匹配: 根路径\n";
} else {
    echo "  ✗ 未找到匹配的路由\n";
}

echo "\n=== 调试完成 ===\n";