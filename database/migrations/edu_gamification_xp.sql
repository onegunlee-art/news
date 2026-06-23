-- ============================================================
-- GIST EDU — 게임화 조각 2: 진단 XP + streak freeze (Supabase)
-- ============================================================

alter table edu_student_insights
  add column if not exists xp_earned int;

alter table edu_user_tier
  add column if not exists streak_freeze_available int not null default 1;

comment on column edu_student_insights.xp_earned is 'P2-B 진단 기반 세션 XP (5~65)';
comment on column edu_user_tier.streak_freeze_available is '스트릭 freeze 잔여 (기본 1)';
