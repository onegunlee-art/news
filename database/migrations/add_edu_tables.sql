-- ============================================================
-- GIST EDU — Sprint 1 tables (Supabase SQL Editor)
-- WRITE boundary: edu_* only. Core news/users unchanged.
-- ============================================================

-- Daily quest catalog (approved seed from GIST_EDU_QUESTS.json)
create table if not exists edu_daily_quests (
  id uuid default gen_random_uuid() primary key,
  quest_code text not null unique,
  quest_title text not null,
  grade_band text not null check (grade_band in ('middle', 'high')),
  status text not null default 'approved' check (status in ('draft', 'approved', 'archived')),
  manual_arc text,
  pro_line text not null,
  con_line text not null,
  alignment_summary text,
  conflict_summary text not null,
  hammer_hints jsonb not null default '{}',
  fsm_stages jsonb not null default '["commit","hammer","reflection","writing","growth"]',
  pilot_priority text check (pilot_priority in ('A', 'B', 'C')),
  scores jsonb default '{}',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists idx_edu_daily_quests_band on edu_daily_quests(grade_band);
create index if not exists idx_edu_daily_quests_pilot on edu_daily_quests(pilot_priority) where pilot_priority is not null;

-- Quest ↔ published article junction
create table if not exists edu_quest_articles (
  id uuid default gen_random_uuid() primary key,
  quest_id uuid not null references edu_daily_quests(id) on delete cascade,
  news_id int not null,
  role text not null check (role in ('primary', 'context', 'counter')),
  sort_order int not null default 0,
  title text,
  gist_url text,
  unique (quest_id, news_id)
);

create index if not exists idx_edu_quest_articles_quest on edu_quest_articles(quest_id);
create index if not exists idx_edu_quest_articles_news on edu_quest_articles(news_id);

-- Pilot cohort (academy)
create table if not exists edu_pilot_cohorts (
  id uuid default gen_random_uuid() primary key,
  name text not null,
  slug text not null unique,
  rotation_codes text[] not null default array['Q-G01','Q-G05','Q-G14'],
  is_active boolean not null default true,
  created_at timestamptz default now()
);

-- Pilot students
create table if not exists edu_students (
  id uuid default gen_random_uuid() primary key,
  cohort_id uuid references edu_pilot_cohorts(id) on delete set null,
  display_name text not null,
  grade_band text not null check (grade_band in ('middle', 'high')),
  invite_code text not null unique,
  access_token_hash text,
  status text not null default 'active' check (status in ('active', 'inactive')),
  created_at timestamptz default now(),
  last_active_at timestamptz
);

create index if not exists idx_edu_students_invite on edu_students(invite_code);
create index if not exists idx_edu_students_cohort on edu_students(cohort_id);

-- FSM session per student × quest attempt
create table if not exists edu_quest_sessions (
  id uuid default gen_random_uuid() primary key,
  student_id uuid not null references edu_students(id) on delete cascade,
  quest_id uuid not null references edu_daily_quests(id) on delete restrict,
  stage text not null default 'commit' check (stage in ('commit', 'hammer', 'reflection', 'writing', 'growth', 'completed')),
  stance text check (stance in ('pro', 'con')),
  hammer_payload jsonb default '{}',
  started_at timestamptz default now(),
  completed_at timestamptz,
  updated_at timestamptz default now()
);

create index if not exists idx_edu_quest_sessions_student on edu_quest_sessions(student_id);
create index if not exists idx_edu_quest_sessions_active on edu_quest_sessions(student_id, stage) where stage != 'completed';

-- Writing v1/v2 + hero sentence for parent Share Card
create table if not exists edu_writing_drafts (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade unique,
  student_id uuid not null references edu_students(id) on delete cascade,
  v1_sentences jsonb not null default '[]',
  v2_sentences jsonb default '[]',
  teacher_feedback text,
  hero_sentence text,
  stance_delta text check (stance_delta in ('refined', 'flipped', 'unchanged')),
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists idx_edu_writing_drafts_student on edu_writing_drafts(student_id);

-- Tier progress (one row per student)
create table if not exists edu_user_tier (
  student_id uuid primary key references edu_students(id) on delete cascade,
  tier_id text not null default 'observer' check (tier_id in (
    'observer', 'iron', 'bronze', 'silver', 'gold', 'platinum', 'gist_challenger'
  )),
  status text not null default 'active' check (status in ('active', 'dormant')),
  xp_current int not null default 0,
  streak_days int not null default 0,
  last_quest_date date,
  dormant_since date,
  updated_at timestamptz default now()
);

-- XP audit log (internal; not front-facing)
create table if not exists edu_xp_events (
  id uuid default gen_random_uuid() primary key,
  student_id uuid not null references edu_students(id) on delete cascade,
  session_id uuid references edu_quest_sessions(id) on delete set null,
  event_type text not null,
  xp_delta int not null,
  meta jsonb default '{}',
  created_at timestamptz default now()
);

create index if not exists idx_edu_xp_events_student on edu_xp_events(student_id, created_at desc);

-- Default pilot cohort
insert into edu_pilot_cohorts (name, slug, rotation_codes)
values ('파일럿 학원 1', 'pilot-01', array['Q-G01','Q-G05','Q-G14'])
on conflict (slug) do nothing;
