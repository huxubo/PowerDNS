#!/usr/bin/env php
<?php
/**
 * PowerDNS API 部署检查脚本
 * 
 * 用于检查部署环境是否满足运行要求
 * 
 * 使用方法：
 * php check-deployment.php
 * 或在浏览器中访问：http://your-domain/check-deployment.php
 */

// 设置为命令行模式还是 Web 模式
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

class DeploymentChecker
{
    private $checks = [];
    private $errors = 0;
    private $warnings = 0;
    private $isCli = false;

    public function __construct($isCli = false)
    {
        $this->isCli = $isCli;
    }

    public function check()
    {
        $this->output("=== PowerDNS API 部署环境检查 ===\n\n", 'header');

        $this->checkPhpVersion();
        $this->checkPhpExtensions();
        $this->checkDirectories();
        $this->checkFiles();
        $this->checkPermissions();
        $this->checkConfig();
        $this->checkDatabase();
        $this->checkWebServer();

        $this->outputSummary();
    }

    private function checkPhpVersion()
    {
        $this->output("检查 PHP 版本...\n", 'section');
        
        $version = PHP_VERSION;
        $required = '7.4.0';
        
        if (version_compare($version, $required, '>=')) {
            $this->success("PHP 版本: {$version} ✓");
        } else {
            $this->error("PHP 版本过低: {$version}，需要 >= {$required}");
        }
    }

    private function checkPhpExtensions()
    {
        $this->output("\n检查 PHP 扩展...\n", 'section');
        
        $required = [
            'pdo' => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'json' => 'JSON',
            'mbstring' => 'Mbstring',
        ];

        $recommended = [
            'opcache' => 'OPcache (性能优化)',
        ];

        foreach ($required as $ext => $name) {
            if (extension_loaded($ext)) {
                $this->success("{$name} ✓");
            } else {
                $this->error("{$name} 未安装 (必需)");
            }
        }

        foreach ($recommended as $ext => $name) {
            if (extension_loaded($ext)) {
                $this->success("{$name} ✓");
            } else {
                $this->warning("{$name} 未安装 (推荐)");
            }
        }
    }

    private function checkDirectories()
    {
        $this->output("\n检查目录结构...\n", 'section');
        
        $dirs = [
            'config' => '配置目录',
            'database' => '数据库目录',
            'src' => '源代码目录',
            'src/api' => 'API 控制器目录',
            'src/models' => '模型目录',
            'src/utils' => '工具类目录',
        ];

        foreach ($dirs as $dir => $name) {
            $path = __DIR__ . '/' . $dir;
            if (is_dir($path)) {
                $this->success("{$name} ({$dir}) ✓");
            } else {
                $this->error("{$name} ({$dir}) 不存在");
            }
        }

        // 检查日志目录
        $logsDir = __DIR__ . '/logs';
        if (is_dir($logsDir)) {
            $this->success("日志目录 (logs) ✓");
        } else {
            $this->warning("日志目录 (logs) 不存在，将自动创建");
            @mkdir($logsDir, 0755, true);
        }
    }

