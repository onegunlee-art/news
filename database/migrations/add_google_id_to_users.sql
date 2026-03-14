-- Google OAuth 로그인 지원: users 테이블에 google_id 컬럼 추가
-- MySQL 8.0+

ALTER TABLE `users`
ADD COLUMN `google_id` VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth 사용자 ID' AFTER `kakao_id`;
