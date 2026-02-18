-- GPT Persona 시스템
-- Supabase SQL Editor에서 실행
-- 페르소나 Playground에서 정의한 system prompt 저장

create table if not exists gpt_personas (
  id uuid default gen_random_uuid() primary key,
  name text not null default 'The Gist 수석 에디터',
  system_prompt text not null,
  meta jsonb default '{}',
  is_active boolean not null default false,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists idx_gpt_personas_active on gpt_personas(is_active) where is_active = true;

comment on table gpt_personas is 'GPT 페르소나 (Playground에서 정의, AnalysisAgent system prompt로 사용)';
comment on column gpt_personas.name is '페르소나 이름';
comment on column gpt_personas.system_prompt is 'GPT system prompt';
comment on column gpt_personas.meta is 'source_conversation_id, extracted_at, version 등';
comment on column gpt_personas.is_active is '현재 서비스에 적용 여부 (1개만 true)';
