-- 지스트 크리티크(미래 전망) 저장용 컬럼. 오디오에서 내레이션+크리티크 전부 읽기 위해 필요.
-- 실행: phpMyAdmin 또는 mysql 클라이언트에서 ailand DB 선택 후 실행

ALTER TABLE news ADD COLUMN future_prediction TEXT NULL AFTER narration;
