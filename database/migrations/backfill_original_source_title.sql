-- original_source, original_title 백필
-- 기존 기사 중 빈 값인 경우 source, title로 채움 (표시용 기본값)

-- original_source: source가 있고 Admin이 아니면 source 복사
UPDATE news
SET original_source = source
WHERE (original_source IS NULL OR original_source = '')
  AND source IS NOT NULL
  AND source != ''
  AND source != 'Admin';

-- original_title: 비어있으면 title 복사 (영문 여부와 관계없이 기본값)
UPDATE news
SET original_title = title
WHERE (original_title IS NULL OR original_title = '')
  AND title IS NOT NULL
  AND title != '';
