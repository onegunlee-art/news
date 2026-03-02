-- ============================================================
-- 우리 정책: 모든 기사의 날짜는 우리가 게시한 날짜(created_at)로 통일
-- published_at이 비어있는 기사는 created_at으로 채움
-- (created_at이 유효하지 않으면 NOW() 사용 - MySQL strict mode 대응)
-- ============================================================
-- 참고: published_at에 ''(빈문자열)이 저장된 경우 phpMyAdmin에서
--       아래를 한 줄씩 실행하세요. (첫 번째만으로 오류 나면 두 번째 시도)
-- ============================================================

-- 1) published_at이 NULL인 행만 (가장 안전)
UPDATE news
SET published_at = IF(created_at IS NOT NULL AND created_at > '2000-01-01', created_at, NOW())
WHERE published_at IS NULL;

-- 2) published_at이 빈문자열/제로데이트인 행 (1) 실행 후 필요 시)
-- UPDATE news
-- SET published_at = IF(created_at IS NOT NULL AND created_at > '2000-01-01', created_at, NOW())
-- WHERE published_at = '0000-00-00 00:00:00' OR published_at = '';
