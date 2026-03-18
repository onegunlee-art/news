-- 기사의 '특집 동시 노출' 플래그 추가
-- 외교/경제 기사를 특집 탭에도 동시 노출할 때 사용
ALTER TABLE news
  ADD COLUMN also_special TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '특집 동시 노출 (1이면 원래 카테고리 + 특집 모두 노출)'
  AFTER category_parent;

CREATE INDEX idx_news_also_special ON news (also_special);

-- 특집 배지 문구 (기본값 MSC)
INSERT INTO settings (`key`, `value`) VALUES ('special_badge_text', 'MSC')
ON DUPLICATE KEY UPDATE `value` = `value`;
