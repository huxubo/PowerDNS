<?php
/**
 * PowerDNS API 配置文件示例
 * 
 * 复制此文件为 config.php 并修改相应配置
 */

return [
    // 数据库配置
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'powerdns',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true, // 使用持久连接
        ],
    ],
    
    // API 配置
    'api' => [
        // API 密钥（也可以存储在数据库中）
        'key' => 'powerdns-api-key-change-me',
        
        // API 版本
        'version' => 'v1',
        
        // 服务器 ID
        'server_id' => 'localhost',
        
        // 服务器类型
        'daemon_type' => 'authoritative',
        
        // API 版本号
        'server_version' => 'PHP-PowerDNS-API-1.0.0',
    ],
    
    // CNAME 展平配置
    'cname_flattening' => [
        // 是否启用 CNAME 展平
        'enabled' => true,
        
        // 最大跳转次数（防止循环引用）
        'max_hops' => 10,
        
        // 缓存 TTL（秒）
        'cache_ttl' => 300,
        
        // 是否使用数据库缓存
        'use_db_cache' => true,
        
        // 是否使用内存缓存
        'use_memory_cache' => true,
    ],
    
    // 日志配置
    'logging' => [
        // 是否启用日志
        'enabled' => true,
        
        // 日志文件路径
        'file' => __DIR__ . '/../logs/api.log',
        
        // 日志级别：debug, info, warning, error
        'level' => 'info',
        
        // 日志格式
        'format' => '[%datetime%] %level%: %message% %context%',
    ],
    
    // 安全配置
    'security' => [
        // 是否检查 API Key
        'require_api_key' => true,
        
        // 允许的 IP 地址（空数组表示允许所有）
        'allowed_ips' => [],
        
        // 是否启用 CORS
        'cors_enabled' => true,
        
        // 允许的来源
        'cors_origins' => ['*'],
        
        // 速率限制（每分钟请求数）
        'rate_limit' => 60,
    ],
    
    // 分页配置
    'pagination' => [
        // 默认每页数量
        'default_per_page' => 50,
        
        // 最大每页数量
        'max_per_page' => 1000,
    ],
    
    // DNS 默认配置
    'dns' => [
        // 默认 TTL
        'default_ttl' => 3600,
        
        // 默认 SOA 记录
        'default_soa' => [
            'primary' => 'ns1.example.com.',
            'hostmaster' => 'hostmaster.example.com.',
            'refresh' => 3600,
            'retry' => 1800,
            'expire' => 604800,
            'minimum' => 86400,
        ],
        
        // 默认名称服务器
        'default_nameservers' => [
            'ns1.example.com.',
            'ns2.example.com.',
        ],
    ],
    
    // 调试模式
    'debug' => false,
];
