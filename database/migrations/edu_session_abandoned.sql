-- GIST EDU — allow stage=abandoned for in-progress session reset (P1-0)
-- Supabase SQL Editor: paste and run, or:
--   php tools/edu_apply_abandoned_stage_migration.php

alter table edu_quest_sessions drop constraint if exists edu_quest_sessions_stage_check;
alter table edu_quest_sessions add constraint edu_quest_sessions_stage_check
  check (stage in (
    'commit', 'reasoning', 'evidence', 'hammer', 'reflection',
    'writing', 'compose', 'growth', 'completed', 'abandoned'
  ));

drop index if exists idx_edu_quest_sessions_active;
create index if not exists idx_edu_quest_sessions_active
  on edu_quest_sessions (student_id, stage)
  where stage not in ('completed', 'abandoned');
