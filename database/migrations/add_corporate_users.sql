-- ============================================================
-- 기업 고객 일괄 등록: company_tag + corporate_otp_skip
-- 실행: 운영 DB에서 한 번 실행
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `company_tag` VARCHAR(50) NULL
  COMMENT '기업 고객 소속 (예: hyundai, samsung)'
  AFTER `profile_image`;

CREATE INDEX `idx_users_company_tag` ON `users` (`company_tag`);

CREATE TABLE IF NOT EXISTS `corporate_otp_skip` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `email` VARCHAR(255) NOT NULL COMMENT 'OTP 생략 이메일 (소문자)',
    `company_tag` VARCHAR(50) NOT NULL COMMENT '기업 태그',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 시각',
    `created_by` INT UNSIGNED NULL COMMENT '등록한 admin user id',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_corporate_otp_skip_email` (`email`),
    KEY `idx_corporate_otp_skip_company` (`company_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='기업 고객 로그인 OTP 생략 목록';
