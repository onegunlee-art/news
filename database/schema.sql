-- ============================================================
-- News 맥락 분석 데이터베이스 스키마
-- 
-- MySQL 8.0+ 호환
-- 문자셋: utf8mb4
-- 정렬: utf8mb4_unicode_ci
--
-- @author News Context Analysis Team
-- @version 1.0.0
-- ============================================================

-- 데이터베이스 생성 (필요 시)
-- CREATE DATABASE IF NOT EXISTS ailand
-- CHARACTER SET utf8mb4
-- COLLATE utf8mb4_unicode_ci;

-- USE ailand;

-- ============================================================
-- 1. users 테이블 - 사용자 정보
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '사용자 고유 ID',
    `kakao_id` BIGINT UNSIGNED NULL UNIQUE COMMENT '카카오 사용자 ID',
    `email` VARCHAR(255) NULL COMMENT '이메일 주소',
    `nickname` VARCHAR(100) NOT NULL COMMENT '닉네임',
    `profile_image` VARCHAR(500) NULL COMMENT '프로필 이미지 URL',
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user' COMMENT '사용자 역할',
    `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active' COMMENT '계정 상태',
    `last_login_at` TIMESTAMP NULL COMMENT '마지막 로그인 시간',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_users_kakao_id` (`kakao_id`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 정보 테이블';

-- ============================================================
-- 2. user_tokens 테이블 - 리프레시 토큰 관리
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '토큰 고유 ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '사용자 ID',
    `token` VARCHAR(500) NOT NULL COMMENT '리프레시 토큰',
    `token_type` ENUM('refresh', 'kakao_access', 'kakao_refresh') NOT NULL DEFAULT 'refresh' COMMENT '토큰 타입',
    `expires_at` TIMESTAMP NOT NULL COMMENT '만료 시간',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `revoked_at` TIMESTAMP NULL COMMENT '폐기 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_user_tokens_user_id` (`user_id`),
    INDEX `idx_user_tokens_token` (`token`(255)),
    INDEX `idx_user_tokens_expires_at` (`expires_at`),
    CONSTRAINT `fk_user_tokens_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 토큰 테이블';

-- ============================================================
-- 3. news 테이블 - 뉴스 기사 정보
-- ============================================================
CREATE TABLE IF NOT EXISTS `news` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '뉴스 고유 ID',
    `external_id` VARCHAR(100) NULL COMMENT '외부 API 뉴스 ID',
    `title` VARCHAR(500) NOT NULL COMMENT '뉴스 제목',
    `subtitle` VARCHAR(500) NULL COMMENT '부제목 (Foreign Affairs 등 매체의 서브타이틀)',
    `description` TEXT NULL COMMENT '뉴스 설명/요약',
    `content` LONGTEXT NULL COMMENT '뉴스 본문',
    `source` VARCHAR(200) NULL COMMENT '뉴스 출처 (언론사)',
    `author` VARCHAR(200) NULL COMMENT '기자/작성자',
    `url` VARCHAR(1000) NOT NULL COMMENT '원본 뉴스 URL',
    `image_url` VARCHAR(1000) NULL COMMENT '대표 이미지 URL',
    `category` VARCHAR(50) NULL COMMENT '카테고리',
    `published_at` DATETIME NULL COMMENT '뉴스 발행 시간',
    `fetched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '수집 시간',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시간',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_news_url` (`url`(255)),
    INDEX `idx_news_external_id` (`external_id`),
    INDEX `idx_news_source` (`source`),
    INDEX `idx_news_category` (`category`),
    INDEX `idx_news_published_at` (`published_at`),
    INDEX `idx_news_created_at` (`created_at`),
    FULLTEXT INDEX `idx_news_fulltext` (`title`, `description`, `content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='뉴스 기사 테이블';

-- ============================================================
-- 4. analyses 테이블 - 뉴스 분석 결과
-- ============================================================
CREATE TABLE IF NOT EXISTS `analyses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '분석 고유 ID',
    `user_id` INT UNSIGNED NULL COMMENT '요청 사용자 ID (NULL: 시스템 자동 분석)',
    `news_id` INT UNSIGNED NULL COMMENT '분석된 뉴스 ID (NULL: 직접 텍스트 분석)',
    `input_text` TEXT NULL COMMENT '분석된 원본 텍스트 (뉴스가 아닌 경우)',
    `keywords` JSON NOT NULL COMMENT '추출된 키워드 [{"keyword": "...", "score": 0.95}, ...]',
    `sentiment` ENUM('positive', 'negative', 'neutral') NOT NULL COMMENT '감정 분석 결과',
    `sentiment_score` DECIMAL(5,4) NOT NULL DEFAULT 0 COMMENT '감정 점수 (-1.0 ~ 1.0)',
    `sentiment_details` JSON NULL COMMENT '감정 분석 상세 정보',
    `summary` TEXT NOT NULL COMMENT '요약문',
    `summary_length` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '요약문 길이',
    `entities` JSON NULL COMMENT '추출된 개체명 (인물, 조직, 장소 등)',
    `topics` JSON NULL COMMENT '주제 분류 결과',
    `processing_time_ms` INT UNSIGNED NULL COMMENT '분석 소요 시간 (밀리초)',
    `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending' COMMENT '분석 상태',
    `error_message` TEXT NULL COMMENT '에러 메시지 (실패 시)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `completed_at` TIMESTAMP NULL COMMENT '분석 완료 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_analyses_user_id` (`user_id`),
    INDEX `idx_analyses_news_id` (`news_id`),
    INDEX `idx_analyses_sentiment` (`sentiment`),
    INDEX `idx_analyses_status` (`status`),
    INDEX `idx_analyses_created_at` (`created_at`),
    CONSTRAINT `fk_analyses_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_analyses_news` FOREIGN KEY (`news_id`) 
        REFERENCES `news` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='뉴스 분석 결과 테이블';

-- ============================================================
-- 5. bookmarks 테이블 - 사용자 북마크
-- ============================================================
CREATE TABLE IF NOT EXISTS `bookmarks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '북마크 고유 ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '사용자 ID',
    `news_id` INT UNSIGNED NOT NULL COMMENT '뉴스 ID',
    `memo` TEXT NULL COMMENT '사용자 메모',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_bookmarks_user_news` (`user_id`, `news_id`),
    INDEX `idx_bookmarks_user_id` (`user_id`),
    INDEX `idx_bookmarks_news_id` (`news_id`),
    INDEX `idx_bookmarks_created_at` (`created_at`),
    CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bookmarks_news` FOREIGN KEY (`news_id`) 
        REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 북마크 테이블';

-- ============================================================
-- 6. search_history 테이블 - 검색 기록
-- ============================================================
CREATE TABLE IF NOT EXISTS `search_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '검색 기록 고유 ID',
    `user_id` INT UNSIGNED NULL COMMENT '사용자 ID (NULL: 비로그인)',
    `query` VARCHAR(255) NOT NULL COMMENT '검색어',
    `results_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '검색 결과 수',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP 주소',
    `user_agent` VARCHAR(500) NULL COMMENT 'User Agent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '검색 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_search_history_user_id` (`user_id`),
    INDEX `idx_search_history_query` (`query`),
    INDEX `idx_search_history_created_at` (`created_at`),
    CONSTRAINT `fk_search_history_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='검색 기록 테이블';

-- ============================================================
-- 7. api_logs 테이블 - API 호출 로그
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '로그 고유 ID',
    `user_id` INT UNSIGNED NULL COMMENT '사용자 ID',
    `method` VARCHAR(10) NOT NULL COMMENT 'HTTP 메서드',
    `endpoint` VARCHAR(255) NOT NULL COMMENT 'API 엔드포인트',
    `status_code` SMALLINT UNSIGNED NOT NULL COMMENT 'HTTP 상태 코드',
    `request_body` JSON NULL COMMENT '요청 바디 (민감정보 제외)',
    `response_time_ms` INT UNSIGNED NULL COMMENT '응답 시간 (밀리초)',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP 주소',
    `user_agent` VARCHAR(500) NULL COMMENT 'User Agent',
    `error_message` TEXT NULL COMMENT '에러 메시지',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    PRIMARY KEY (`id`),
    INDEX `idx_api_logs_user_id` (`user_id`),
    INDEX `idx_api_logs_endpoint` (`endpoint`),
    INDEX `idx_api_logs_status_code` (`status_code`),
    INDEX `idx_api_logs_created_at` (`created_at`),
    CONSTRAINT `fk_api_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API 호출 로그 테이블';

-- ============================================================
-- 8. settings 테이블 - 시스템 설정
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '설정 고유 ID',
    `key` VARCHAR(100) NOT NULL COMMENT '설정 키',
    `value` TEXT NULL COMMENT '설정 값',
    `type` ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string' COMMENT '값 타입',
    `description` VARCHAR(500) NULL COMMENT '설정 설명',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시간',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='시스템 설정 테이블';

-- ============================================================
-- 초기 설정 데이터 삽입
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('site_name', 'News 맥락 분석', 'string', '사이트 이름'),
    ('site_description', 'AI 기반 뉴스 분석 서비스', 'string', '사이트 설명'),
    ('analysis_daily_limit', '100', 'integer', '일일 분석 제한 횟수'),
    ('news_fetch_interval', '3600', 'integer', '뉴스 수집 간격 (초)'),
    ('maintenance_mode', 'false', 'boolean', '유지보수 모드')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- ============================================================
-- 뷰 생성 - 분석 통계
-- ============================================================
CREATE OR REPLACE VIEW `v_analysis_stats` AS
SELECT 
    DATE(`created_at`) AS `analysis_date`,
    COUNT(*) AS `total_count`,
    SUM(CASE WHEN `sentiment` = 'positive' THEN 1 ELSE 0 END) AS `positive_count`,
    SUM(CASE WHEN `sentiment` = 'negative' THEN 1 ELSE 0 END) AS `negative_count`,
    SUM(CASE WHEN `sentiment` = 'neutral' THEN 1 ELSE 0 END) AS `neutral_count`,
    AVG(`processing_time_ms`) AS `avg_processing_time`
FROM `analyses`
WHERE `status` = 'completed'
GROUP BY DATE(`created_at`);

-- ============================================================
-- 뷰 생성 - 인기 검색어
-- ============================================================
CREATE OR REPLACE VIEW `v_popular_searches` AS
SELECT 
    `query`,
    COUNT(*) AS `search_count`,
    MAX(`created_at`) AS `last_searched_at`
FROM `search_history`
WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY `query`
ORDER BY `search_count` DESC
LIMIT 50;

-- ============================================================
-- 스키마 버전 기록
-- ============================================================
CREATE TABLE IF NOT EXISTS `schema_versions` (
    `version` VARCHAR(50) NOT NULL COMMENT '스키마 버전',
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '적용 시간',
    `description` VARCHAR(255) NULL COMMENT '버전 설명',
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='스키마 버전 관리';

INSERT INTO `schema_versions` (`version`, `description`) VALUES
    ('1.0.0', 'Initial schema creation')
ON DUPLICATE KEY UPDATE `applied_at` = CURRENT_TIMESTAMP;
