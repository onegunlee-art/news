-- ============================================================
-- bookmarks 테이블 생성 (즐겨찾기 기능용)
-- 실행: phpMyAdmin에서 ailand DB 선택 후 SQL 탭에 붙여넣기 후 실행
-- ============================================================

CREATE TABLE IF NOT EXISTS `bookmarks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '북마크 고유 ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '사용자 ID',
    `news_id` INT UNSIGNED NOT NULL COMMENT '뉴스 ID',
    `memo` TEXT NULL COMMENT '사용자 메모',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_bookmarks_user_news` (`user_id`, `news_id`),
    INDEX `idx_bookmarks_user_id` (`user_id`),
    INDEX `idx_bookmarks_news_id` (`news_id`),
    INDEX `idx_bookmarks_created_at` (`created_at`),
    CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bookmarks_news` FOREIGN KEY (`news_id`)
        REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 북마크 테이블';
