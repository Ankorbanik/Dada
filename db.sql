-- ডাটাবেস: if0_41741438_1 (আপনার ডাটাবেস নাম অনুযায়ী পরিবর্তন করতে পারেন)
CREATE TABLE IF NOT EXISTS `urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short_code` varchar(50) NOT NULL,
  `long_url` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short_code` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` varchar(30) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  `referrer` text DEFAULT NULL,
  `visitor_fingerprint` varchar(64) DEFAULT NULL,
  `hour_of_day` tinyint(2) DEFAULT NULL,
  `day_of_week` varchar(15) DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_short_code` (`short_code`),
  KEY `idx_fingerprint` (`visitor_fingerprint`),
  KEY `idx_clicked_at` (`clicked_at`),
  KEY `idx_hour` (`hour_of_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ip_cache` (
  `ip` varchar(45) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
UPDATE clicks SET hour_of_day = HOUR(CONVERT_TZ(clicked_at, '+00:00', '+06:00')), day_of_week = DAYNAME(CONVERT_TZ(clicked_at, '+00:00', '+06:00')) WHERE hour_of_day IS NULL;
UPDATE clicks SET hour_of_day = MOD(HOUR(clicked_at) + 6, 24), day_of_week = DAYNAME(DATE_ADD(clicked_at, INTERVAL 6 HOUR)) WHERE hour_of_day IS NULL;
ALTER TABLE clicks 
ADD COLUMN IF NOT EXISTS is_conversion TINYINT DEFAULT 0,
ADD COLUMN IF NOT EXISTS page_load_time INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS click_position_x INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS click_position_y INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS viewport_width INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS viewport_height INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS device_model VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS browser_version VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS utm_source VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS utm_term VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS utm_content VARCHAR(255) DEFAULT NULL;
-- ======================================================
-- Database: URL Shortener Analytics System
-- Tables: urls, clicks
-- Author: Auto-generated for overview_analytics.php
-- ======================================================

-- Create database (optional, adjust name as needed)
-- CREATE DATABASE IF NOT EXISTS shortlink_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE shortlink_db;

-- --------------------------------------------------------
-- Table structure for `urls` (stores short codes and original URLs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short_code` varchar(50) NOT NULL COMMENT 'Unique short link identifier',
  `long_url` text NOT NULL COMMENT 'Original long URL',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL COMMENT 'User who created (optional)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `clicks` (stores every click with detailed analytics)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short_code` varchar(50) NOT NULL COMMENT 'Short link that was clicked',
  `visitor_fingerprint` varchar(255) NOT NULL COMMENT 'Unique fingerprint (based on UA, IP, accept-language)',
  `is_bot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if crawler/spider',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Visitor IP (IPv4/IPv6)',
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` enum('Desktop','Mobile','Tablet') DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `browser_version` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `device_model` varchar(100) DEFAULT NULL COMMENT 'e.g., iPhone 12, Samsung Galaxy',
  `referrer` text DEFAULT NULL COMMENT 'HTTP_REFERER',
  `clicked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `utm_source` varchar(255) DEFAULT NULL,
  `utm_medium` varchar(255) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `click_position_x` int(11) DEFAULT NULL COMMENT 'X coordinate of click on page',
  `click_position_y` int(11) DEFAULT NULL COMMENT 'Y coordinate of click on page',
  `viewport_width` int(11) DEFAULT NULL,
  `viewport_height` int(11) DEFAULT NULL,
  `page_load_time` int(11) DEFAULT NULL COMMENT 'Load time in milliseconds',
  `is_conversion` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if click resulted in conversion (e.g., thank-you page)',
  PRIMARY KEY (`id`),
  KEY `short_code` (`short_code`),
  KEY `visitor_fingerprint` (`visitor_fingerprint`),
  KEY `clicked_at` (`clicked_at`),
  KEY `country` (`country`),
  KEY `device_type` (`device_type`),
  KEY `browser` (`browser`),
  KEY `is_bot` (`is_bot`),
  KEY `utm_campaign` (`utm_campaign`),
  KEY `conversion_idx` (`is_conversion`, `clicked_at`),
  CONSTRAINT `clicks_ibfk_1` FOREIGN KEY (`short_code`) REFERENCES `urls` (`short_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Optional: Users table if you want authentication (used by session 'manage_access')
-- Currently your system checks $_SESSION['manage_access'] – you can integrate this table.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager') DEFAULT 'manager',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert a default admin user (password = 'admin123' - CHANGE IT!)
-- The password hash below is for 'admin123' using PASSWORD_DEFAULT (bcrypt).
-- You can generate your own using password_hash('your_password', PASSWORD_DEFAULT).
-- For MySQL, if you use PHP, insert via script. Here we provide a placeholder.
-- INSERT INTO `users` (`username`, `password_hash`) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ======================================================
-- Notes on indexes:
-- - clicked_at and is_bot are used in many WHERE clauses (heatmap, trends)
-- - visitor_fingerprint for unique user counting
-- - short_code for joins and filtering per link
-- - country for map and pie charts
-- - utm_* for campaign analysis
-- ======================================================
-- নতুন ভিজিটর টেবিল
CREATE TABLE IF NOT EXISTS `visitors` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `cookie_id` VARCHAR(255) NOT NULL,
    `local_storage_id` VARCHAR(255) NOT NULL,
    `fingerprint_hash` VARCHAR(255) NOT NULL,
    `browser` VARCHAR(100),
    `os` VARCHAR(100),
    `device_type` VARCHAR(50),
    `screen_resolution` VARCHAR(20),
    `language` VARCHAR(10),
    `timezone` VARCHAR(50),
    `first_seen` DATETIME NOT NULL,
    `last_seen` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cookie_id` (`cookie_id`),
    UNIQUE KEY `local_storage_id` (`local_storage_id`),
    UNIQUE KEY `fingerprint_hash` (`fingerprint_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- clicks টেবিলে নতুন কলাম যোগ (যদি ইতিমধ্যে না থাকে)
ALTER TABLE `clicks` 
ADD COLUMN IF NOT EXISTS `visitor_id` INT(11) NULL,
ADD COLUMN IF NOT EXISTS `cookie_id` VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS `local_storage_id` VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS `session_id` VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS `fingerprint_hash` VARCHAR(255) NULL,
ADD INDEX `idx_visitor_id` (`visitor_id`),
ADD INDEX `idx_session_id` (`session_id`);

-- বিদ্যমান clicks টেবিলের সাথে foreign key (বিদ্যমান ডাটা থাকলে সমস্যা হতে পারে, তাই আলাদা করে দিন)
-- FOREIGN KEY যোগ করতে চাইলে: (আগে সব visitor_id NULL সেট করুন)
-- ALTER TABLE `clicks` ADD FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE SET NULL;