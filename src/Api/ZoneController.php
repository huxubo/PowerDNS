<?php
/**
 * 区域（域名）控制器
 * 
 * 处理 DNS 区域相关的 API 请求
 */

namespace PowerDNS\Api;

use PowerDNS\Models\Database;
use PowerDNS\Models\Domain;
use PowerDNS\Models\Record;
use PowerDNS\Utils\Response;

class ZoneController
{
    /**
     * 数据库实例
     */
    private $db;
    
    /**
     * 域名模型
     */
    private $domainModel;
    
    /**
     * 记录模型
     */
    private $recordModel;
    
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
        $this->domainModel = new Domain($db);
        $this->recordModel = new Record($db);
    }
    
    /**
     * 列出所有区域
     * 
     * GET /api/v1/servers/:server_id/zones
     */
    public function listZones(string $serverId): void
    {
        $limit = (int)($_GET['limit'] ?? $this->config['pagination']['default_per_page']);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $domains = $this->domainModel->getAll($limit, $offset);
        
        $zones = array_map(function($domain) use ($serverId) {
            return $this->formatZone($domain, $serverId, false);
        }, $domains);
        
        Response::success($zones);
    }
    
    /**
     * 创建区域
     * 
     * POST /api/v1/servers/:server_id/zones
     */
    public function createZone(string $serverId): void
    {
        $data = $this->getJsonInput();
        
        // 验证必需字段
        if (!isset($data['name'])) {
            Response::validationError('缺少必需字段: name');
        }
        
        $name = $data['name'];
        $kind = $data['kind'] ?? 'Native';
        $nameservers = $data['nameservers'] ?? $this->config['dns']['default_nameservers'];
        
        // 检查区域是否已存在
        if ($this->domainModel->exists($name)) {
            Response::error('区域已存在', 409);
        }
        
        // 开始事务
        $this->db->beginTransaction();
        
        try {
            // 创建域名
            $domainId = $this->domainModel->create([
                'name' => $name,
                'type' => strtoupper($kind),
            ]);
            
            // 创建 SOA 记录
            $this->createSoaRecord($domainId, $name, $data['soa'] ?? null);
            
            // 创建 NS 记录
            foreach ($nameservers as $ns) {
                $this->recordModel->create([
                    'domain_id' => $domainId,
                    'name' => $name,
                    'type' => 'NS',
                    'content' => $ns,
                    'ttl' => $data['ttl'] ?? $this->config['dns']['default_ttl'],
                ]);
            }
            
            // 如果提供了记录集，创建它们
            if (isset($data['rrsets'])) {
                $this->processRRsets($domainId, $data['rrsets']);
            }
            
            $this->db->commit();
            
            // 返回创建的区域
            $domain = $this->domainModel->getById($domainId);
            $zone = $this->formatZone($domain, $serverId, true);
            
            Response::success($zone, 201);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('创建区域失败: ' . $e->getMessage());
            Response::serverError('创建区域失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取区域详情
     * 
     * GET /api/v1/servers/:server_id/zones/:zone_id
     */
    public function getZone(string $serverId, string $zoneId): void
    {
        $domain = $this->domainModel->getByName($zoneId);
        
        if (!$domain) {
            Response::notFound('区域不存在');
        }
        
        $zone = $this->formatZone($domain, $serverId, true);
        
        Response::success($zone);
    }
    
    /**
     * 更新区域（修改记录集）
     * 
     * PATCH /api/v1/servers/:server_id/zones/:zone_id
     */
    public function updateZone(string $serverId, string $zoneId): void
    {
        $domain = $this->domainModel->getByName($zoneId);
        
        if (!$domain) {
            Response::notFound('区域不存在');
        }
        
        $data = $this->getJsonInput();
        
        if (!isset($data['rrsets'])) {
            Response::validationError('缺少必需字段: rrsets');
        }
        
        // 开始事务
        $this->db->beginTransaction();
        
        try {
            // 处理记录集
            $this->processRRsets($domain['id'], $data['rrsets']);
            
            // 更新序列号
            $this->domainModel->updateSerial($domain['id']);
            
            $this->db->commit();
            
            Response::noContent();
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('更新区域失败: ' . $e->getMessage());
            Response::serverError('更新区域失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除区域
     * 
     * DELETE /api/v1/servers/:server_id/zones/:zone_id
     */
    public function deleteZone(string $serverId, string $zoneId): void
    {
        $domain = $this->domainModel->getByName($zoneId);
        
        if (!$domain) {
            Response::notFound('区域不存在');
        }
        
        // 删除域名（记录会被级联删除）
        $this->domainModel->delete($domain['id']);
        
        Response::noContent();
    }
    
    /**
     * 处理记录集
     * 
     * @param int $domainId 域名 ID
     * @param array $rrsets 记录集数组
     */
    private function processRRsets(int $domainId, array $rrsets): void
    {
        foreach ($rrsets as $rrset) {
            $name = $rrset['name'];
            $type = $rrset['type'];
            $changetype = strtoupper($rrset['changetype'] ?? 'REPLACE');
            
            // 确保名称以点结尾
            if (substr($name, -1) !== '.') {
                $name .= '.';
            }
            
            switch ($changetype) {
                case 'DELETE':
                    // 删除记录
                    $this->recordModel->deleteByNameAndType($domainId, $name, $type);
                    break;
                    
                case 'REPLACE':
                    // 先删除旧记录
                    $this->recordModel->deleteByNameAndType($domainId, $name, $type);
                    
                    // 然后添加新记录
                    if (isset($rrset['records'])) {
                        foreach ($rrset['records'] as $record) {
                            $this->recordModel->create([
                                'domain_id' => $domainId,
                                'name' => $name,
                                'type' => $type,
                                'content' => $record['content'],
                                'ttl' => $rrset['ttl'] ?? $this->config['dns']['default_ttl'],
                                'prio' => $record['priority'] ?? null,
                                'disabled' => $record['disabled'] ?? 0,
                            ]);
                        }
                    }
                    break;
            }
        }
    }
    
    /**
     * 创建 SOA 记录
     * 
     * @param int $domainId 域名 ID
     * @param string $domainName 域名
     * @param array|null $soaData SOA 数据
     */
    private function createSoaRecord(int $domainId, string $domainName, ?array $soaData = null): void
    {
        $soa = $this->config['dns']['default_soa'];
        
        if ($soaData) {
            $soa = array_merge($soa, $soaData);
        }
        
        // 生成序列号
        $serial = (int)date('Ymd01');
        
        $content = sprintf(
            '%s %s %d %d %d %d %d',
            $soa['primary'],
            $soa['hostmaster'],
            $serial,
            $soa['refresh'],
            $soa['retry'],
            $soa['expire'],
            $soa['minimum']
        );
        
        $this->recordModel->create([
            'domain_id' => $domainId,
            'name' => $domainName,
            'type' => 'SOA',
            'content' => $content,
            'ttl' => $this->config['dns']['default_ttl'],
        ]);
    }
    
    /**
     * 格式化区域为 API 响应格式
     * 
     * @param array $domain 域名数据
     * @param string $serverId 服务器 ID
     * @param bool $includeRecords 是否包含记录
     * @return array
     */
    private function formatZone(array $domain, string $serverId, bool $includeRecords = false): array
    {
        $zone = [
            'id' => $domain['name'],
            'name' => $domain['name'],
            'type' => 'Zone',
            'kind' => $domain['type'],
            'serial' => 0,
            'notified_serial' => $domain['notified_serial'],
            'masters' => [],
            'dnssec' => false,
            'nsec3param' => '',
            'nsec3narrow' => false,
            'presigned' => false,
            'soa_edit' => '',
            'soa_edit_api' => '',
            'api_rectify' => false,
            'account' => $domain['account'] ?? '',
            'url' => "/api/{$this->config['api']['version']}/servers/{$serverId}/zones/{$domain['name']}",
        ];
        
        if ($includeRecords) {
            $records = $this->recordModel->getByDomainId($domain['id']);
            
            // CNAME 展平不在 API 中处理
            // 直接返回原始记录，包括顶端的 CNAME 记录
            
            // 格式化记录为 RRsets
            $zone['rrsets'] = $this->formatRecordsAsRRsets($records);
            
            // 提取序列号
            foreach ($records as $record) {
                if ($record['type'] === 'SOA') {
                    $parts = preg_split('/\s+/', $record['content']);
                    if (count($parts) >= 3) {
                        $zone['serial'] = (int)$parts[2];
                    }
                    break;
                }
            }
        }
        
        return $zone;
    }
    
    /**
     * 将记录格式化为 RRsets
     * 
     * @param array $records 记录数组
     * @return array
     */
    private function formatRecordsAsRRsets(array $records): array
    {
        $rrsets = [];
        $grouped = [];
        
        // 按名称和类型分组
        foreach ($records as $record) {
            $key = $record['name'] . '|' . $record['type'];
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $record['name'],
                    'type' => $record['type'],
                    'ttl' => $record['ttl'],
                    'records' => [],
                ];
            }
            
            $grouped[$key]['records'][] = [
                'content' => $record['content'],
                'disabled' => (bool)$record['disabled'],
            ];
        }
        
        return array_values($grouped);
    }
    
    /**
     * 获取 JSON 输入
     * 
     * @return array
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::validationError('无效的 JSON 数据');
        }
        
        return $data ?? [];
    }
}
