-- 검색 클러스터 분석 저장 (Admin 재열람·편집·Gist 메모리 연결)
CREATE TABLE IF NOT EXISTS `search_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_query` VARCHAR(500) NULL,
  `cluster_name` VARCHAR(500) NOT NULL,
  `cluster_question` VARCHAR(500) NULL,
  `analysis_text` LONGTEXT NOT NULL,
  `news_ids_json` TEXT NOT NULL COMMENT 'JSON array of news ids',
  `article_titles_json` TEXT NULL COMMENT 'JSON object id -> title',
  `entities_json` TEXT NULL COMMENT 'merged entities from RAG metadata',
  `topic_labels_json` TEXT NULL,
  `meta_json` TEXT NULL COMMENT 'memory_diff snapshot, source, etc.',
  `article_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sr_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
