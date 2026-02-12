-- ============================================================
-- api_usage_logs 테이블 - API 사용량·과금 실시간 로깅
-- OpenAI, Google TTS, Kakao 등 모든 API 호출 시 usage 저장
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_usage_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(50) NOT NULL COMMENT 'openai, google_tts, kakao, supabase, nyt 등',
    `endpoint` VARCHAR(100) NOT NULL COMMENT 'chat, embeddings, images, tts, translate 등',
    `model` VARCHAR(100) NULL COMMENT 'gpt-5.2, dall-e-3, text-embedding-3-small 등',
    `input_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `output_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `images` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'DALL-E 등 이미지 생성 수',
    `characters` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TTS 문자 수',
    `requests` INT UNSIGNED NOT NULL DEFAULT 1,
    `estimated_cost_usd` DECIMAL(12,6) NULL COMMENT '예상 비용 (USD)',
    `metadata` JSON NULL COMMENT '추가 정보 (article_id, job_id 등)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_usage_provider` (`provider`),
    INDEX `idx_usage_endpoint` (`endpoint`),
    INDEX `idx_usage_created_at` (`created_at`),
    INDEX `idx_usage_provider_date` (`provider`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API 사용량 로그';
