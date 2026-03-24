-- 기사 챗봇 세션·메시지 (MySQL)
-- 실행: 스테이징/프로덕션 DB에 적용

CREATE TABLE IF NOT EXISTS `article_chat_sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `news_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `session_key` VARCHAR(64) NOT NULL DEFAULT '',
    `mode` ENUM('article_single','corpus_assist') NOT NULL DEFAULT 'article_single',
    `question_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `question_limit` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `status` ENUM('active','closed','expired') NOT NULL DEFAULT 'active',
    `expired_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_chat_news_user` (`news_id`, `user_id`),
    KEY `idx_article_chat_user` (`user_id`),
    KEY `idx_article_chat_news` (`news_id`),
    CONSTRAINT `fk_article_chat_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_article_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='기사 챗 세션';

CREATE TABLE IF NOT EXISTS `article_chat_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT UNSIGNED NOT NULL,
    `role` ENUM('user','assistant') NOT NULL,
    `content` MEDIUMTEXT NOT NULL,
    `chip_id` VARCHAR(50) NULL DEFAULT NULL,
    `answer_type` ENUM('summary','structure','intent','risk','scenario','other') NULL DEFAULT NULL,
    `retrieved_refs_json` JSON NULL,
    `tokens_used` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article_chat_msg_session` (`session_id`, `created_at`),
    CONSTRAINT `fk_article_chat_msg_session` FOREIGN KEY (`session_id`) REFERENCES `article_chat_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='기사 챗 메시지';
