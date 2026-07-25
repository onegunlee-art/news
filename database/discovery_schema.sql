-- ============================================================
-- Discovery (오늘의 발견) — isolated schema
-- No FK to existing gist tables
-- ============================================================

CREATE TABLE IF NOT EXISTS `discovery_editions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edition_date` DATE NOT NULL,
    `status` ENUM('generating', 'draft', 'published') NOT NULL DEFAULT 'draft',
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `change_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `warning_message` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_discovery_edition_date` (`edition_date`),
    KEY `idx_discovery_edition_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discovery_changes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edition_id` INT UNSIGNED NOT NULL,
    `rank` TINYINT UNSIGNED NOT NULL,
    `category` ENUM('geopolitics', 'business', 'tech', 'climate', 'other') NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `summary` TEXT NOT NULL,
    `briefing_json` JSON NOT NULL,
    `status` ENUM('pending', 'verified', 'discarded') NOT NULL DEFAULT 'verified',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_discovery_changes_edition` (`edition_id`),
    KEY `idx_discovery_changes_rank` (`edition_id`, `rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discovery_sources` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(1000) NOT NULL,
    `article_title` VARCHAR(500) NULL DEFAULT NULL,
    `verified` TINYINT(1) NOT NULL DEFAULT 0,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `fail_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_discovery_sources_change` (`change_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discovery_polls` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_id` INT UNSIGNED NOT NULL,
    `question` VARCHAR(300) NOT NULL,
    `options_json` JSON NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_discovery_poll_change` (`change_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discovery_votes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `poll_id` INT UNSIGNED NOT NULL,
    `device_key` VARCHAR(64) NOT NULL,
    `account_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `option_idx` TINYINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_discovery_vote` (`poll_id`, `device_key`),
    KEY `idx_discovery_votes_poll` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `discovery_runs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `run_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `edition_date` DATE NOT NULL,
    `generated_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `discarded_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `reasons_json` JSON NULL,
    `cost_usd` DECIMAL(8,4) NULL DEFAULT NULL,
    `duration_sec` INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_discovery_runs_date` (`edition_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
