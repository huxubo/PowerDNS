<?php
/**
 * DNS 记录模型类
 * 
 * 处理 DNS 记录的增删改查操作
 */

namespace PowerDNS\Models;

use PowerDNS\Models\Database;

class Record
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
     * 根据域名 ID 获取所有记录
     * 
     * @param int $domainId 域名 ID
     * @return array
     */
    public function getByDomainId(int $domainId): array
    {
        $sql = "SELECT * FROM records WHERE domain_id = :domain_id ORDER BY name, type";
        return $this->db->fetchAll($sql, [':domain_id' => $domainId]);
    }
    
    /**
     * 根据 ID 获取记录
     * 
     * @param int $id 记录 ID
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM records WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }
    
    /**
     * 根据名称和类型获取记录
     * 
     * @param int $domainId 域名 ID
     * @param string $name 记录名称
     * @param string $type 记录类型
     * @return array
     */
    public function getByNameAndType(int $domainId, string $name, string $type): array
    {
        // 确保名称以点结尾
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        
        $sql = "SELECT * FROM records WHERE domain_id = :domain_id AND name = :name AND type = :type";
        return $this->db->fetchAll($sql, [
            ':domain_id' => $domainId,
            ':name' => $name,
            ':type' => $type
        ]);
    }
    
    /**
     * 根据名称获取所有记录（所有类型）
     * 
     * @param int $domainId 域名 ID
     * @param string $name 记录名称
     * @return array
     */
    public function getByName(int $domainId, string $name): array
    {
        // 确保名称以点结尾
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        
        $sql = "SELECT * FROM records WHERE domain_id = :domain_id AND name = :name";
        return $this->db->fetchAll($sql, [
            ':domain_id' => $domainId,
            ':name' => $name
        ]);
    }
    
    /**
     * 创建记录
     * 
     * @param array $data 记录数据
     * @return int 记录 ID
     */
    public function create(array $data): int
    {
        // 确保名称以点结尾
        if (isset($data['name']) && substr($data['name'], -1) !== '.') {
            $data['name'] .= '.';
        }
        
        // 设置默认值
        if (!isset($data['ttl'])) {
            $data['ttl'] = 3600;
        }
        if (!isset($data['disabled'])) {
            $data['disabled'] = 0;
        }
        if (!isset($data['auth'])) {
            $data['auth'] = 1;
        }
        
        return $this->db->insert('records', $data);
    }
    
    /**
     * 更新记录
     * 
     * @param int $id 记录 ID
     * @param array $data 更新的数据
     * @return int 影响的行数
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('records', $data, ['id' => $id]);
    }
    
    /**
     * 删除记录
     * 
     * @param int $id 记录 ID
     * @return int 影响的行数
     */
    public function delete(int $id): int
    {
        return $this->db->delete('records', ['id' => $id]);
    }
    
    /**
     * 删除域名的所有指定类型记录
     * 
     * @param int $domainId 域名 ID
     * @param string $name 记录名称
     * @param string $type 记录类型
     * @return int 影响的行数
     */
    public function deleteByNameAndType(int $domainId, string $name, string $type): int
    {
        // 确保名称以点结尾
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        
        $sql = "DELETE FROM records WHERE domain_id = :domain_id AND name = :name AND type = :type";
        $stmt = $this->db->query($sql, [
            ':domain_id' => $domainId,
            ':name' => $name,
            ':type' => $type
        ]);
        
        return $stmt->rowCount();
    }
    
    /**
     * 批量创建记录
     * 
     * @param array $records 记录数组
     * @return array 创建的记录 ID 数组
     */
    public function createBatch(array $records): array
    {
        $ids = [];
        
        $this->db->beginTransaction();
        try {
            foreach ($records as $record) {
                $ids[] = $this->create($record);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        
        return $ids;
    }
    
    /**
     * 搜索记录
     * 
     * @param string $query 搜索关键词
     * @param int $limit 限制数量
     * @return array
     */
    public function search(string $query, int $limit = 100): array
    {
        $sql = "SELECT r.*, d.name as domain_name 
                FROM records r 
                LEFT JOIN domains d ON r.domain_id = d.id 
                WHERE r.name LIKE :query OR r.content LIKE :query 
                ORDER BY r.name ASC 
                LIMIT :limit";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 检查记录是否为根记录
     * 
     * @param string $recordName 记录名称
     * @param string $domainName 域名
     * @return bool
     */
    public function isApexRecord(string $recordName, string $domainName): bool
    {
        // 标准化名称
        $recordName = rtrim($recordName, '.') . '.';
        $domainName = rtrim($domainName, '.') . '.';
        
        return $recordName === $domainName;
    }
    
    /**
     * 获取 CNAME 目标记录
     * 
     * @param string $name CNAME 记录名称
     * @return array|null
     */
    public function getCnameTarget(string $name): ?array
    {
        // 确保名称以点结尾
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        
        $sql = "SELECT * FROM records WHERE name = :name AND type = 'CNAME' AND disabled = 0 LIMIT 1";
        return $this->db->fetchOne($sql, [':name' => $name]);
    }
    
    /**
     * 根据名称获取 A/AAAA 记录
     * 
     * @param string $name 记录名称
     * @param string $type 记录类型（A 或 AAAA）
     * @return array
     */
    public function getAddressRecords(string $name, string $type = 'A'): array
    {
        // 确保名称以点结尾
        if (substr($name, -1) !== '.') {
            $name .= '.';
        }
        
        $sql = "SELECT * FROM records WHERE name = :name AND type = :type AND disabled = 0";
        return $this->db->fetchAll($sql, [
            ':name' => $name,
            ':type' => $type
        ]);
    }
    
    /**
     * 获取记录数量
     * 
     * @param int $domainId 域名 ID
     * @return int
     */
    public function countByDomainId(int $domainId): int
    {
        $sql = "SELECT COUNT(*) as count FROM records WHERE domain_id = :domain_id";
        $result = $this->db->fetchOne($sql, [':domain_id' => $domainId]);
        return (int)$result['count'];
    }
}
