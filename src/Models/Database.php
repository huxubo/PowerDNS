<?php
/**
 * 数据库连接类
 * 
 * 提供单例模式的数据库连接管理
 */

namespace PowerDNS\Models;

use PDO;
use PDOException;

class Database
{
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * PDO 连接对象
     */
    private $connection = null;
    
    /**
     * 配置数组
     */
    private $config = [];
    
    /**
     * 私有构造函数（单例模式）
     * 
     * @param array $config 数据库配置
     */
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * 获取数据库实例
     * 
     * @param array $config 数据库配置
     * @return Database
     */
    public static function getInstance(array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \Exception('数据库配置不能为空');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    /**
     * 建立数据库连接
     * 
     * @throws PDOException
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            error_log('数据库连接失败: ' . $e->getMessage());
            throw new PDOException('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取 PDO 连接对象
     * 
     * @return PDO
     */
    public function getConnection(): PDO
    {
        // 检查连接是否存活
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            // 重新连接
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * 执行查询
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * 查询单行数据
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }
    
    /**
     * 查询多行数据
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 插入数据
     * 
     * @param string $table 表名
     * @param array $data 数据
     * @return int 插入的 ID
     */
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(function ($field) {
            return ':' . $field;
        }, $fields);
        
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $fields),
            implode(', ', $placeholders)
        );
        
        $params = [];
        foreach ($data as $field => $value) {
            $params[':' . $field] = $value;
        }
        
        $this->query($sql, $params);
        return (int)$this->connection->lastInsertId();
    }
    
    /**
     * 更新数据
     * 
     * @param string $table 表名
     * @param array $data 数据
     * @param array $where 条件
     * @return int 影响的行数
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClause = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $setClause[] = "`{$field}` = :set_{$field}";
            $params[":set_{$field}"] = $value;
        }
        
        $whereClause = [];
        foreach ($where as $field => $value) {
            $whereClause[] = "`{$field}` = :where_{$field}";
            $params[":where_{$field}"] = $value;
        }
        
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClause),
            implode(' AND ', $whereClause)
        );
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     * 
     * @param string $table 表名
     * @param array $where 条件
     * @return int 影响的行数
     */
    public function delete(string $table, array $where): int
    {
        $whereClause = [];
        $params = [];
        
        foreach ($where as $field => $value) {
            $whereClause[] = "`{$field}` = :{$field}";
            $params[":{$field}"] = $value;
        }
        
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $whereClause)
        );
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }
    
    /**
     * 提交事务
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }
    
    /**
     * 回滚事务
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }
    
    /**
     * 获取最后插入的 ID
     * 
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止反序列化
     */
    public function __wakeup()
    {
        throw new \Exception("不能反序列化单例对象");
    }
}
