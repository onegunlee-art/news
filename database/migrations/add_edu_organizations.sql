-- ============================================================
-- GIST EDU — Organizations (Phase 2: schema only)
-- Supabase SQL Editor에서 수동 실행
--
-- 범위: edu_organizations 신설 + nullable FK 컬럼만 추가
-- 안 함: org 스코핑, operator 필터, admin UI (Phase 3–4)
-- 본체 MySQL users/news 변경 없음
--
-- 실행 후: php tools/edu_org_phase2_verify.php
-- ============================================================

-- 1) Organizations (학원/학교 통합 — type으로 구분)
create table if not exists edu_organizations (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  type text not null check (type in ('academy', 'school')),
  slug text not null unique,
  metadata jsonb not null default '{}'::jsonb,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);

create index if not exists idx_edu_organizations_slug on edu_organizations (slug);
create index if not exists idx_edu_organizations_type on edu_organizations (type);
create index if not exists idx_edu_organizations_active on edu_organizations (is_active)
  where is_active = true;

comment on table edu_organizations is 'EDU B2B org — academy|school; Phase 2 schema only';
comment on column edu_organizations.type is 'academy = 사설학원, school = 학교/교육청';

-- 2) Students — nullable org (기존·게스트·미배정 학생 NULL 유지)
alter table edu_students
  add column if not exists organization_id uuid references edu_organizations (id) on delete set null;

create index if not exists idx_edu_students_organization on edu_students (organization_id)
  where organization_id is not null;

comment on column edu_students.organization_id is 'Phase 2: nullable; NULL = 미배정, 기존 기능 영향 없음';

-- 3) Operators — nullable org/role (기존 test@edu.com 등 Phase 2에서 NULL 허용)
alter table edu_operators
  add column if not exists organization_id uuid references edu_organizations (id) on delete set null;

alter table edu_operators
  add column if not exists role text;

-- role check: NULL 허용 (레거시 operator), 값 있으면 owner|teacher만
do $$
begin
  if not exists (
    select 1 from pg_constraint
    where conname = 'edu_operators_role_check'
  ) then
    alter table edu_operators
      add constraint edu_operators_role_check
      check (role is null or role in ('owner', 'teacher'));
  end if;
end $$;

create index if not exists idx_edu_operators_organization on edu_operators (organization_id)
  where organization_id is not null;

comment on column edu_operators.organization_id is 'Phase 4에서 NOT NULL + 스코핑; Phase 2 NULL 허용';
comment on column edu_operators.role is 'owner=원장, teacher=교사; Phase 2 NULL 허용';

-- ============================================================
-- (선택) Pilot cohort → org backfill — Phase 2에서 기본 skip
-- 필요 시 주석 해제 후 1회 실행
-- ============================================================
-- insert into edu_organizations (name, type, slug, metadata)
-- select '파일럿 학원 1', 'academy', 'pilot-01', '{}'::jsonb
-- where not exists (select 1 from edu_organizations where slug = 'pilot-01');
--
-- update edu_students s
-- set organization_id = o.id
-- from edu_pilot_cohorts c
-- join edu_organizations o on o.slug = c.slug
-- where s.cohort_id = c.id
--   and s.organization_id is null;
