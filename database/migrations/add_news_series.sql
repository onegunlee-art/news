-- 시리즈(분할 기사) 지원: series_id + series_order
-- 같은 원문을 여러 편으로 쪼갠 기사를 그룹핑합니다.
-- 기존 기사는 모두 NULL (단독 기사)이므로 영향 없음.

ALTER TABLE `news`
  ADD COLUMN `series_id` VARCHAR(36) NULL DEFAULT NULL COMMENT '시리즈 그룹 ID (UUID)' AFTER `also_special`,
  ADD COLUMN `series_order` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '시리즈 내 순서 (1부터)' AFTER `series_id`,
  ADD COLUMN `series_title` VARCHAR(200) NULL DEFAULT NULL COMMENT '시리즈 제목 (예: 트럼프 관세 분석)' AFTER `series_order`;

ALTER TABLE `news`
  ADD INDEX `idx_news_series` (`series_id`, `series_order`);
