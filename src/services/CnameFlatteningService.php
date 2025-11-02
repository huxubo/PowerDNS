<?php
/**
 * CNAME 展平服务
 * 
 * 实现根记录 (@) 的 CNAME 展平功能
 * 当根记录使用 CNAME 时，自动追踪到最终的 A/AAAA 记录
 */

namespace PowerDNS\Services;

use PowerDNS\Models\Database;
use PowerDNS\Models\Record;

class CnameFlatteningService
{
    /**
     * 数据库实例
     */
    private $db;
    
    /**
     * 记录模型
     */
    private $recordModel;
    
    /**
     * 配置
     */
    private $config;
    
    /**
     * 内存缓存
     */
    private $memoryCache = [];
    
    /**
     * 构造函数
     * 
     * @param Database $db 数据库实例
     * @param array $config 配置数组
     */
    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->recordModel = new Record($db);
        $this->config = $config;
    }
    
    /**
     * 处理 CNAME 展平
     * 
     * @param array $records 原始记录数组
     * @param string $domainName 域名
     * @return array 处理后的记录数组
     */
    public function flatten(array $records, string $domainName): array
    {
        // 检查是否启用 CNAME 展平
        if (!$this->config['cname_flattening']['enabled']) {
            return $records;
        }
        
        $flattenedRecords = [];
        
        foreach ($records as $record) {
            // 只处理根记录的 CNAME
            if ($record['type'] === 'CNAME' && $this->isApexRecord($record['name'], $domainName)) {
                // 展平 CNAME
                $resolved = $this->resolveCname($record['name'], $record['content']);
                
                if (!empty($resolved)) {
                    // 添加展平后的记录
                    $flattenedRecords = array_merge($flattenedRecords, $resolved);
                    
                    // 记录日志
                    $this->logFlattening($record['name'], $record['content'], $resolved);
                } else {
                    // 无法解析，保留原记录
                    $flattenedRecords[] = $record;
                }
            } else {
                // 非根记录 CNAME 或其他类型记录，直接添加
                $flattenedRecords[] = $record;
            }
        }
        
        return $flattenedRecords;
    }
    
    /**
     * 解析 CNAME 链
     * 
     * @param string $sourceName 源记录名称
     * @param string $targetName 目标记录名称
     * @param int $depth 当前深度（防止循环引用）
     * @param array $visited 已访问的记录（防止循环引用）
     * @return array 解析后的 A/AAAA 记录
     */
    private function resolveCname(string $sourceName, string $targetName, int $depth = 0, array $visited = []): array
    {
        // 检查深度限制
        $maxHops = $this->config['cname_flattening']['max_hops'] ?? 10;
        if ($depth >= $maxHops) {
            error_log("CNAME 展平: 超过最大跳转次数 ({$maxHops})，源: {$sourceName}");
            return [];
        }
        
        // 检查循环引用
        if (in_array($targetName, $visited)) {
            error_log("CNAME 展平: 检测到循环引用，源: {$sourceName}，目标: {$targetName}");
            return [];
        }
        
        $visited[] = $targetName;
        
        // 标准化目标名称
        $targetName = $this->normalizeName($targetName);
        
        // 检查缓存
        $cached = $this->getFromCache($targetName);
        if ($cached !== null) {
            return $cached;
        }
        
        // 首先尝试获取 A 记录
        $aRecords = $this->recordModel->getAddressRecords($targetName, 'A');
        
        // 然后尝试获取 AAAA 记录
        $aaaaRecords = $this->recordModel->getAddressRecords($targetName, 'AAAA');
        
        // 如果找到 A 或 AAAA 记录，返回它们
        if (!empty($aRecords) || !empty($aaaaRecords)) {
            $result = array_merge($aRecords, $aaaaRecords);
            
            // 将这些记录转换为根记录
            $result = array_map(function($record) use ($sourceName) {
                $record['name'] = $sourceName;
                $record['flattened'] = true; // 标记为展平记录
                return $record;
            }, $result);
            
            // 保存到缓存
            $this->saveToCache($targetName, $result);
            
            return $result;
        }
        
        // 检查目标是否也是 CNAME
        $cnameRecord = $this->recordModel->getCnameTarget($targetName);
        
        if ($cnameRecord) {
            // 递归解析
            return $this->resolveCname($sourceName, $cnameRecord['content'], $depth + 1, $visited);
        }
        
        // 无法解析
        return [];
    }
    
    /**
     * 检查是否为根记录
     * 
     * @param string $recordName 记录名称
     * @param string $domainName 域名
     * @return bool
     */
    private function isApexRecord(string $recordName, string $domainName): bool
    {
        $recordName = $this->normalizeName($recordName);
        $domainName = $this->normalizeName($domainName);
        
        return $recordName === $domainName;
    }
    
    /**
     * 标准化域名（确保以点结尾）
     * 
     * @param string $name 域名
     * @return string
     */
    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        return strtolower($name);
    }
    
    /**
     * 从缓存获取记录
     * 
     * @param string $name 记录名称
     * @return array|null
     */
    private function getFromCache(string $name): ?array
    {
        // 首先检查内存缓存
        if ($this->config['cname_flattening']['use_memory_cache'] && isset($this->memoryCache[$name])) {
            return $this->memoryCache[$name];
        }
        
        // 然后检查数据库缓存
        if ($this->config['cname_flattening']['use_db_cache']) {
            try {
                $sql = "SELECT * FROM cname_flatten_cache 
                        WHERE source_name = :name AND expires_at > NOW()";
                $cached = $this->db->fetchOne($sql, [':name' => $name]);
                
                if ($cached) {
                    $records = json_decode($cached['target_content'], true);
                    
                    // 保存到内存缓存
                    if ($this->config['cname_flattening']['use_memory_cache']) {
                        $this->memoryCache[$name] = $records;
                    }
                    
                    return $records;
                }
            } catch (\Exception $e) {
                error_log('CNAME 展平缓存读取失败: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * 保存记录到缓存
     * 
     * @param string $name 记录名称
     * @param array $records 记录数组
     */
    private function saveToCache(string $name, array $records): void
    {
        // 保存到内存缓存
        if ($this->config['cname_flattening']['use_memory_cache']) {
            $this->memoryCache[$name] = $records;
        }
        
        // 保存到数据库缓存
        if ($this->config['cname_flattening']['use_db_cache']) {
            try {
                $cacheTtl = $this->config['cname_flattening']['cache_ttl'] ?? 300;
                $expiresAt = date('Y-m-d H:i:s', time() + $cacheTtl);
                
                // 查找最小 TTL
                $minTtl = $this->findMinTtl($records);
                
                // 删除旧缓存
                $this->db->delete('cname_flatten_cache', ['source_name' => $name]);
                
                // 插入新缓存
                $this->db->insert('cname_flatten_cache', [
                    'source_name' => $name,
                    'target_type' => 'A/AAAA',
                    'target_content' => json_encode($records),
                    'ttl' => $minTtl,
                    'expires_at' => $expiresAt
                ]);
            } catch (\Exception $e) {
                error_log('CNAME 展平缓存保存失败: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * 查找记录中的最小 TTL
     * 
     * @param array $records 记录数组
     * @return int
     */
    private function findMinTtl(array $records): int
    {
        $minTtl = 3600; // 默认值
        
        foreach ($records as $record) {
            if (isset($record['ttl']) && $record['ttl'] < $minTtl) {
                $minTtl = $record['ttl'];
            }
        }
        
        return $minTtl;
    }
    
    /**
     * 清除缓存
     * 
     * @param string|null $name 记录名称（null 表示清除所有）
     */
    public function clearCache(?string $name = null): void
    {
        // 清除内存缓存
        if ($name === null) {
            $this->memoryCache = [];
        } else {
            unset($this->memoryCache[$name]);
        }
        
        // 清除数据库缓存
        try {
            if ($name === null) {
                $this->db->query("DELETE FROM cname_flatten_cache");
            } else {
                $this->db->delete('cname_flatten_cache', ['source_name' => $name]);
            }
        } catch (\Exception $e) {
            error_log('CNAME 展平缓存清除失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 清除过期缓存
     */
    public function clearExpiredCache(): void
    {
        try {
            $this->db->query("DELETE FROM cname_flatten_cache WHERE expires_at < NOW()");
        } catch (\Exception $e) {
            error_log('CNAME 展平过期缓存清除失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 记录展平操作日志
     * 
     * @param string $sourceName 源记录名称
     * @param string $targetName 目标记录名称
     * @param array $resolved 解析结果
     */
    private function logFlattening(string $sourceName, string $targetName, array $resolved): void
    {
        if ($this->config['logging']['enabled']) {
            $message = sprintf(
                'CNAME 展平: %s -> %s，解析出 %d 条记录',
                $sourceName,
                $targetName,
                count($resolved)
            );
            error_log($message);
        }
    }
}
