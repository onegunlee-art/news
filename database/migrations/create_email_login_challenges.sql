-- 이메일 로그인 2차 인증(OTP) 전용 세션. 회원가입용 email_verifications와 분리.

CREATE TABLE IF NOT EXISTS `email_login_challenges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token` VARCHAR(64) NOT NULL COMMENT '클라이언트에 전달하는 불투명 세션 토큰(hex)',
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL COMMENT '6자리 OTP password_hash',
    `expires_at` DATETIME NOT NULL,
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `consumed_at` DATETIME NULL DEFAULT NULL,
    `last_code_sent_at` DATETIME NOT NULL COMMENT '재발송 쿨다운(1분) 기준',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_elc_token` (`token`),
    KEY `idx_elc_user_pending` (`user_id`, `consumed_at`),
    CONSTRAINT `fk_elc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
