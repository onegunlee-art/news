-- original_source, original_title 백필
-- 기존 기사 중 빈 값인 경우 source로 채움 (표시용 기본값)
--
-- original_title은 title 복사하지 않음 (title이 한글일 수 있어 원칙 위반).
-- original_title 백필은 Admin의 "original_title HTML 백필" 또는 URL 기반 백필 사용.

-- original_source: source가 있고 Admin이 아니면 source 복사
UPDATE news
SET original_source = source
WHERE (original_source IS NULL OR original_source = '')
  AND source IS NOT NULL
  AND source != ''
  AND source != 'Admin';
