-- status 컬럼 추가 (draft: 임시저장, published: 공개)
ALTER TABLE news ADD COLUMN status ENUM('draft', 'published') NOT NULL DEFAULT 'published'
  COMMENT 'draft: 임시저장, published: 공개' AFTER updated_at;
CREATE INDEX idx_news_status ON news (status);
