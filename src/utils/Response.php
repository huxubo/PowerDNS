<?php
/**
 * HTTP 响应工具类
 * 
 * 统一处理 API 响应格式
 */

namespace PowerDNS\Utils;

class Response
{
    /**
     * 发送 JSON 响应
     * 
     * @param mixed $data 响应数据
     * @param int $statusCode HTTP 状态码
     * @param array $headers 额外的 HTTP 头
     */
    public static function json($data, int $statusCode = 200, array $headers = []): void
    {
        // 设置 HTTP 状态码
        http_response_code($statusCode);
        
        // 设置 Content-Type
        header('Content-Type: application/json; charset=utf-8');
        
        // 设置额外的头信息
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // 输出 JSON 数据
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * 发送成功响应
     * 
     * @param mixed $data 响应数据
     * @param int $statusCode HTTP 状态码
     */
    public static function success($data, int $statusCode = 200): void
    {
        self::json($data, $statusCode);
    }
    
    /**
     * 发送错误响应
     * 
     * @param string $message 错误消息
     * @param int $statusCode HTTP 状态码
     * @param array $errors 详细错误信息
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'error' => $message,
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        self::json($response, $statusCode);
    }
    
    /**
     * 发送 204 无内容响应
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
    
    /**
     * 发送 404 未找到响应
     * 
     * @param string $message 错误消息
     */
    public static function notFound(string $message = '资源不存在'): void
    {
        self::error($message, 404);
    }
    
    /**
     * 发送 401 未授权响应
     * 
     * @param string $message 错误消息
     */
    public static function unauthorized(string $message = '未授权'): void
    {
        self::error($message, 401);
    }
    
    /**
     * 发送 422 数据验证失败响应
     * 
     * @param string $message 错误消息
     * @param array $errors 验证错误详情
     */
    public static function validationError(string $message = '数据验证失败', array $errors = []): void
    {
        self::error($message, 422, $errors);
    }
    
    /**
     * 发送 500 服务器错误响应
     * 
     * @param string $message 错误消息
     */
    public static function serverError(string $message = '服务器内部错误'): void
    {
        self::error($message, 500);
    }
    
    /**
     * 设置 CORS 头
     * 
     * @param array $config CORS 配置
     */
    public static function setCorsHeaders(array $config = []): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowedOrigins = $config['cors_origins'] ?? ['*'];
        
        // 检查来源是否允许
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
        header('Access-Control-Max-Age: 86400');
        
        // 处理预检请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