    private function checkFiles()
    {
        $this->output("\n检查关键文件...\n", 'section');
        
        $files = [
            'index.php' => 'API 入口文件',
            '.htaccess' => 'Apache 重写规则',
            'database/schema.sql' => '数据库架构文件',
            'README.md' => '项目说明',
            'BAOTA_DEPLOY.md' => '宝塔部署指南',
        ];

        foreach ($files as $file => $name) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $this->success("{$name} ({$file}) ✓");
            } else {
                $this->error("{$name} ({$file}) 不存在");
            }
        }
    }

    private function checkPermissions()
    {
        $this->output("\n检查文件权限...\n", 'section');
        
        $indexFile = __DIR__ . '/index.php';
        if (is_readable($indexFile)) {
            $this->success("index.php 可读 ✓");
        } else {
            $this->error("index.php 不可读");
        }

        $logsDir = __DIR__ . '/logs';
        if (is_writable($logsDir)) {
            $this->success("logs 目录可写 ✓");
        } else {
            $this->warning("logs 目录不可写，可能无法记录日志");
        }
    }

    private function checkConfig()
    {
        $this->output("\n检查配置文件...\n", 'section');
        
        $configFile = __DIR__ . '/config/config.php';
        $exampleFile = __DIR__ . '/config/config.example.php';

        if (!file_exists($configFile)) {
            $this->error("config/config.php 不存在");
            if (file_exists($exampleFile)) {
                $this->warning("请复制 config/config.example.php 为 config/config.php");
            }
            return;
        }

        $this->success("config/config.php 存在 ✓");

        // 检查配置内容
        try {
            $config = require $configFile;
            
            if (!is_array($config)) {
                $this->error("配置文件格式错误");
                return;
            }

            $this->success("配置文件格式正确 ✓");

            // 检查数据库配置
            if (isset($config['database'])) {
                $db = $config['database'];
                if (empty($db['host'])) {
                    $this->warning("数据库主机未配置");
                }
                if (empty($db['database'])) {
                    $this->warning("数据库名称未配置");
                }
                if (empty($db['username'])) {
                    $this->warning("数据库用户名未配置");
                }
            } else {
                $this->error("缺少数据库配置");
            }

            // 检查 API Key
            if (isset($config['api']['key'])) {
                $apiKey = $config['api']['key'];
                if ($apiKey === 'powerdns-api-key-change-me') {
                    $this->warning("API Key 未修改，请设置一个强密码");
                } elseif (strlen($apiKey) < 32) {
                    $this->warning("API Key 过短，建议至少 32 个字符");
                } else {
                    $this->success("API Key 已配置 ✓");
                }
            } else {
                $this->error("缺少 API Key 配置");
            }

        } catch (Exception $e) {
            $this->error("配置文件加载失败: " . $e->getMessage());
        }
    }

    private function checkDatabase()
    {
        $this->output("\n检查数据库连接...\n", 'section');
        
        $configFile = __DIR__ . '/config/config.php';
        if (!file_exists($configFile)) {
            $this->warning("跳过数据库检查（配置文件不存在）");
            return;
        }

        try {
            $config = require $configFile;
            $db = $config['database'];

            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $db['host'],
                $db['port'] ?? 3306,
                $db['database'],
                $db['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO(
                $dsn,
                $db['username'],
                $db['password'],
                $db['options'] ?? []
            );

            $this->success("数据库连接成功 ✓");

            // 检查表是否存在
            $tables = ['domains', 'records', 'domainmetadata'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $this->success("数据表 {$table} 存在 ✓");
                } else {
                    $this->warning("数据表 {$table} 不存在，请导入 database/schema.sql");
                }
            }

        } catch (PDOException $e) {
            $this->error("数据库连接失败: " . $e->getMessage());
        }
    }

    private function checkWebServer()
    {
        $this->output("\n检查 Web 服务器...\n", 'section');
        
        if (!$this->isCli) {
            // 检查文档根目录
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            $this->success("文档根目录: {$docRoot}");

            // 检查 URL 重写
            if (isset($_SERVER['REQUEST_URI'])) {
                $this->success("URL 重写可能已启用 ✓");
            }
        } else {
            $this->warning("命令行模式，跳过 Web 服务器检查");
        }
    }

    private function outputSummary()
    {
        $this->output("\n=== 检查结果汇总 ===\n", 'header');
        
        $total = count($this->checks);
        $success = $total - $this->errors - $this->warnings;

        $this->output("总计: {$total} 项\n");
        $this->output("✓ 成功: {$success} 项\n", 'success');
        
        if ($this->warnings > 0) {
            $this->output("⚠ 警告: {$this->warnings} 项\n", 'warning');
        }
        
        if ($this->errors > 0) {
            $this->output("✗ 错误: {$this->errors} 项\n", 'error');
        }

        $this->output("\n");

        if ($this->errors === 0 && $this->warnings === 0) {
            $this->output("🎉 恭喜！所有检查都已通过，可以开始使用 API 了！\n", 'success');
        } elseif ($this->errors === 0) {
            $this->output("⚠️ 基本检查通过，但有一些警告项需要注意。\n", 'warning');
        } else {
            $this->output("❌ 发现错误，请修复后再使用 API。\n", 'error');
            $this->output("\n详细部署指南请查看: BAOTA_DEPLOY.md\n");
        }
    }

    private function success($message)
    {
        $this->checks[] = ['type' => 'success', 'message' => $message];
        $this->output($message . "\n", 'success');
    }

    private function warning($message)
    {
        $this->checks[] = ['type' => 'warning', 'message' => $message];
        $this->warnings++;
        $this->output($message . "\n", 'warning');
    }

    private function error($message)
    {
        $this->checks[] = ['type' => 'error', 'message' => $message];
        $this->errors++;
        $this->output($message . "\n", 'error');
    }

    private function output($message, $type = 'normal')
    {
        if ($this->isCli) {
            // 命令行输出，带颜色
            $colors = [
                'header' => "\033[1;36m",
                'section' => "\033[1;33m",
                'success' => "\033[0;32m",
                'warning' => "\033[0;33m",
                'error' => "\033[0;31m",
                'normal' => "\033[0m",
            ];

            $color = $colors[$type] ?? $colors['normal'];
            $reset = $colors['normal'];
            echo $color . $message . $reset;
        } else {
            // Web 输出，带 HTML 样式
            static $headerPrinted = false;
            
            if (!$headerPrinted) {
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>部署检查</title>';
                echo '<style>
                    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
                    pre { background: #fff; padding: 20px; border-radius: 5px; line-height: 1.6; }
                    .header { color: #0066cc; font-weight: bold; font-size: 1.2em; }
                    .section { color: #ff9900; font-weight: bold; }
                    .success { color: #00aa00; }
                    .warning { color: #ff9900; }
                    .error { color: #cc0000; }
                </style></head><body><pre>';
                $headerPrinted = true;
            }

            $class = $type !== 'normal' ? " class=\"{$type}\"" : '';
            echo "<span{$class}>" . htmlspecialchars($message) . "</span>";
        }
    }
}

// 运行检查
$checker = new DeploymentChecker($isCli);
$checker->check();

if (!$isCli) {
    echo '</pre></body></html>';
}
