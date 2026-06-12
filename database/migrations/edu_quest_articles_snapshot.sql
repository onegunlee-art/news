-- GIST EDU — quest article snapshot fields (READ from MySQL at quest creation time)
alter table edu_quest_articles
  add column if not exists excerpt text,
  add column if not exists why_important text,
  add column if not exists source_outlet text,
  add column if not exists published_at timestamptz;
