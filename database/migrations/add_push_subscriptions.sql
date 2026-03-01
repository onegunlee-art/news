-- ============================================================
-- push_subscriptions 테이블 생성 (Web Push 알림용)
-- 실행: phpMyAdmin 또는 MySQL 클라이언트에서 ailand DB 선택 후 SQL 탭에 붙여넣기
-- ============================================================

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '구독 고유 ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '사용자 ID',
    `endpoint` VARCHAR(500) NOT NULL COMMENT 'Web Push endpoint URL',
    `p256dh` VARCHAR(255) NOT NULL COMMENT 'ECDH 공개키 (p256dh)',
    `auth` VARCHAR(100) NOT NULL COMMENT '인증 비밀 (auth)',
    `user_agent` VARCHAR(500) NULL COMMENT '브라우저 User-Agent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시간',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_push_user_endpoint` (`user_id`, `endpoint`(255)),
    INDEX `idx_push_user_id` (`user_id`),
    CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web Push 구독 테이블';
