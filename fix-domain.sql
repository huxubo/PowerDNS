-- 修复域名108.xx.kg的SQL脚本
-- 这个脚本会创建域名记录（如果不存在）并添加基本的DNS记录

USE powerdns;

-- 检查域名是否已存在
SELECT 'Checking if domain 108.xx.kg exists...' as status;
SELECT * FROM domains WHERE name IN ('108.xx.kg', '108.xx.kg.');

-- 如果域名不存在，创建它
INSERT IGNORE INTO `domains` (`name`, `type`, `account`) VALUES 
('108.xx.kg', 'NATIVE', 'default'),
('108.xx.kg.', 'NATIVE', 'default');

-- 获取域名ID（带点的版本）
SET @domain_id = NULL;
SELECT id INTO @domain_id FROM domains WHERE name = '108.xx.kg.' LIMIT 1;

-- 如果找到了域名ID，添加基本记录
IF @domain_id IS NOT NULL THEN
    SELECT 'Domain found with ID: ' + @domain_id as status;
    
    -- 删除现有记录（如果有的话）
    DELETE FROM records WHERE domain_id = @domain_id;
    
    -- 插入SOA记录
    INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
    (@domain_id, '108.xx.kg', 'SOA', 'ns1.108.xx.kg. hostmaster.108.xx.kg. 2024010101 3600 1800 604800 86400', 3600, NULL, 0, 1);
    
    -- 插入NS记录
    INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
    (@domain_id, '108.xx.kg', 'NS', 'ns1.108.xx.kg.', 3600, NULL, 0, 1),
    (@domain_id, '108.xx.kg', 'NS', 'ns2.108.xx.kg.', 3600, NULL, 0, 1);
    
    -- 插入A记录（名称服务器）
    INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
    (@domain_id, 'ns1.108.xx.kg', 'A', '192.168.1.1', 3600, NULL, 0, 1),
    (@domain_id, 'ns2.108.xx.kg', 'A', '192.168.1.2', 3600, NULL, 0, 1);
    
    -- 插入主域名A记录
    INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
    (@domain_id, '108.xx.kg', 'A', '192.168.1.100', 3600, NULL, 0, 1);
    
    -- 插入www子域名
    INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
    (@domain_id, 'www.108.xx.kg', 'A', '192.168.1.100', 3600, NULL, 0, 1);
    
    SELECT 'Domain 108.xx.kg created with basic DNS records' as result;
    
ELSE
    SELECT 'Domain 108.xx.kg not found and could not be created' as result;
END IF;

-- 显示最终的域名状态
SELECT 'Final domain status:' as status;
SELECT * FROM domains WHERE name IN ('108.xx.kg', '108.xx.kg.');

-- 显示所有记录
SELECT 'DNS records for domain:' as status;
SELECT r.* FROM records r 
JOIN domains d ON r.domain_id = d.id 
WHERE d.name IN ('108.xx.kg', '108.xx.kg.')
ORDER BY r.type, r.name;