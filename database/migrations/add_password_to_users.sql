-- 이메일/비밀번호 로그인 지원: users 테이블에 password_hash 컬럼 추가
-- MySQL 8.0+

ALTER TABLE `users`
ADD COLUMN `password_hash` VARCHAR(255) NULL COMMENT '비밀번호 해시 (이메일 로그인용)' AFTER `profile_image`;

-- 테스트용 계정 (비밀번호: Test1234!)
-- PHP password_hash('Test1234!', PASSWORD_DEFAULT) 결과를 사용하거나, 아래 시드 스크립트로 생성
-- 이 SQL만으로는 해시를 직접 넣기 어려우므로, public/api/seed_test_user.php 실행 권장
