-- 내레이션 "시청자 여러분" → "지스터 여러분" 일괄 치환
-- 적용: phpMyAdmin 또는 mysql 클라이언트에서 실행

-- narration 컬럼이 있는 경우에만 실행 (스키마에 따라 다를 수 있음)
UPDATE news
SET narration = REPLACE(narration, '시청자 여러분', '지스터 여러분')
WHERE narration LIKE '%시청자 여러분%'
  AND narration IS NOT NULL
  AND narration != '';
