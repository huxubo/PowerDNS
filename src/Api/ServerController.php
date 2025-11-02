<?php
/**
 * 服务器控制器
 * 
 * 处理服务器相关的 API 请求
 */

namespace PowerDNS\Api;

use PowerDNS\Models\Database;
use PowerDNS\Utils\Response;

class ServerController
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
     * 列出所有服务器
     * 
     * GET /api/v1/servers
     */
    public function listServers(): void
    {
        $serverId = $this->config['api']['server_id'];
        
        $servers = [
            [
                'id' => $serverId,
                'type' => 'Server',
                'version' => $this->config['api']['server_version'],
                'daemon_type' => $this->config['api']['daemon_type'],
                'url' => "/api/{$this->config['api']['version']}/servers/{$serverId}",
                'config_url' => "/api/{$this->config['api']['version']}/servers/{$serverId}/config",
                'zones_url' => "/api/{$this->config['api']['version']}/servers/{$serverId}/zones",
            ]
        ];
        
        Response::success($servers);
    }
    
    /**
     * 获取服务器信息
     * 
     * GET /api/v1/servers/:server_id
     */
    public function getServer(string $serverId): void
    {
        $this->validateServerId($serverId);
        
        $server = [
            'id' => $serverId,
            'type' => 'Server',
            'version' => $this->config['api']['server_version'],
            'daemon_type' => $this->config['api']['daemon_type'],
            'url' => "/api/{$this->config['api']['version']}/servers/{$serverId}",
            'config_url' => "/api/{$this->config['api']['version']}/servers/{$serverId}/config",
            'zones_url' => "/api/{$this->config['api']['version']}/servers/{$serverId}/zones",
        ];
        
        Response::success($server);
    }
    
    /**
     * 获取服务器统计信息
     * 
     * GET /api/v1/servers/:server_id/statistics
     */
    public function getStatistics(string $serverId): void
    {
        $this->validateServerId($serverId);
        
        // 获取域名数量
        $domainCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM domains");
        
        // 获取记录数量
        $recordCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM records");
        
        // 构建统计信息
        $statistics = [
            [
                'name' => 'uptime',
                'type' => 'StatisticItem',
                'value' => (string)$this->getUptime(),
            ],
            [
                'name' => 'zones',
                'type' => 'StatisticItem',
                'value' => (string)$domainCount['count'],
            ],
            [
                'name' => 'records',
                'type' => 'StatisticItem',
                'value' => (string)$recordCount['count'],
            ],
            [
                'name' => 'queries',
                'type' => 'StatisticItem',
                'value' => '0', // 该实现不跟踪查询
            ],
        ];
        
        Response::success($statistics);
    }
    
    /**
     * 获取服务器配置
     * 
     * GET /api/v1/servers/:server_id/config
     */
    public function getConfig(string $serverId): void
    {
        $this->validateServerId($serverId);
        
        // 返回部分配置信息（隐藏敏感信息）
        $config = [
            [
                'name' => 'version',
                'type' => 'ConfigSetting',
                'value' => $this->config['api']['server_version'],
            ],
            [
                'name' => 'daemon-type',
                'type' => 'ConfigSetting',
                'value' => $this->config['api']['daemon_type'],
            ],
            [
                'name' => 'default-ttl',
                'type' => 'ConfigSetting',
                'value' => (string)$this->config['dns']['default_ttl'],
            ],
            [
                'name' => 'cname-flattening',
                'type' => 'ConfigSetting',
                'value' => $this->config['cname_flattening']['enabled'] ? 'yes' : 'no',
            ],
        ];
        
        Response::success($config);
    }
    
    /**
     * 搜索数据
     * 
     * GET /api/v1/servers/:server_id/search-data?q=:query&max=:max
     */
    public function search(string $serverId, string $query, int $max = 100): void
    {
        $this->validateServerId($serverId);
        
        // 限制最大数量
        $max = min($max, $this->config['pagination']['max_per_page']);
        
        $results = [];
        
        // 搜索域名
        $domainSql = "SELECT id, name, type FROM domains WHERE name LIKE :query LIMIT :limit";
        $stmt = $this->db->getConnection()->prepare($domainSql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $max, \PDO::PARAM_INT);
        $stmt->execute();
        $domains = $stmt->fetchAll();
        
        foreach ($domains as $domain) {
            $results[] = [
                'object_type' => 'zone',
                'name' => $domain['name'],
                'zone_id' => $domain['name'],
                'type' => $domain['type'],
            ];
        }
        
        // 搜索记录
        $recordSql = "SELECT r.*, d.name as domain_name 
                      FROM records r 
                      LEFT JOIN domains d ON r.domain_id = d.id 
                      WHERE r.name LIKE :query OR r.content LIKE :query 
                      LIMIT :limit";
        $stmt = $this->db->getConnection()->prepare($recordSql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $max, \PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll();
        
        foreach ($records as $record) {
            $results[] = [
                'object_type' => 'record',
                'name' => $record['name'],
                'type' => $record['type'],
                'content' => $record['content'],
                'zone_id' => $record['domain_name'],
                'zone' => $record['domain_name'],
            ];
        }
        
        Response::success($results);
    }
    
    /**
     * 清除缓存
     * 
     * PUT /api/v1/servers/:server_id/cache/flush?domain=:domain
     */
    public function flushCache(string $serverId, ?string $domain = null): void
    {
        $this->validateServerId($serverId);
        
        // 清除 CNAME 展平缓存
        if ($this->config['cname_flattening']['enabled']) {
            $cnameFlatteningService = new \PowerDNS\Services\CnameFlatteningService(
                $this->db,
                $this->config
            );
            
            if ($domain) {
                $cnameFlatteningService->clearCache($domain);
            } else {
                $cnameFlatteningService->clearCache();
            }
        }
        
        $result = [
            'count' => 1,
            'result' => 'Flushed cache.',
        ];
        
        Response::success($result);
    }
    
    /**
     * 验证服务器 ID
     * 
     * @param string $serverId 服务器 ID
     */
    private function validateServerId(string $serverId): void
    {
        if ($serverId !== $this->config['api']['server_id']) {
            Response::notFound('服务器不存在');
        }
    }
    
    /**
     * 获取运行时间（秒）
     * 
     * @return int
     */
    private function getUptime(): int
    {
        // 简单实现：返回当前时间戳（实际应用中应该记录启动时间）
        return time();
    }
}
