-- GIST EDU — 퀘스트 난이도 (L1~L5, 코치 레벨과 동일 체계)
-- Supabase SQL Editor에서 실행 (본체 MySQL 0, edu_* only)
--
-- 배포 순서:
--   1) 이 파일 실행
--   2) php tools/edu_quest_difficulty_backfill.php --dry-run
--   3) php tools/edu_quest_difficulty_backfill.php --write
--   4) docs/edu_quest_difficulty_audit.md 분포 확인

alter table edu_daily_quests
  add column if not exists difficulty_level smallint
  check (difficulty_level is null or difficulty_level between 1 and 5);

comment on column edu_daily_quests.difficulty_level is
  'L1~L5 quest difficulty (matches coach_level labels). Nullable until backfill.';

create index if not exists idx_edu_daily_quests_difficulty
  on edu_daily_quests (difficulty_level, live_at desc nulls last)
  where status = 'approved';

-- 확인 (실행 후 1행이라도 나오면 OK)
-- select quest_code, quest_title, difficulty_level
-- from edu_daily_quests
-- where status = 'approved'
-- order by live_at desc nulls last
-- limit 5;
