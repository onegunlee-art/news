-- ============================================================
-- 지스터 표현 제거: 지스터 → 독자 (정책 변경)
-- 기존에 지스터로 저장된 내레이션을 독자로 통일
-- ============================================================

-- 지스터 여러분 → 독자 여러분 (또는 제거)
UPDATE news 
SET narration = REPLACE(narration, '지스터 여러분', '') 
WHERE narration LIKE '%지스터 여러분%' AND narration IS NOT NULL AND narration != '';

-- 지스터가 → 독자가 (또는 제거)
UPDATE news 
SET narration = REPLACE(narration, '지스터가', '독자가') 
WHERE narration LIKE '%지스터가%' AND narration IS NOT NULL AND narration != '';

-- 지스터에게 → 독자에게 (또는 제거)
UPDATE news 
SET narration = REPLACE(narration, '지스터에게', '독자에게') 
WHERE narration LIKE '%지스터에게%' AND narration IS NOT NULL AND narration != '';

-- 단독 지스터 → 독자
UPDATE news 
SET narration = REPLACE(narration, '지스터', '독자') 
WHERE narration LIKE '%지스터%' AND narration IS NOT NULL AND narration != '';
