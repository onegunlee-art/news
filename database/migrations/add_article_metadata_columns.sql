-- 기사 메타데이터 컬럼 추가
-- 원본 출처, 작성자, 발행일 저장을 위한 컬럼

-- original_source: 추출된 원본 출처 (예: Financial Times, Reuters)
ALTER TABLE news ADD COLUMN IF NOT EXISTS original_source VARCHAR(255) DEFAULT NULL AFTER source;

-- author: 원본 기사 작성자
ALTER TABLE news ADD COLUMN IF NOT EXISTS author VARCHAR(255) DEFAULT NULL AFTER original_source;

-- published_at: 원본 기사 발행일 (문자열로 저장, 다양한 형식 지원)
ALTER TABLE news ADD COLUMN IF NOT EXISTS published_at VARCHAR(100) DEFAULT NULL AFTER author;

-- 참고: image_url 컬럼은 이미 존재함
