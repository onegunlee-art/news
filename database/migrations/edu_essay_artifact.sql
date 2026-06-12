-- GIST EDU — structured essay storage (Supabase SQL Editor)
ALTER TABLE edu_writing_drafts
  ADD COLUMN IF NOT EXISTS full_text text,
  ADD COLUMN IF NOT EXISTS essay_structure jsonb DEFAULT '{}';
