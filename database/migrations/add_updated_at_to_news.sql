-- news 테이블에 updated_at 컬럼 추가
-- 닷홈 phpMyAdmin > SQL 탭에서 실행

ALTER TABLE `news`
ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`;

-- 기존 데이터: updated_at을 created_at 값으로 초기화 (선택사항)
-- UPDATE `news` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;
