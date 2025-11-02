<?php
/**
 * 测试脚本：检查域名是否存在
 */

// 加载自动加载器
require_once __DIR__ . '/src/autoload.php';

// 加载配置
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    die("配置文件不存在\n");
}
$config = require $configFile;

use PowerDNS\Models\Database;
use PowerDNS\Models\Domain;

try {
    // 初始化数据库
    $db = Database::getInstance($config['database']);
    echo "数据库连接成功\n";
    
    // 创建域名模型
    $domainModel = new Domain($db);
    
    // 检查域名
    $domainName = '108.xx.kg';
    echo "检查域名: {$domainName}\n";
    
    $domain = $domainModel->getByName($domainName);
    
    if ($domain) {
        echo "找到域名记录:\n";
        echo "ID: {$domain['id']}\n";
        echo "名称: {$domain['name']}\n";
        echo "类型: {$domain['type']}\n";
    } else {
        echo "域名不存在\n";
        
        // 尝试带点的版本
        $domainWithDot = $domainName . '.';
        echo "检查域名: {$domainWithDot}\n";
        $domain = $domainModel->getByName($domainWithDot);
        
        if ($domain) {
            echo "找到域名记录（带点）:\n";
            echo "ID: {$domain['id']}\n";
            echo "名称: {$domain['name']}\n";
            echo "类型: {$domain['type']}\n";
        } else {
            echo "带点版本的域名也不存在\n";
        }
    }
    
    // 列出所有域名
    echo "\n所有域名:\n";
    $allDomains = $domainModel->getAll();
    foreach ($allDomains as $domain) {
        echo "- {$domain['name']} (ID: {$domain['id']}, Type: {$domain['type']})\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}