-- 인기 탭: 가장 많이 조회(클릭)한 기사 20개용
ALTER TABLE news
  ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '기사 상세 조회 수 (인기 정렬용)'
  AFTER status;

CREATE INDEX idx_news_view_count ON news (view_count DESC);
