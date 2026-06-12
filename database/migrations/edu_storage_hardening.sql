-- GIST EDU — essay draft storage hardening (Supabase SQL Editor)
-- Run after: add_edu_tables → edu_pilot_001 → edu_chat_engine → edu_essay_artifact

ALTER TABLE edu_writing_drafts
  ADD COLUMN IF NOT EXISTS student_edited boolean DEFAULT false;

COMMENT ON COLUMN edu_writing_drafts.full_text IS 'GIST EDU structured essay plain text';
COMMENT ON COLUMN edu_writing_drafts.essay_structure IS 'title/sections/conclusion JSON wrapper';
