<?php
/**
 * PowerDNS API 测试配置示例
 * 
 * 复制此文件为 test-config.php 并修改相应配置
 * 用于测试脚本的配置管理
 */

return [
    // API 基础配置
    'api' => [
        // API 基础 URL
        'base_url' => 'http://localhost/powerdns-api',
        
        // API 密钥（必须与 config/config.php 中的密钥一致）
        'api_key' => 'powerdns-api-key-change-me',
        
        // 服务器 ID（通常为 localhost）
        'server_id' => 'localhost',
        
        // API 版本
        'version' => 'v1',
    ],
    
    // 测试数据配置
    'test_data' => [
        // 测试用域名
        'test_domain' => 'test-example.com',
        
        // 测试用记录
        'test_records' => [
            'a' => [
                'name' => 'www',
                'content' => '192.168.1.100',
                'ttl' => 3600,
            ],
            'aaaa' => [
                'name' => 'ipv6',
                'content' => '2001:db8::1',
                'ttl' => 3600,
            ],
            'cname' => [
                'name' => 'mail',
                'content' => 'mail.example.com.',
                'ttl' => 3600,
            ],
            'mx' => [
                'name' => '@',
                'content' => '10 mail.test-example.com.',
                'ttl' => 3600,
            ],
            'txt' => [
                'name' => '_dmarc',
                'content' => '"v=DMARC1; p=none;"',
                'ttl' => 3600,
            ],
        ],
        
        // 测试用名称服务器
        'nameservers' => [
            'ns1.test-example.com.',
            'ns2.test-example.com.',
        ],
    ],
    
    // 性能测试配置
    'performance' => [
        // 并发请求数
        'concurrent_requests' => 10,
        
        // 总请求数
        'total_requests' => 100,
        
        // 请求间隔（毫秒）
        'request_interval' => 100,
    ],
    
    // 搜索测试配置
    'search' => [
        // 搜索关键词
        'keywords' => ['test', 'example', 'www', 'mail'],
        
        // 最大结果数
        'max_results' => 50,
    ],
    
    // 日志配置
    'logging' => [
        // 是否启用详细日志
        'verbose' => true,
        
        // 是否保存响应到文件
        'save_responses' => false,
        
        // 日志文件路径
        'log_file' => __DIR__ . '/logs/test.log',
    ],
    
    // 清理配置
    'cleanup' => [
        // 测试完成后是否自动清理
        'auto_cleanup' => true,
        
        // 是否清理失败
        'cleanup_on_failure' => false,
    ],
    
    // 错误处理配置
    'error_handling' => [
        // 遇到错误时是否继续测试
        'continue_on_error' => true,
        
        // 最大重试次数
        'max_retries' => 3,
        
        // 重试间隔（秒）
        'retry_delay' => 1,
    ],
    
    // 输出配置
    'output' => [
        // 输出格式：text, json, html
        'format' => 'text',
        
        // 是否显示颜色
        'colors' => true,
        
        // 是否显示进度条
        'progress_bar' => true,
        
        // 详细程度：quiet, normal, verbose
        'verbosity' => 'normal',
    ],
];