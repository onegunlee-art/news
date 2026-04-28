-- Judgement Layer 데이터 흐름 결함 수정용 마이그레이션
-- 임시저장(draft) 시점에 GPT 분석 원본을 보존해 두었다가,
-- 임시저장→게시(PUT published) 경로에서도 storeJudgementRecord에 비교 입력으로 사용.
-- 이 컬럼이 없으면 AdminDraftPreviewEdit를 통해 게시되는 대부분의 기사에서 Judgement Layer가 작동하지 않습니다.
--
-- 실행: phpMyAdmin 또는 mysql 클라이언트에서 ailand DB 선택 후 실행.
-- 실행 후 storage/cache/news_schema.json 파일을 삭제하거나 1시간 이상 경과하면
-- news.php가 새 컬럼을 자동 인식합니다. 즉시 반영하려면 캐시 파일을 삭제하세요.
--
-- 또는 public/run_add_ai_original_snapshot.php 를 한 번 실행하면
-- ALTER + 스키마 캐시 무효화를 한 번에 처리합니다.

ALTER TABLE news
  ADD COLUMN ai_original_snapshot JSON NULL
  COMMENT 'GPT 분석 원본 스냅샷 - Judgement Layer 비교용 (임시저장 시 보존)';
