-- ============================================================
-- FA A/B 수동 검증: judgement_records.track (nullable)
-- Supabase Dashboard → SQL Editor → Run
-- 가드 0.4: 기존 row·INSERT( track 미전달 ) 무변경
-- ============================================================

alter table judgement_records
  add column if not exists track text null;

comment on column judgement_records.track is
  'Admin FA 프롬프트 트랙: A=현행, B=신규 FA. nullable — 기존·비FA 기록은 null.';

-- 선택: A/B만 허용 (null 허용)
alter table judgement_records
  drop constraint if exists judgement_records_track_check;

alter table judgement_records
  add constraint judgement_records_track_check
  check (track is null or track in ('A', 'B'));
