-- 구독 결제용 프로모션 코드 (StepPay 할인 price_code 매핑)
-- 기존 promo_codes 테이bl(가입용)과 별개입니다.

CREATE TABLE IF NOT EXISTS `promotion_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL COMMENT '프로모션 코드 (대소문자 무시 비교)',
    `description` VARCHAR(255) NULL COMMENT '설명',
    `discount_percent` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '표시용 할인율(%)',
    `plan_price_map` JSON NOT NULL COMMENT '플랜별 price_code·금액: {"1m":{"price_code":"...","amount":3850}}',
    `max_uses` INT UNSIGNED NULL COMMENT '전체 최대 사용(완료) 횟수, NULL=무제한',
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '결제 완료 기준 누적',
    `starts_at` TIMESTAMP NULL COMMENT '유효 시작(포함)',
    `expires_at` TIMESTAMP NULL COMMENT '유효 종료(포함)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_promotion_codes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotion_code_usage` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promotion_code_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `plan_id` VARCHAR(32) NOT NULL,
    `original_amount` INT UNSIGNED NOT NULL DEFAULT 0,
    `discounted_amount` INT UNSIGNED NOT NULL DEFAULT 0,
    `order_code` VARCHAR(191) NULL,
    `completed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=결제 검증 완료',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_pcu_promo_user` (`promotion_code_id`, `user_id`),
    INDEX `idx_pcu_user` (`user_id`),
    INDEX `idx_pcu_order` (`order_code`(64)),
    CONSTRAINT `fk_pcu_promo` FOREIGN KEY (`promotion_code_id`)
        REFERENCES `promotion_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pcu_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 주문 생성 시 선택 플랜 / 프로모션(검증 단계에서 매칭용)
-- 이미 컬럼이 있으면 해당 줄은 건너뛰세요.
ALTER TABLE `users`
    ADD COLUMN `pending_checkout_plan_id` VARCHAR(32) NULL DEFAULT NULL COMMENT '구독 플랜 id (1m,3m 등)';
ALTER TABLE `users`
    ADD COLUMN `pending_promotion_code_id` INT UNSIGNED NULL DEFAULT NULL;
