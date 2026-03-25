-- 기사 챗: 기존 세션의 question_limit 을 config/article-chat.php 의
-- max_questions_per_session(255)과 맞출 때 사용.
-- 신규 세션은 앱이 INSERT 시 이미 새 한도를 쓰므로, 과거에 4 등으로 박혀 있는 행만 갱신.
--
-- 실행 전: DB 백업 또는 최소 해당 테이블 스냅샷 권장.

UPDATE article_chat_sessions
SET question_limit = 255
WHERE question_limit < 255;
