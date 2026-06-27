-- Phase 2 — 탐구 깊이 레벨 판정 (Supabase SQL Editor, 멱등)
alter table edu_student_insights
  add column if not exists exploration_depth_level int check (exploration_depth_level between 1 and 7);

comment on column edu_student_insights.exploration_depth_level is
  'LLM/rule 진단 — 이 세션 탐구 깊이 (1~7). Phase 4 레벨업 근거용, 지금은 저장만';
