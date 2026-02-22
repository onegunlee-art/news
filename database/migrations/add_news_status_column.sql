-- status 컬럼 추가 (draft: 임시저장, published: 공개)
-- AFTER 절 없이 추가 (updated_at 등 참조 컬럼이 없을 수 있음)
ALTER TABLE news ADD COLUMN status ENUM('draft', 'published') NOT NULL DEFAULT 'published'
  COMMENT 'draft: 임시저장, published: 공개';
CREATE INDEX idx_news_status ON news (status);
