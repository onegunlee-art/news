-- 위클리 Gist 저장 (Admin 재조회)
CREATE TABLE IF NOT EXISTS `weekly_gist_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `headline` VARCHAR(500) NULL,
  `gist_json` LONGTEXT NOT NULL,
  `article_ids_json` TEXT NULL COMMENT 'JSON array of news ids',
  `article_titles_json` TEXT NULL COMMENT 'JSON object id -> title',
  `article_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wg_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
