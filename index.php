<?php
/**
 * PowerDNS API 入口文件
 * 
 * 处理所有 API 请求的路由分发
 */

// 加载自动加载器（必须在 use 语句之前）
require_once __DIR__ . '/src/autoload.php';

// 错误报告（生产环境中应关闭）
error_reporting(E_ALL);
ini_set('display_errors', '0');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

use PowerDNS\Models\Database;
use PowerDNS\Utils\Response;
use PowerDNS\Utils\Auth;
use PowerDNS\Api\ServerController;
use PowerDNS\Api\ZoneController;

// 加载配置
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => '配置文件不存在',
        'message' => '请先创建配置文件 config/config.php',
        'instructions' => [
            '1. 复制 config/config.example.php 为 config/config.php',
            '2. 编辑 config/config.php 配置数据库和 API Key',
            '3. 详细部署指南请查看: BAOTA_DEPLOY.md 或 INSTALL.md',
        ],
        'help' => '如使用宝塔面板，请查看项目根目录的 BAOTA_DEPLOY.md 文件'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$config = require $configFile;

// 设置 CORS 头
if ($config['security']['cors_enabled']) {
    Response::setCorsHeaders($config['security']);
}

// 初始化数据库
try {
    $db = Database::getInstance($config['database']);
} catch (Exception $e) {
    error_log('数据库初始化失败: ' . $e->getMessage());
    Response::serverError('数据库连接失败');
}

// 认证
$auth = new Auth($db, $config);

// 检查 IP 白名单
if (!$auth->checkIpWhitelist()) {
    Response::unauthorized('IP 地址不在白名单中');
}

// 验证 API Key
if (!$auth->authenticate()) {
    Response::unauthorized('无效的 API Key');
}

// 解析请求
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($uri);
$path = $parsedUrl['path'];

// 移除查询字符串并标准化路径
$path = rtrim($path, '/');

// 路由匹配
try {
    // API 版本
    $apiVersion = $config['api']['version'];
    
    // 匹配路由
    if (preg_match("#^/api/{$apiVersion}/servers$#", $path)) {
        // GET /api/v1/servers
        if ($method === 'GET') {
            $controller = new ServerController($db, $config);
            $controller->listServers();
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id
        $serverId = $matches[1];
        if ($method === 'GET') {
            $controller = new ServerController($db, $config);
            $controller->getServer($serverId);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/statistics$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id/statistics
        $serverId = $matches[1];
        if ($method === 'GET') {
            $controller = new ServerController($db, $config);
            $controller->getStatistics($serverId);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/config$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id/config
        $serverId = $matches[1];
        if ($method === 'GET') {
            $controller = new ServerController($db, $config);
            $controller->getConfig($serverId);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/search-data$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id/search-data
        $serverId = $matches[1];
        if ($method === 'GET') {
            $query = $_GET['q'] ?? '';
            $max = (int)($_GET['max'] ?? 100);
            $controller = new ServerController($db, $config);
            $controller->search($serverId, $query, $max);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/cache/flush$#", $path, $matches)) {
        // PUT /api/v1/servers/:server_id/cache/flush
        $serverId = $matches[1];
        if ($method === 'PUT') {
            $domain = $_GET['domain'] ?? null;
            $controller = new ServerController($db, $config);
            $controller->flushCache($serverId, $domain);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id/zones (列出区域)
        // POST /api/v1/servers/:server_id/zones (创建区域)
        $serverId = $matches[1];
        $controller = new ZoneController($db, $config);
        
        if ($method === 'GET') {
            $controller->listZones($serverId);
        } elseif ($method === 'POST') {
            $controller->createZone($serverId);
        }
    } elseif (preg_match("#^/api/{$apiVersion}/servers/([^/]+)/zones/([^/]+)$#", $path, $matches)) {
        // GET /api/v1/servers/:server_id/zones/:zone_id (获取区域)
        // PATCH /api/v1/servers/:server_id/zones/:zone_id (更新区域)
        // DELETE /api/v1/servers/:server_id/zones/:zone_id (删除区域)
        $serverId = $matches[1];
        $zoneId = urldecode($matches[2]);
        $controller = new ZoneController($db, $config);
        
        if ($method === 'GET') {
            $controller->getZone($serverId, $zoneId);
        } elseif ($method === 'PATCH') {
            $controller->updateZone($serverId, $zoneId);
        } elseif ($method === 'DELETE') {
            $controller->deleteZone($serverId, $zoneId);
        }
    } elseif ($path === '/' || $path === '') {
        // API 根路径
        Response::success([
            'message' => 'PowerDNS API - PHP Implementation',
            'version' => $config['api']['server_version'],
            'endpoints' => [
                'servers' => "/api/{$apiVersion}/servers",
                'documentation' => 'https://doc.powerdns.com/authoritative/http-api/',
            ],
        ]);
    } else {
        // 未找到路由
        Response::notFound('API 端点不存在');
    }
    
} catch (Exception $e) {
    // 捕获所有异常
    error_log('API 错误: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    if ($config['debug']) {
        Response::serverError('服务器错误: ' . $e->getMessage());
    } else {
        Response::serverError('服务器内部错误');
    }
}
