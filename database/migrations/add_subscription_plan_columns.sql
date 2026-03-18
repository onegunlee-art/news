-- users 테이블에 구독 플랜·시작일 컬럼 추가
-- 닷홈 phpMyAdmin > SQL 탭에서 실행

ALTER TABLE `users`
  ADD COLUMN `subscription_plan` VARCHAR(20) DEFAULT NULL COMMENT '구독 플랜 (1m, 3m, 6m, 12m)' AFTER `subscription_expires_at`,
  ADD COLUMN `subscription_start_date` DATETIME DEFAULT NULL COMMENT '구독 시작일' AFTER `subscription_plan`;
