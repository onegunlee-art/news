-- GIST EDU Conversation-to-Essay engine
-- Supabase SQL Editor에서 실행

alter table edu_quest_sessions
  add column if not exists blueprint_json jsonb default '{}',
  add column if not exists dialogue_json jsonb default '[]';

alter table edu_quest_sessions drop constraint if exists edu_quest_sessions_stage_check;
alter table edu_quest_sessions add constraint edu_quest_sessions_stage_check
  check (stage in (
    'commit', 'reasoning', 'evidence', 'hammer', 'reflection',
    'writing', 'compose', 'growth', 'completed', 'abandoned'
  ));

alter table edu_thinking_logs drop constraint if exists edu_thinking_logs_agent_role_check;
alter table edu_thinking_logs add constraint edu_thinking_logs_agent_role_check
  check (agent_role in ('socratic', 'hammer', 'reflection', 'writing', 'director', 'composer'));

alter table edu_quest_articles
  add column if not exists scqa_snapshot jsonb default '{}';
