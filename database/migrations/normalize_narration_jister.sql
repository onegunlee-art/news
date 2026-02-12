-- 내레이션 지스터 통일 (시청자/청취자 → 지스터)
-- 적용: phpMyAdmin 또는 mysql 클라이언트에서 실행

UPDATE news SET narration = REPLACE(narration, '시청자 여러분', '지스터 여러분')
WHERE narration LIKE '%시청자 여러분%' AND narration IS NOT NULL AND narration != '';

UPDATE news SET narration = REPLACE(narration, '청취자가', '지스터가')
WHERE narration LIKE '%청취자가%' AND narration IS NOT NULL AND narration != '';

UPDATE news SET narration = REPLACE(narration, '청취자에게', '지스터에게')
WHERE narration LIKE '%청취자에게%' AND narration IS NOT NULL AND narration != '';
