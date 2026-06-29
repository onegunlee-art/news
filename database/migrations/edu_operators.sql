-- GIST EDU — 운영자 계정 (부모 리포트 admin, 본체 users와 분리)
create table if not exists edu_operators (
  id uuid primary key default gen_random_uuid(),
  email text not null unique,
  password_hash text not null,
  display_name text not null default '',
  status text not null default 'active' check (status in ('active', 'disabled')),
  access_token_hash text,
  last_login_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_edu_operators_token on edu_operators (access_token_hash)
  where access_token_hash is not null;

comment on table edu_operators is 'EDU 운영자 — 리포트 admin 전용 (the gist users 테이블과 무관)';
