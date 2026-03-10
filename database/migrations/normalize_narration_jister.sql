-- 내레이션에서 지스터/시청자/청취자 인사말 제거
-- 정책: 인사말 없이 바로 본문으로 시작
-- 적용: phpMyAdmin 또는 mysql 클라이언트에서 실행

-- 지스터 여러분, 으로 시작하는 문장에서 해당 인사말 제거
UPDATE news SET narration = TRIM(REGEXP_REPLACE(narration, '^지스터\\s+여러분[,\\.\\s]*', ''))
WHERE narration REGEXP '^지스터\\s+여러분' AND narration IS NOT NULL AND narration != '';

-- 시청자 여러분, 으로 시작하는 문장에서 해당 인사말 제거
UPDATE news SET narration = TRIM(REGEXP_REPLACE(narration, '^시청자\\s+여러분[,\\.\\s]*', ''))
WHERE narration REGEXP '^시청자\\s+여러분' AND narration IS NOT NULL AND narration != '';

-- 청취자 여러분, 으로 시작하는 문장에서 해당 인사말 제거
UPDATE news SET narration = TRIM(REGEXP_REPLACE(narration, '^청취자\\s+여러분[,\\.\\s]*', ''))
WHERE narration REGEXP '^청취자\\s+여러분' AND narration IS NOT NULL AND narration != '';

-- 여러분, 으로 시작하는 문장에서 해당 인사말 제거
UPDATE news SET narration = TRIM(REGEXP_REPLACE(narration, '^여러분[,\\.\\s]*', ''))
WHERE narration REGEXP '^여러분[,\\.]' AND narration IS NOT NULL AND narration != '';
