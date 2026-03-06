-- ============================================================
-- users 테이블에 구독(StepPay) 관련 컬럼 추가
-- 실행: 운영 DB에서 한 번 실행
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `is_subscribed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '구독 여부' AFTER `status`,
  ADD COLUMN `subscription_expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT '구독 만료일' AFTER `is_subscribed`,
  ADD COLUMN `steppay_customer_id` BIGINT NULL DEFAULT NULL COMMENT 'StepPay 고객 ID' AFTER `subscription_expires_at`,
  ADD COLUMN `steppay_subscription_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'StepPay 구독 ID' AFTER `steppay_customer_id`,
  ADD COLUMN `steppay_order_code` VARCHAR(100) NULL DEFAULT NULL COMMENT 'StepPay 주문 코드' AFTER `steppay_subscription_id`;
