-- 이메일 인증 코드 저장 (회원가입 시 인증 번호 방식)
-- MySQL 8.0+

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `verified_at` DATETIME NULL,
    `used_at` DATETIME NULL COMMENT '회원가입 완료 시 설정 (재사용 방지)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_code` (`email`, `code`),
    INDEX `idx_email_expires` (`email`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='이메일 인증 코드';
