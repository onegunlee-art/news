-- B-2: 코치 레벨 진척 게이지 (XP = 현재 L1~5 게이지, 7단 메달과 분리)
ALTER TABLE edu_user_tier
  ADD COLUMN IF NOT EXISTS coach_gauge_xp INT NOT NULL DEFAULT 0;

COMMENT ON COLUMN edu_user_tier.coach_gauge_xp IS 'Current coach level gauge fill (0–100 target per level; B-3 resets on level-up)';
