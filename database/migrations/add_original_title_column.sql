-- 원문 영어 제목 (매체글 TTS용)
-- GPT 분석에서 추출한 original_title 저장
-- 적용: phpMyAdmin 또는 mysql 클라이언트에서 실행 (컬럼 이미 있으면 에러 무시)
ALTER TABLE news ADD COLUMN original_title VARCHAR(500) NULL COMMENT '원문 영어 제목 (매체글 TTS용)' AFTER title;
