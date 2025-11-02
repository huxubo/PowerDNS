#!/bin/bash
# PowerDNS API 宝塔面板快速配置脚本
# 
# 使用方法：
# chmod +x quick-setup.sh
# ./quick-setup.sh

set -e

echo "======================================"
echo "PowerDNS API 快速配置脚本"
echo "======================================"
echo ""

# 检测是否在正确的目录
if [ ! -f "index.php" ]; then
    echo "错误: 请在项目根目录运行此脚本"
    exit 1
fi

# 1. 创建配置文件
echo "[1/5] 创建配置文件..."
if [ -f "config/config.php" ]; then
    echo "  ⚠️  config/config.php 已存在，跳过"
else
    if [ ! -f "config/config.example.php" ]; then
        echo "  ❌ 错误: config/config.example.php 不存在"
        exit 1
    fi
    
    cp config/config.example.php config/config.php
    echo "  ✓ 已创建 config/config.php"
fi

# 2. 生成 API Key
echo ""
echo "[2/5] 生成 API Key..."
if command -v openssl &> /dev/null; then
    API_KEY=$(openssl rand -hex 32)
    echo "  ✓ API Key: $API_KEY"
    
    # 替换配置文件中的 API Key
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/'key' => 'powerdns-api-key-change-me'/'key' => '$API_KEY'/" config/config.php
    else
        # Linux
        sed -i "s/'key' => 'powerdns-api-key-change-me'/'key' => '$API_KEY'/" config/config.php
    fi
    echo "  ✓ API Key 已写入配置文件"
    echo ""
    echo "  ⚠️  重要: 请保存以下 API Key，后续访问 API 时需要使用"
    echo "  API Key: $API_KEY"
    echo ""
else
    echo "  ⚠️  openssl 未安装，请手动生成 API Key"
fi

# 3. 创建日志目录
echo ""
echo "[3/5] 创建日志目录..."
if [ -d "logs" ]; then
    echo "  ✓ logs 目录已存在"
else
    mkdir -p logs
    echo "  ✓ 已创建 logs 目录"
fi

# 4. 设置权限
echo ""
echo "[4/5] 设置文件权限..."

# 检测 Web 服务器用户
WEB_USER="www"
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
fi

echo "  检测到 Web 服务器用户: $WEB_USER"

# 设置所有者
if [ "$EUID" -eq 0 ]; then
    chown -R $WEB_USER:$WEB_USER .
    chmod 600 config/config.php
    chmod 755 logs
    echo "  ✓ 权限设置完成"
else
    echo "  ⚠️  需要 root 权限才能设置所有者，请手动执行:"
    echo "     sudo chown -R $WEB_USER:$WEB_USER $(pwd)"
    echo "     sudo chmod 600 $(pwd)/config/config.php"
    echo "     sudo chmod 755 $(pwd)/logs"
fi

# 5. 配置提示
echo ""
echo "[5/5] 配置提示"
echo ""
echo "======================================"
echo "基本配置已完成！"
echo "======================================"
echo ""
echo "接下来需要手动完成以下步骤："
echo ""
echo "📌 1. 配置 Nginx 伪静态规则"
echo "   - 进入网站设置 → 伪静态"
echo "   - 复制 docs/baota-nginx-rewrite.conf 中的内容"
echo "   - 粘贴并保存"
echo ""
echo "📌 2. 配置数据库"
echo "   - 编辑 config/config.php"
echo "   - 修改数据库连接信息（host, database, username, password）"
echo ""
echo "📌 3. 导入数据库架构"
echo "   - 在宝塔面板数据库管理中"
echo "   - 导入 database/schema.sql 文件"
echo ""
echo "📌 4. 测试部署"
echo "   - 在浏览器访问: http://你的域名/"
echo "   - 或运行: php check-deployment.php"
echo ""
echo "详细部署指南请查看: BAOTA_DEPLOY.md"
echo ""

# 保存 API Key 到文件（可选）
if [ ! -z "$API_KEY" ]; then
    echo "API_KEY=$API_KEY" > .api-key
    chmod 600 .api-key
    echo "✓ API Key 已保存到 .api-key 文件"
    echo ""
fi

echo "======================================"
echo "配置完成！祝您使用愉快！"
echo "======================================"
