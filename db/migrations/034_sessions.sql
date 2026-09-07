CREATE TABLE IF NOT EXISTS `sessions` (`id` VARCHAR(128) PRIMARY KEY, `payload` MEDIUMTEXT NOT NULL, `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_sessions_activity ON `sessions` (`last_activity`);
