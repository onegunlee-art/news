-- ============================================================
-- 게시 순서 정책 변경에 따른 기존 기사 published_at 정리
-- 정책: published_at = 기사를 실제로 공개한 시점
-- ============================================================
-- 실행 환경: phpMyAdmin 또는 MySQL CLI (닷홈 DB)
-- ============================================================

-- ① 현재 기사 수 확인 (실행 전 확인용)
SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_count,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
    SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) AS null_published_at
FROM news;

-- ② [선택] draft 기사의 published_at을 NULL로 초기화
--    (임시저장 때 잘못 세팅된 published_at 제거)
--    → 이 SQL은 draft 기사가 목록에 노출될 위험 없음 (status='draft'는 published_only 필터로 제외)
UPDATE news
SET published_at = NULL
WHERE status = 'draft';

-- ③ [필수] published 기사 중 published_at이 NULL인 기사 → created_at으로 채움
UPDATE news
SET published_at = IF(created_at IS NOT NULL AND created_at > '2000-01-01', created_at, NOW())
WHERE status = 'published'
  AND published_at IS NULL;

-- ④ [선택] 기존 published 기사 전체의 published_at을 created_at으로 재설정
--    (임시저장 시점이 아닌 생성 시점을 기준으로 초기화하고 싶을 때만 실행)
--    주의: 실행 시 모든 기사의 목록 순서가 created_at 기준으로 재정렬됨
-- UPDATE news
-- SET published_at = IF(created_at IS NOT NULL AND created_at > '2000-01-01', created_at, NOW())
-- WHERE status = 'published';

-- ⑤ 실행 후 확인
SELECT id, title, status, published_at, created_at
FROM news
ORDER BY published_at DESC
LIMIT 20;
