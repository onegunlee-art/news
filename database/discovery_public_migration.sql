-- Discovery public service migration (discovery_* only; no gist table changes)

CREATE TABLE IF NOT EXISTS `discovery_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `poll_id` INT UNSIGNED NOT NULL,
    `device_key` VARCHAR(64) NOT NULL,
    `body` VARCHAR(500) NOT NULL,
    `ip_hash` CHAR(64) NULL DEFAULT NULL,
    `deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_discovery_comments_poll` (`poll_id`, `deleted`, `created_at`),
    KEY `idx_discovery_comments_device` (`device_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discovery_rate_limits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket_key` VARCHAR(128) NOT NULL,
    `window_start` TIMESTAMP NOT NULL,
    `hit_count` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_discovery_rate_bucket` (`bucket_key`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rename user_key -> device_key when legacy column exists
SET @has_user_key := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'discovery_votes'
      AND COLUMN_NAME = 'user_key'
);
SET @sql_rename := IF(
    @has_user_key > 0,
    'ALTER TABLE `discovery_votes` CHANGE COLUMN `user_key` `device_key` VARCHAR(64) NOT NULL',
    'SET @noop = 0'
);
PREPARE stmt_rename FROM @sql_rename;
EXECUTE stmt_rename;
DEALLOCATE PREPARE stmt_rename;

SET @has_device_key := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'discovery_votes'
      AND COLUMN_NAME = 'device_key'
);
SET @sql_add_device := IF(
    @has_device_key = 0,
    'ALTER TABLE `discovery_votes` ADD COLUMN `device_key` VARCHAR(64) NOT NULL AFTER `poll_id`',
    'SET @noop = 0'
);
PREPARE stmt_add_device FROM @sql_add_device;
EXECUTE stmt_add_device;
DEALLOCATE PREPARE stmt_add_device;

SET @has_account_user := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'discovery_votes'
      AND COLUMN_NAME = 'account_user_id'
);
SET @sql_add_account := IF(
    @has_account_user = 0,
    'ALTER TABLE `discovery_votes` ADD COLUMN `account_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `device_key`',
    'SET @noop = 0'
);
PREPARE stmt_add_account FROM @sql_add_account;
EXECUTE stmt_add_account;
DEALLOCATE PREPARE stmt_add_account;

SET @has_vote_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'discovery_votes'
      AND INDEX_NAME = 'uniq_discovery_vote'
);
SET @sql_reindex := IF(
    @has_vote_unique > 0,
    'ALTER TABLE `discovery_votes` DROP INDEX `uniq_discovery_vote`, ADD UNIQUE KEY `uniq_discovery_vote` (`poll_id`, `device_key`)',
    'ALTER TABLE `discovery_votes` ADD UNIQUE KEY `uniq_discovery_vote` (`poll_id`, `device_key`)'
);
PREPARE stmt_reindex FROM @sql_reindex;
EXECUTE stmt_reindex;
DEALLOCATE PREPARE stmt_reindex;
