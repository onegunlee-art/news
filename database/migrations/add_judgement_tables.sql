-- ============================================================
-- Judgement Layer (관찰 모드) – Supabase SQL Editor에서 실행
-- ============================================================

-- 편집 판단 기록 (AI 원본 vs 에디터 최종 + 시맨틱 diff)
create table if not exists judgement_records (
  id uuid default gen_random_uuid() primary key,
  news_id int not null,
  ai_output jsonb not null default '{}',
  human_output jsonb not null default '{}',
  semantic_diff jsonb default '{}',
  source text not null default 'publish' check (source in ('publish', 'backfill')),
  created_at timestamptz default now()
);

create index if not exists idx_judgement_records_news on judgement_records(news_id);
create index if not exists idx_judgement_records_created on judgement_records(created_at desc);
create index if not exists idx_judgement_records_source on judgement_records(source);

-- 반복 패턴 집계 (frequency 기반 weight는 애플리케이션에서 계산)
create table if not exists judgement_patterns (
  id uuid default gen_random_uuid() primary key,
  pattern_hash text not null,
  category text not null,
  description text not null,
  ai_approach text,
  editor_correction text,
  frequency int not null default 1,
  weight float not null default 0.02,
  is_active boolean not null default true,
  last_seen_at timestamptz default now(),
  created_at timestamptz default now()
);

create unique index if not exists idx_judgement_patterns_hash on judgement_patterns(pattern_hash);
create index if not exists idx_judgement_patterns_weight on judgement_patterns(weight desc);
create index if not exists idx_judgement_patterns_active on judgement_patterns(is_active) where is_active = true;
