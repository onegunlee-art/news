-- ============================================================
-- GIST EDU Pilot — 추가 테이블 (Supabase SQL Editor)
-- 기존 edu_* 테이블 위에 파일럿 기능 추가
-- ============================================================

-- 1. edu_students에 카카오 로그인 필드 추가
alter table edu_students 
add column if not exists kakao_id text unique,
add column if not exists profile_image text,
add column if not exists email text;

create index if not exists idx_edu_students_kakao on edu_students(kakao_id) where kakao_id is not null;

-- 2. 입장 변화 추적 (v1 → v2, 핵심 테이블)
create table if not exists edu_hypothesis_versions (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  student_id uuid not null references edu_students(id) on delete cascade,
  version int not null check (version in (1, 2)),
  stance text not null check (stance in ('pro', 'con')),
  reason text,
  confidence_level int check (confidence_level between 1 and 5),
  created_at timestamptz default now(),
  unique (session_id, version)
);

create index if not exists idx_edu_hypothesis_session on edu_hypothesis_versions(session_id);
create index if not exists idx_edu_hypothesis_student on edu_hypothesis_versions(student_id);

-- 3. 대화/생각 로그
create table if not exists edu_thinking_logs (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  student_id uuid not null references edu_students(id) on delete cascade,
  turn_number int not null,
  agent_role text not null check (agent_role in ('socratic', 'hammer', 'reflection', 'writing')),
  prompt_sent text,
  student_response text,
  ai_feedback text,
  created_at timestamptz default now()
);

create index if not exists idx_edu_thinking_session on edu_thinking_logs(session_id, turn_number);

-- 4. 근거 로그
create table if not exists edu_evidence_logs (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  student_id uuid not null references edu_students(id) on delete cascade,
  evidence_text text not null,
  source_type text check (source_type in ('article', 'student', 'ai_suggested')),
  news_id int,
  created_at timestamptz default now()
);

create index if not exists idx_edu_evidence_session on edu_evidence_logs(session_id);

-- 5. 반론/재답변 로그
create table if not exists edu_counter_logs (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  student_id uuid not null references edu_students(id) on delete cascade,
  counter_argument text not null,
  student_rebuttal text,
  impact_score int check (impact_score between 1 and 5),
  led_to_stance_change boolean default false,
  created_at timestamptz default now()
);

create index if not exists idx_edu_counter_session on edu_counter_logs(session_id);

-- 6. 3줄 정리 (Reflection)
create table if not exists edu_reflections (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade unique,
  student_id uuid not null references edu_students(id) on delete cascade,
  summary_lines jsonb not null default '[]',
  key_insight text,
  stance_change_reason text,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists idx_edu_reflections_student on edu_reflections(student_id);

-- 7. 5문장 글 버전 (기존 edu_writing_drafts 확장용)
create table if not exists edu_writing_versions (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade,
  student_id uuid not null references edu_students(id) on delete cascade,
  version int not null default 1,
  scqa_situation text,
  scqa_complication text,
  scqa_question text,
  scqa_answer text,
  conclusion text,
  word_count int,
  quality_score int check (quality_score between 1 and 100),
  ai_feedback text,
  created_at timestamptz default now()
);

create index if not exists idx_edu_writing_versions_session on edu_writing_versions(session_id);

-- 8. 티어 승급 이력
create table if not exists edu_tier_history (
  id uuid default gen_random_uuid() primary key,
  student_id uuid not null references edu_students(id) on delete cascade,
  from_tier text not null,
  to_tier text not null,
  trigger_event text,
  xp_at_promotion int,
  created_at timestamptz default now()
);

create index if not exists idx_edu_tier_history_student on edu_tier_history(student_id, created_at desc);

-- 9. 배지
create table if not exists edu_badges (
  id uuid default gen_random_uuid() primary key,
  student_id uuid not null references edu_students(id) on delete cascade,
  badge_code text not null,
  badge_name text not null,
  description text,
  earned_at timestamptz default now(),
  unique (student_id, badge_code)
);

create index if not exists idx_edu_badges_student on edu_badges(student_id);

-- 10. 전국 통계 (% only, 숫자 비노출)
create table if not exists edu_national_stats (
  id uuid default gen_random_uuid() primary key,
  quest_id uuid not null references edu_daily_quests(id) on delete cascade unique,
  pro_pct numeric(5,2) not null default 50.00,
  con_pct numeric(5,2) not null default 50.00,
  stance_changed_pct numeric(5,2) not null default 0.00,
  avg_confidence_before numeric(3,2),
  avg_confidence_after numeric(3,2),
  total_participants int not null default 0,
  updated_at timestamptz default now()
);

create index if not exists idx_edu_national_stats_quest on edu_national_stats(quest_id);

-- 11. 공유 카드
create table if not exists edu_share_cards (
  id uuid default gen_random_uuid() primary key,
  session_id uuid not null references edu_quest_sessions(id) on delete cascade unique,
  student_id uuid not null references edu_students(id) on delete cascade,
  quest_code text not null,
  quest_title text not null,
  initial_stance text not null check (initial_stance in ('pro', 'con')),
  final_stance text not null check (final_stance in ('pro', 'con')),
  stance_changed boolean not null default false,
  streak_days int not null default 0,
  tier_name text not null,
  national_changed_pct numeric(5,2),
  hero_sentence text,
  card_image_url text,
  share_hash text unique,
  views_count int not null default 0,
  created_at timestamptz default now()
);

create index if not exists idx_edu_share_cards_student on edu_share_cards(student_id);
create index if not exists idx_edu_share_cards_hash on edu_share_cards(share_hash);

-- 12. edu_quest_sessions에 드랍 스케줄 필드 추가
alter table edu_daily_quests
add column if not exists drop_schedule text[] default array['wed', 'sat', 'sun'],
add column if not exists drop_time time default '16:00:00',
add column if not exists live_at timestamptz,
add column if not exists expires_at timestamptz;

-- 인덱스 추가
create index if not exists idx_edu_quests_live on edu_daily_quests(live_at) where live_at is not null;

-- ============================================================
-- RPC: 전국 통계 갱신 함수
-- ============================================================
create or replace function refresh_edu_national_stats(p_quest_id uuid)
returns void as $$
declare
  v_total int;
  v_pro int;
  v_changed int;
begin
  select count(*) into v_total
  from edu_quest_sessions
  where quest_id = p_quest_id and stage = 'completed';
  
  if v_total = 0 then return; end if;
  
  select count(*) into v_pro
  from edu_quest_sessions s
  join edu_hypothesis_versions h on h.session_id = s.id and h.version = 2
  where s.quest_id = p_quest_id and s.stage = 'completed' and h.stance = 'pro';
  
  select count(*) into v_changed
  from edu_share_cards
  where quest_code in (select quest_code from edu_daily_quests where id = p_quest_id)
    and stance_changed = true;
  
  insert into edu_national_stats (quest_id, pro_pct, con_pct, stance_changed_pct, total_participants, updated_at)
  values (
    p_quest_id,
    round(v_pro::numeric / v_total * 100, 2),
    round((v_total - v_pro)::numeric / v_total * 100, 2),
    round(v_changed::numeric / v_total * 100, 2),
    v_total,
    now()
  )
  on conflict (quest_id) do update set
    pro_pct = excluded.pro_pct,
    con_pct = excluded.con_pct,
    stance_changed_pct = excluded.stance_changed_pct,
    total_participants = excluded.total_participants,
    updated_at = excluded.updated_at;
end;
$$ language plpgsql;
