-- GIST EDU — Mix-up source tracking on counter logs
alter table edu_counter_logs
  add column if not exists mixup_sources jsonb default '[]'::jsonb;
