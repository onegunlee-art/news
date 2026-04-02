-- 기사 챗봇: 비로그인·비구독(무료 기사) 세션 지원
-- article_chat_sessions.user_id NULL 허용, 세션 식별을 (news_id, session_key)로 통일
--
-- 실행 전 백업 권장. FK 이름이 다르면: SHOW CREATE TABLE article_chat_sessions;

-- 빈 session_key 충돌 방지
UPDATE `article_chat_sessions`
SET `session_key` = CONCAT('legacy_', `id`, '_', UNIX_TIMESTAMP())
WHERE `session_key` = '' OR `session_key` IS NULL;

-- UNIQUE(news_id, session_key) 추가 전에 중복이 있으면:
-- SELECT news_id, session_key, COUNT(*) c FROM article_chat_sessions GROUP BY news_id, session_key HAVING c > 1;
-- 중복 행의 session_key 끝에 _{id} 를 붙여 수동 정리.

ALTER TABLE `article_chat_sessions` DROP FOREIGN KEY `fk_article_chat_user`;

ALTER TABLE `article_chat_sessions`
    MODIFY `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = guest';

ALTER TABLE `article_chat_sessions` DROP INDEX `uniq_article_chat_news_user`;

ALTER TABLE `article_chat_sessions`
    ADD UNIQUE KEY `uniq_article_chat_news_session` (`news_id`, `session_key`);
