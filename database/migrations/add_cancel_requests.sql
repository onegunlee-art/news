-- 구독 취소 요청 테이블
CREATE TABLE IF NOT EXISTS `cancel_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL COMMENT '요청한 사용자 ID (비로그인이면 NULL)',
    `contact` VARCHAR(255) NOT NULL COMMENT '연락처 (이메일 또는 휴대폰)',
    `message` TEXT NULL COMMENT '취소 사유',
    `status` ENUM('pending', 'done') NOT NULL DEFAULT 'pending' COMMENT '처리 상태',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '요청 시각',
    `processed_at` TIMESTAMP NULL COMMENT '처리 완료 시각',
    INDEX `idx_cancel_status` (`status`),
    INDEX `idx_cancel_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='구독 취소/환불 요청';
