-- GIST EDU — 부모 리포트 공개 공유 링크 (토큰 기반, 로그인 불필요)
-- Supabase SQL Editor에서 실행 (edu_* only)
--
-- 검증: php tools/edu_parent_report_share_static_test.php

create table if not exists edu_parent_report_shares (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references edu_students (id) on delete cascade,
  operator_id uuid references edu_operators (id) on delete set null,
  share_token text not null unique,
  report_snapshot jsonb not null default '{}'::jsonb,
  views_count integer not null default 0,
  is_active boolean not null default true,
  expires_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_edu_parent_report_shares_token
  on edu_parent_report_shares (share_token)
  where is_active = true;

create index if not exists idx_edu_parent_report_shares_student
  on edu_parent_report_shares (student_id, created_at desc);

comment on table edu_parent_report_shares is '부모 리포트 공개 URL — 링크 아는 사람만 조회';
comment on column edu_parent_report_shares.share_token is 'URL path token (32 hex chars, unguessable)';
comment on column edu_parent_report_shares.report_snapshot is '생성 시점 리포트 JSON (eduParentReportBuildPayload)';
