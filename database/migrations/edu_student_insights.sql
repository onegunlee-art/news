-- ============================================================
-- GIST EDU — P2-B 2단계: 학생 구조 진단 누적 (Supabase SQL Editor)
-- WRITE boundary: edu_* only. 점수/등급 없음. internal_only.
-- ============================================================

create table if not exists edu_student_insights (
  id uuid default gen_random_uuid() primary key,
  student_id uuid not null references edu_students(id) on delete cascade,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  quest_code text not null,
  diagnosed_at timestamptz not null default now(),

  axes_engaged_count int not null default 0,
  axes_total int not null default 0,
  tension_engaged text,
  conclusion_clarity text,
  evidence_linked text,
  structure_note text,
  diagnose_version text not null,
  diagnose_mode text not null default 'rule_fallback',

  diagnose_json jsonb not null default '{}',
  internal_only boolean not null default true,
  created_at timestamptz default now(),

  unique (session_id)
);

create index if not exists idx_edu_student_insights_student_time
  on edu_student_insights (student_id, diagnosed_at asc);

create index if not exists idx_edu_student_insights_quest
  on edu_student_insights (quest_code, diagnosed_at desc);
