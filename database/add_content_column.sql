-- ============================================================
-- News 테이블에 content 컬럼 추가 (기존 테이블이 있는 경우)
-- dothome phpMyAdmin에서 실행하세요
-- ============================================================

-- 1. 먼저 컬럼이 있는지 확인 (에러가 나면 이미 존재)
-- SHOW COLUMNS FROM `news` LIKE 'content';

-- 2. content 컬럼 추가 (없는 경우)
ALTER TABLE `news` 
ADD COLUMN IF NOT EXISTS `content` LONGTEXT NULL COMMENT '뉴스 본문' AFTER `description`;

-- 3. category 컬럼 추가 (없는 경우)
ALTER TABLE `news` 
ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) NULL COMMENT '카테고리' AFTER `image_url`;

-- 4. 기존 news 테이블이 없다면 새로 생성
-- ============================================================
CREATE TABLE IF NOT EXISTS `news` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '뉴스 고유 ID',
    `external_id` VARCHAR(100) NULL COMMENT '외부 API 뉴스 ID',
    `title` VARCHAR(500) NOT NULL COMMENT '뉴스 제목',
    `description` TEXT NULL COMMENT '뉴스 설명/요약',
    `content` LONGTEXT NULL COMMENT '뉴스 본문',
    `source` VARCHAR(200) NULL COMMENT '뉴스 출처 (언론사)',
    `author` VARCHAR(200) NULL COMMENT '기자/작성자',
    `url` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '원본 뉴스 URL',
    `image_url` VARCHAR(1000) NULL COMMENT '대표 이미지 URL',
    `category` VARCHAR(50) NULL COMMENT '카테고리',
    `published_at` DATETIME NULL COMMENT '뉴스 발행 시간',
    `fetched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '수집 시간',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_news_external_id` (`external_id`),
    INDEX `idx_news_source` (`source`),
    INDEX `idx_news_category` (`category`),
    INDEX `idx_news_published_at` (`published_at`),
    INDEX `idx_news_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='뉴스 기사 테이블';
