-- TTS media_cache hash 기반 조회 RPC
-- PostgREST JSONB 필터 대체, 매체설명/오디오 불일치 방지
-- Supabase SQL Editor에서 실행

create or replace function get_tts_cache_by_hash(p_hash text)
returns table (file_url text, generation_params jsonb)
language sql stable
as $$
  select mc.file_url, mc.generation_params
  from media_cache mc
  where mc.media_type = 'tts' and (mc.generation_params->>'hash') = p_hash
  limit 1;
$$;
