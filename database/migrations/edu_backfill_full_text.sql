-- GIST EDU — backfill edu_writing_drafts.full_text (Supabase SQL Editor)
-- Run AFTER edu_essay_artifact.sql + edu_storage_hardening.sql
-- Safe to re-run: only touches rows where full_text is null or empty

-- 0) 진단: 어떤 세션이 비어 있는지
select
  s.id as session_id,
  d.id as draft_id,
  length(coalesce(d.full_text, '')) as full_text_len,
  nullif(trim(s.blueprint_json->'essay_artifact'->>'full_text'), '') is not null as has_blueprint_full_text,
  jsonb_array_length(coalesce(d.v2_sentences, '[]'::jsonb)) as v2_count
from edu_quest_sessions s
left join edu_writing_drafts d on d.session_id = s.id
where s.stage = 'completed'
  and (d.full_text is null or d.full_text = '')
order by s.completed_at desc nulls last;

-- 1) UPDATE — draft 행이 있는 completed 세션
with session_bp as (
  select
    s.id as session_id,
    s.student_id,
    d.id as draft_id,
    d.v2_sentences,
    d.essay_structure as existing_essay_structure,
    d.hero_sentence as existing_hero,
    coalesce(
      nullif(s.blueprint_json, '{}'::jsonb),
      s.hammer_payload->'blueprint',
      '{}'::jsonb
    ) as bp
  from edu_quest_sessions s
  join edu_writing_drafts d on d.session_id = s.id
  where s.stage = 'completed'
    and (d.full_text is null or d.full_text = '')
),
targets as (
  select
    session_id,
    draft_id,
    coalesce(
      nullif(trim(bp->'essay_artifact'->>'full_text'), ''),
      nullif(trim((
        select string_agg(elem, E'\n\n' order by ord)
        from jsonb_array_elements_text(coalesce(v2_sentences, '[]'::jsonb))
          with ordinality as t(elem, ord)
      )), ''),
      nullif(trim((
        select concat_ws(
          E'\n\n',
          nullif(trim(v.scqa_situation), ''),
          nullif(trim(v.scqa_complication), ''),
          nullif(trim(v.scqa_question), ''),
          nullif(trim(v.scqa_answer), ''),
          nullif(trim(v.conclusion), '')
        )
        from edu_writing_versions v
        where v.session_id = session_bp.session_id
        order by v.version desc
        limit 1
      )), '')
    ) as new_full_text,
    coalesce(
      nullif(existing_essay_structure, '{}'::jsonb),
      jsonb_build_object(
        'title', coalesce(bp->'essay_artifact'->>'title', ''),
        'subtitle', coalesce(bp->'essay_artifact'->>'subtitle', ''),
        'structure', coalesce(bp->'essay_structure', '{}'::jsonb),
        'sections', coalesce(bp->'essay_artifact'->'sections', '[]'::jsonb),
        'conclusion_heading', coalesce(bp->'essay_artifact'->>'conclusion_heading', '결론'),
        'conclusion_paragraphs', coalesce(bp->'essay_artifact'->'conclusion_paragraphs', '[]'::jsonb)
      )
    ) as new_essay_structure,
    coalesce(
      nullif(trim(existing_hero), ''),
      nullif(trim(bp->'essay_artifact'->>'hero_sentence'), ''),
      left(coalesce(
        nullif(trim(bp->'essay_artifact'->>'full_text'), ''),
        nullif(trim((
          select string_agg(elem, E'\n\n' order by ord)
          from jsonb_array_elements_text(coalesce(v2_sentences, '[]'::jsonb))
            with ordinality as t(elem, ord)
        )), '')
      ), 80)
    ) as new_hero
  from session_bp
)
update edu_writing_drafts d
set
  full_text = t.new_full_text,
  essay_structure = t.new_essay_structure,
  hero_sentence = coalesce(t.new_hero, d.hero_sentence),
  updated_at = now()
from targets t
where d.id = t.draft_id
  and t.new_full_text is not null
  and t.new_full_text <> '';

-- 2) INSERT — completed인데 draft 행 자체가 없는 세션 (있을 경우)
insert into edu_writing_drafts (
  session_id,
  student_id,
  v1_sentences,
  v2_sentences,
  full_text,
  essay_structure,
  hero_sentence,
  stance_delta,
  updated_at
)
select
  s.id,
  s.student_id,
  coalesce(
    case when s.blueprint_json->'essay_artifact'->'sections' is not null
      then '[]'::jsonb else '[]'::jsonb end,
    '[]'::jsonb
  ),
  '[]'::jsonb,
  nullif(trim(s.blueprint_json->'essay_artifact'->>'full_text'), ''),
  jsonb_build_object(
    'title', coalesce(s.blueprint_json->'essay_artifact'->>'title', ''),
    'subtitle', coalesce(s.blueprint_json->'essay_artifact'->>'subtitle', ''),
    'structure', coalesce(s.blueprint_json->'essay_structure', '{}'::jsonb),
    'sections', coalesce(s.blueprint_json->'essay_artifact'->'sections', '[]'::jsonb),
    'conclusion_heading', coalesce(s.blueprint_json->'essay_artifact'->>'conclusion_heading', '결론'),
    'conclusion_paragraphs', coalesce(s.blueprint_json->'essay_artifact'->'conclusion_paragraphs', '[]'::jsonb)
  ),
  left(coalesce(s.blueprint_json->'essay_artifact'->>'hero_sentence', s.blueprint_json->'essay_artifact'->>'full_text'), 80),
  'unchanged',
  now()
from edu_quest_sessions s
left join edu_writing_drafts d on d.session_id = s.id
where s.stage = 'completed'
  and d.id is null
  and nullif(trim(s.blueprint_json->'essay_artifact'->>'full_text'), '') is not null;

-- 3) 검증
select count(*) as missing_full_text_count
from edu_quest_sessions s
left join edu_writing_drafts d on d.session_id = s.id
where s.stage = 'completed'
  and (d.full_text is null or d.full_text = '');
