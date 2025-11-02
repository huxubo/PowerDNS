<?php
/**
 * 部署引导页面
 * 
 * 如果您看到此页面，说明宝塔面板的运行目录配置不正确
 */

header('Content-Type: text/html; charset=utf-8');
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerDNS API - 部署配置提示</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .alert-error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .alert h3 {
            color: #856404;
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .alert-error h3 {
            color: #721c24;
        }
        
        .alert p {
            color: #856404;
            line-height: 1.6;
        }
        
        .alert-error p {
            color: #721c24;
        }
        
        .steps {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .steps h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .steps h2:before {
            content: "🔧";
            margin-right: 10px;
            font-size: 24px;
        }
        
        .step {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid #667eea;
        }
        
        .step:last-child {
            margin-bottom: 0;
        }
        
        .step h4 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .step p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .step code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
            color: #d63384;
            font-size: 13px;
        }
        
        .highlight {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .highlight strong {
            color: #0c5460;
            font-size: 16px;
        }
        
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            margin-top: 20px;
            border-radius: 4px;
        }
        
        .info-box p {
            color: #0c5460;
            margin: 5px 0;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 600px) {
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ PowerDNS API - 部署配置提示</h1>
            <p>需要正确配置宝塔面板的运行目录</p>
        </div>
        
        <div class="content">
            <div class="alert alert-error">
                <h3>❌ 运行目录配置错误</h3>
                <p>当前访问的是项目根目录的 index.php 文件，这说明您的宝塔面板<strong>运行目录配置不正确</strong>。</p>
                <p>正确的入口文件应该是 <code>public/index.php</code>，而不是根目录的 index.php。</p>
            </div>
            
            <div class="steps">
                <h2>快速修复步骤</h2>
                
                <div class="step">
                    <h4>步骤 1：打开网站设置</h4>
                    <p>在宝塔面板中，找到您的网站，点击<strong>「设置」</strong>按钮</p>
                </div>
                
                <div class="step">
                    <h4>步骤 2：修改网站目录</h4>
                    <p>在设置页面中，点击<strong>「网站目录」</strong>选项卡</p>
                </div>
                
                <div class="step">
                    <h4>步骤 3：设置运行目录</h4>
                    <p>找到<strong>「运行目录」</strong>设置项，在下拉框中选择 <code>/public</code> 或手动输入 <code>public</code></p>
                </div>
                
                <div class="step">
                    <h4>步骤 4：保存并刷新</h4>
                    <p>点击<strong>「保存」</strong>按钮，然后刷新此页面</p>
                </div>
            </div>
            
            <div class="highlight">
                <strong>💡 提示：</strong> 设置运行目录后，网站的根目录将自动指向 <code>项目路径/public</code> 目录，这样才能正确访问 API。
            </div>
            
            <div class="info-box">
                <p><strong>📚 更多部署信息：</strong></p>
                <p>• 查看项目中的 <code>BAOTA_DEPLOY.md</code> 文件获取完整部署指南</p>
                <p>• 查看 <code>README.md</code> 了解项目功能和 API 使用说明</p>
                <p>• 查看 <code>INSTALL.md</code> 了解其他部署方式</p>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="javascript:location.reload();" class="btn">🔄 刷新页面</a>
            </div>
        </div>
        
        <div class="footer">
            <p>PowerDNS API - PHP Implementation v1.0.0</p>
            <p>如有问题，请查看项目文档或提交 Issue</p>
        </div>
    </div>
    
    <script>
        // 每 5 秒检查一次是否配置正确
        setInterval(function() {
            fetch('/api/v1/servers', {
                method: 'HEAD'
            }).then(function(response) {
                if (response.status === 401 || response.status === 200) {
                    // 配置正确了，自动跳转
                    window.location.href = '/';
                }
            }).catch(function() {
                // 继续等待
            });
        }, 5000);
    </script>
</body>
</html>
