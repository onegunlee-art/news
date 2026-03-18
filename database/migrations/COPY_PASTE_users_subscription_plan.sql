-- ============================================================
-- 닷홈 phpMyAdmin → SQL 탭 → 아래 블록만 복사해서 실행
-- (이미 컬럼이 있으면 오류 남 → 한 번만 실행)
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `subscription_plan` VARCHAR(20) DEFAULT NULL COMMENT '구독 플랜 (1m, 3m, 6m, 12m)' AFTER `subscription_expires_at`,
  ADD COLUMN `subscription_start_date` DATETIME DEFAULT NULL COMMENT '구독 시작일' AFTER `subscription_plan`;
