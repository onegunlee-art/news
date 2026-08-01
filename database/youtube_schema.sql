-- ============================================================
-- YouTube Shorts — isolated schema
-- No FK to existing gist/discovery tables (change_id is reference only)
-- ============================================================

CREATE TABLE IF NOT EXISTS `youtube_projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_id` INT UNSIGNED NOT NULL COMMENT 'Reference to discovery_changes.id (no FK)',
    `edition_date` DATE NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `status` ENUM('pending', 'scripted', 'visual_ready', 'audio_ready', 'rendered', 'failed') NOT NULL DEFAULT 'pending',
    `error_message` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_youtube_projects_change` (`change_id`),
    KEY `idx_youtube_projects_date` (`edition_date`),
    KEY `idx_youtube_projects_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `youtube_scenes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `scene_num` TINYINT UNSIGNED NOT NULL COMMENT '1-6',
    `visual_type` ENUM('fixed', 'map', 'text', 'chart', 'cinematic') NOT NULL,
    `narration` TEXT NULL COMMENT 'TTS narration text',
    `text_overlay` JSON NULL COMMENT 'Text overlays for the scene',
    `location` VARCHAR(200) NULL COMMENT 'For map scenes: city, country',
    `visual_path` VARCHAR(500) NULL COMMENT 'Path to generated image',
    `audio_path` VARCHAR(500) NULL COMMENT 'Path to TTS audio file',
    `duration_ms` INT UNSIGNED NULL COMMENT 'Scene duration in milliseconds',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_youtube_scene` (`project_id`, `scene_num`),
    KEY `idx_youtube_scenes_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `youtube_videos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `version` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `video_path` VARCHAR(500) NOT NULL,
    `thumbnail_path` VARCHAR(500) NULL,
    `duration_sec` INT UNSIGNED NULL,
    `file_size_bytes` BIGINT UNSIGNED NULL,
    `status` ENUM('rendering', 'ready', 'failed') NOT NULL DEFAULT 'rendering',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_youtube_videos_project` (`project_id`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `youtube_runs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `run_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `stage` VARCHAR(50) NOT NULL COMMENT 'script, visual, audio, render',
    `status` ENUM('started', 'completed', 'failed') NOT NULL,
    `duration_ms` INT UNSIGNED NULL,
    `error_message` TEXT NULL,
    `metadata_json` JSON NULL,
    PRIMARY KEY (`id`),
    KEY `idx_youtube_runs_project` (`project_id`, `run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
