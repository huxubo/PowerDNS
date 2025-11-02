<?php
/**
 * 认证工具类
 * 
 * 处理 API Key 认证
 */

namespace PowerDNS\Utils;

use PowerDNS\Models\Database;

class Auth
{
    /**
     * 数据库实例
     */
    private $db;
    
    /**
     * 配置
     */
    private $config;
    
    /**
     * 构造函数
     * 
     * @param Database $db 数据库实例
     * @param array $config 配置数组
     */
    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * 验证 API Key
     * 
     * @return bool
     */
    public function authenticate(): bool
    {
        // 如果不需要认证，直接返回 true
        if (!$this->config['security']['require_api_key']) {
            return true;
        }
        
        // 从请求头获取 API Key
        $apiKey = $this->getApiKeyFromRequest();
        
        if (!$apiKey) {
            return false;
        }
        
        // 验证 API Key
        return $this->validateApiKey($apiKey);
    }
    
    /**
     * 从请求中获取 API Key
     * 
     * @return string|null
     */
    private function getApiKeyFromRequest(): ?string
    {
        // 从 X-API-Key 头获取
        $headers = $this->getAllHeaders();
        if (isset($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }
        
        // 从 Authorization 头获取（Bearer token）
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        
        // 从查询参数获取
        if (isset($_GET['api_key'])) {
            return $_GET['api_key'];
        }
        
        return null;
    }
    
    /**
     * 获取所有 HTTP 头信息（兼容不同 PHP 环境）
     * 
     * @return array
     */
    private function getAllHeaders(): array
    {
        // 如果 getallheaders() 函数存在（Apache 环境）
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // 对于 nginx + php-fpm 或其他环境的备用方案
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                // 转换 HTTP_X_API_KEY 为 X-Api-Key
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                // 处理特殊的头信息
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * 验证 API Key
     * 
     * @param string $apiKey API Key
     * @return bool
     */
    private function validateApiKey(string $apiKey): bool
    {
        // 首先检查配置文件中的 API Key
        if ($apiKey === $this->config['api']['key']) {
            return true;
        }
        
        // 然后检查数据库中的 API Key
        try {
            $sql = "SELECT * FROM api_keys WHERE `key` = :key AND active = 1";
            $result = $this->db->fetchOne($sql, [':key' => $apiKey]);
            
            if ($result) {
                // 更新最后使用时间
                $this->updateLastUsed($result['id']);
                return true;
            }
        } catch (\Exception $e) {
            // 数据库查询失败，记录日志
            error_log('API Key 验证失败: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * 更新 API Key 的最后使用时间
     * 
     * @param int $keyId API Key ID
     */
    private function updateLastUsed(int $keyId): void
    {
        try {
            $sql = "UPDATE api_keys SET last_used_at = NOW() WHERE id = :id";
            $this->db->query($sql, [':id' => $keyId]);
        } catch (\Exception $e) {
            // 忽略更新失败
            error_log('更新 API Key 使用时间失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查 IP 白名单
     * 
     * @return bool
     */
    public function checkIpWhitelist(): bool
    {
        $allowedIps = $this->config['security']['allowed_ips'] ?? [];
        
        // 如果白名单为空，允许所有 IP
        if (empty($allowedIps)) {
            return true;
        }
        
        $clientIp = $this->getClientIp();
        
        return in_array($clientIp, $allowedIps);
    }
    
    /**
     * 获取客户端 IP 地址
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        return trim($ip);
    }
    
    /**
     * 创建新的 API Key
     * 
     * @param string $description 描述
     * @param array $permissions 权限
     * @return string 生成的 API Key
     */
    public function createApiKey(string $description, array $permissions = []): string
    {
        // 生成随机 API Key
        $apiKey = $this->generateApiKey();
        
        // 保存到数据库
        $this->db->insert('api_keys', [
            'key' => $apiKey,
            'description' => $description,
            'permissions' => json_encode($permissions),
            'active' => 1
        ]);
        
        return $apiKey;
    }
    
    /**
     * 生成随机 API Key
     * 
     * @return string
     */
    private function generateApiKey(): string
    {
        return 'pdns_' . bin2hex(random_bytes(32));
    }
    
    /**
     * 撤销 API Key
     * 
     * @param string $apiKey API Key
     * @return bool
     */
    public function revokeApiKey(string $apiKey): bool
    {
        try {
            $affected = $this->db->update('api_keys', ['active' => 0], ['key' => $apiKey]);
            return $affected > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
