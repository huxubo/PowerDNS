-- PowerDNS 数据库架构
-- 适用于 MySQL 5.7+ 和 MariaDB 10.2+

-- 创建数据库
CREATE DATABASE IF NOT EXISTS `powerdns` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `powerdns`;

-- 区域（域名）表
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT '域名',
  `master` VARCHAR(128) DEFAULT NULL COMMENT '主服务器',
  `last_check` INT DEFAULT NULL COMMENT '上次检查时间',
  `type` VARCHAR(6) NOT NULL COMMENT '类型: MASTER, SLAVE, NATIVE',
  `notified_serial` INT DEFAULT NULL COMMENT '已通知的序列号',
  `account` VARCHAR(40) DEFAULT NULL COMMENT '账户',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DNS区域表';

-- DNS 记录表
CREATE TABLE IF NOT EXISTS `records` (
  `id` INT AUTO_INCREMENT,
  `domain_id` INT DEFAULT NULL COMMENT '所属区域ID',
  `name` VARCHAR(255) DEFAULT NULL COMMENT '记录名称',
  `type` VARCHAR(10) DEFAULT NULL COMMENT '记录类型: A, AAAA, CNAME, MX, NS, TXT等',
  `content` VARCHAR(65535) DEFAULT NULL COMMENT '记录内容',
  `ttl` INT DEFAULT NULL COMMENT 'TTL（生存时间）',
  `prio` INT DEFAULT NULL COMMENT '优先级（用于MX和SRV记录）',
  `disabled` TINYINT(1) DEFAULT 0 COMMENT '是否禁用',
  `ordername` VARCHAR(255) BINARY DEFAULT NULL COMMENT 'DNSSEC排序名称',
  `auth` TINYINT(1) DEFAULT 1 COMMENT '是否权威',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  KEY `name_index` (`name`),
  KEY `nametype_index` (`name`,`type`),
  KEY `domain_id_ordername` (`domain_id`, `ordername`),
  CONSTRAINT `records_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DNS记录表';

-- 超级主服务器表
CREATE TABLE IF NOT EXISTS `supermasters` (
  `ip` VARCHAR(64) NOT NULL COMMENT '超级主服务器IP',
  `nameserver` VARCHAR(255) NOT NULL COMMENT '名称服务器',
  `account` VARCHAR(40) NOT NULL COMMENT '账户',
  PRIMARY KEY (`ip`, `nameserver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='超级主服务器表';

-- 记录注释表
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT AUTO_INCREMENT,
  `domain_id` INT NOT NULL COMMENT '所属区域ID',
  `name` VARCHAR(255) NOT NULL COMMENT '记录名称',
  `type` VARCHAR(10) NOT NULL COMMENT '记录类型',
  `modified_at` INT NOT NULL COMMENT '修改时间戳',
  `account` VARCHAR(40) NOT NULL COMMENT '账户',
  `comment` TEXT NOT NULL COMMENT '注释内容',
  PRIMARY KEY (`id`),
  KEY `comments_domain_id_idx` (`domain_id`),
  KEY `comments_name_type_idx` (`name`, `type`),
  KEY `comments_order_idx` (`domain_id`, `modified_at`),
  CONSTRAINT `comments_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记录注释表';

-- 区域元数据表
CREATE TABLE IF NOT EXISTS `domainmetadata` (
  `id` INT AUTO_INCREMENT,
  `domain_id` INT NOT NULL COMMENT '所属区域ID',
  `kind` VARCHAR(32) NOT NULL COMMENT '元数据类型',
  `content` TEXT COMMENT '元数据内容',
  PRIMARY KEY (`id`),
  KEY `domainmetadata_idx` (`domain_id`, `kind`),
  CONSTRAINT `domainmetadata_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='区域元数据表';

-- DNSSEC 密钥表
CREATE TABLE IF NOT EXISTS `cryptokeys` (
  `id` INT AUTO_INCREMENT,
  `domain_id` INT NOT NULL COMMENT '所属区域ID',
  `flags` INT NOT NULL COMMENT '密钥标志',
  `active` TINYINT(1) NOT NULL COMMENT '是否激活',
  `published` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否已发布',
  `content` TEXT COMMENT '密钥内容',
  PRIMARY KEY (`id`),
  KEY `cryptokeys_domain_id_idx` (`domain_id`),
  CONSTRAINT `cryptokeys_domain_id_ibfk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DNSSEC密钥表';

-- TSIG 密钥表
CREATE TABLE IF NOT EXISTS `tsigkeys` (
  `id` INT AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT '密钥名称',
  `algorithm` VARCHAR(50) NOT NULL COMMENT '算法',
  `secret` VARCHAR(255) NOT NULL COMMENT '密钥',
  PRIMARY KEY (`id`),
  UNIQUE KEY `namealgoindex` (`name`, `algorithm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TSIG密钥表';

-- API 密钥表（自定义，用于API认证）
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL COMMENT 'API密钥',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
  `permissions` TEXT COMMENT '权限（JSON格式）',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否激活',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `last_used_at` TIMESTAMP NULL DEFAULT NULL COMMENT '最后使用时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API密钥表';

-- CNAME 展平缓存表（自定义，用于性能优化）
CREATE TABLE IF NOT EXISTS `cname_flatten_cache` (
  `id` INT AUTO_INCREMENT,
  `source_name` VARCHAR(255) NOT NULL COMMENT '源记录名称',
  `target_type` VARCHAR(10) NOT NULL COMMENT '目标记录类型',
  `target_content` TEXT NOT NULL COMMENT '目标记录内容（JSON格式）',
  `ttl` INT NOT NULL COMMENT 'TTL',
  `expires_at` TIMESTAMP NOT NULL COMMENT '过期时间',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_target_index` (`source_name`, `target_type`),
  KEY `expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CNAME展平缓存表';

-- 插入默认 API 密钥（请在生产环境中修改）
INSERT INTO `api_keys` (`key`, `description`, `permissions`, `active`) VALUES
('powerdns-api-key-change-me', '默认API密钥（请修改）', '{"servers": ["*"], "zones": ["*"]}', 1);

-- 插入示例数据（可选）
INSERT INTO `domains` (`name`, `type`) VALUES ('example.com', 'NATIVE');

SET @domain_id = LAST_INSERT_ID();

INSERT INTO `records` (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `disabled`, `auth`) VALUES
(@domain_id, 'example.com', 'SOA', 'ns1.example.com. hostmaster.example.com. 2024010101 3600 1800 604800 86400', 3600, NULL, 0, 1),
(@domain_id, 'example.com', 'NS', 'ns1.example.com.', 3600, NULL, 0, 1),
(@domain_id, 'example.com', 'NS', 'ns2.example.com.', 3600, NULL, 0, 1),
(@domain_id, 'ns1.example.com', 'A', '192.168.1.1', 3600, NULL, 0, 1),
(@domain_id, 'ns2.example.com', 'A', '192.168.1.2', 3600, NULL, 0, 1),
(@domain_id, 'www.example.com', 'A', '192.168.1.10', 3600, NULL, 0, 1),
(@domain_id, 'mail.example.com', 'A', '192.168.1.20', 3600, NULL, 0, 1),
(@domain_id, 'example.com', 'MX', 'mail.example.com.', 3600, 10, 0, 1);

-- 清理过期的 CNAME 展平缓存（建议定期执行）
-- DELETE FROM `cname_flatten_cache` WHERE `expires_at` < NOW();
