<?php
/**
 * 域名（区域）模型类
 * 
 * 处理 DNS 区域的增删改查操作
 */

namespace PowerDNS\Models;

use PowerDNS\Models\Database;

class Domain
{
    /**
     * 数据库实例
     */
    private $db;
    
    /**
     * 构造函数
     * 
     * @param Database $db 数据库实例
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * 获取所有域名
     * 
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM domains ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 根据名称获取域名
     * 
     * @param string $name 域名
     * @return array|null
     */
    public function getByName(string $name): ?array
    {
        // 尝试原始名称
        $sql = "SELECT * FROM domains WHERE name = :name";
        $result = $this->db->fetchOne($sql, [':name' => $name]);
        
        // 如果没找到，尝试添加尾部点
        if (!$result && substr($name, -1) !== '.') {
            $nameWithDot = $name . '.';
            $result = $this->db->fetchOne($sql, [':name' => $nameWithDot]);
        }
        
        // 如果还没找到且名称有点，尝试去掉点
        if (!$result && substr($name, -1) === '.') {
            $nameWithoutDot = rtrim($name, '.');
            $result = $this->db->fetchOne($sql, [':name' => $nameWithoutDot]);
        }
        
        return $result;
    }
    
    /**
     * 根据 ID 获取域名
     * 
     * @param int $id 域名 ID
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM domains WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }
    
    /**
     * 创建域名
     * 
     * @param array $data 域名数据
     * @return int 域名 ID
     */
    public function create(array $data): int
    {
        // 确保域名以点结尾
        if (isset($data['name']) && substr($data['name'], -1) !== '.') {
            $data['name'] .= '.';
        }
        
        // 设置默认值
        if (!isset($data['type'])) {
            $data['type'] = 'NATIVE';
        }
        
        return $this->db->insert('domains', $data);
    }
    
    /**
     * 更新域名
     * 
     * @param int $id 域名 ID
     * @param array $data 更新的数据
     * @return int 影响的行数
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('domains', $data, ['id' => $id]);
    }
    
    /**
     * 删除域名
     * 
     * @param int $id 域名 ID
     * @return int 影响的行数
     */
    public function delete(int $id): int
    {
        return $this->db->delete('domains', ['id' => $id]);
    }
    
    /**
     * 根据名称删除域名
     * 
     * @param string $name 域名
     * @return int 影响的行数
     */
    public function deleteByName(string $name): int
    {
        // 首先尝试找到域名
        $domain = $this->getByName($name);
        if (!$domain) {
            return 0;
        }
        
        return $this->db->delete('domains', ['name' => $domain['name']]);
    }
    
    /**
     * 检查域名是否存在
     * 
     * @param string $name 域名
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->getByName($name) !== null;
    }
    
    /**
     * 搜索域名
     * 
     * @param string $query 搜索关键词
     * @param int $limit 限制数量
     * @return array
     */
    public function search(string $query, int $limit = 100): array
    {
        $sql = "SELECT * FROM domains WHERE name LIKE :query ORDER BY name ASC LIMIT :limit";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 获取域名数量
     * 
     * @return int
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM domains";
        $result = $this->db->fetchOne($sql);
        return (int)$result['count'];
    }
    
    /**
     * 更新域名的序列号
     * 
     * @param int $domainId 域名 ID
     * @return bool
     */
    public function updateSerial(int $domainId): bool
    {
        // 获取当前 SOA 记录
        $sql = "SELECT * FROM records WHERE domain_id = :domain_id AND type = 'SOA' LIMIT 1";
        $soa = $this->db->fetchOne($sql, [':domain_id' => $domainId]);
        
        if (!$soa) {
            return false;
        }
        
        // 解析 SOA 记录
        $parts = preg_split('/\s+/', $soa['content']);
        if (count($parts) < 7) {
            return false;
        }
        
        // 更新序列号（格式：YYYYMMDDnn）
        $currentSerial = (int)$parts[2];
        $newSerial = $this->generateSerial($currentSerial);
        $parts[2] = (string)$newSerial;
        
        // 更新 SOA 记录
        $newContent = implode(' ', $parts);
        $updateSql = "UPDATE records SET content = :content WHERE id = :id";
        $this->db->query($updateSql, [
            ':content' => $newContent,
            ':id' => $soa['id']
        ]);
        
        return true;
    }
    
    /**
     * 生成新的序列号
     * 
     * @param int $currentSerial 当前序列号
     * @return int
     */
    private function generateSerial(int $currentSerial): int
    {
        $today = (int)date('Ymd00');
        
        if ($currentSerial < $today) {
            return $today;
        }
        
        return $currentSerial + 1;
    }
}
