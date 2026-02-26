-- 상위 카테고리(외교/경제/특집) 컬럼 추가. 하위(category)는 기존 유지.
-- 메인 탭 필터는 category_parent, 기사 하단 표시는 category(하위)만 사용.

ALTER TABLE news
  ADD COLUMN category_parent VARCHAR(20) NOT NULL DEFAULT 'diplomacy'
  COMMENT '상위: diplomacy(외교), economy(경제), special(특집)'
  AFTER image_url;

-- 기존 데이터: category 값으로 상위 매핑 (entertainment/technology → special)
UPDATE news SET category_parent = 'diplomacy' WHERE category = 'diplomacy' OR category IS NULL OR category = '';
UPDATE news SET category_parent = 'economy'   WHERE category = 'economy';
UPDATE news SET category_parent = 'special'   WHERE category IN ('entertainment', 'technology', 'special');

CREATE INDEX idx_news_category_parent ON news (category_parent);
