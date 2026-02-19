-- ============================================================
-- Supabase pgvector Schema for AI Training Workspace
-- Run this in the Supabase SQL Editor.
-- ============================================================

-- 1. Enable pgvector extension
create extension if not exists vector;

-- 2. Conversations (chat sessions)
create table if not exists conversations (
  id uuid default gen_random_uuid() primary key,
  article_url text,
  news_id int,
  admin_user text not null default 'admin',
  title text,
  context jsonb default '{}',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- 3. Messages (chat messages within a conversation)
create table if not exists messages (
  id uuid default gen_random_uuid() primary key,
  conversation_id uuid references conversations(id) on delete cascade,
  role text not null check (role in ('system','user','assistant')),
  content text not null,
  metadata jsonb default '{}',
  created_at timestamptz default now()
);
create index if not exists idx_messages_conv on messages(conversation_id, created_at);

-- 4. Critiques (editor feedback linked to articles)
create table if not exists critiques (
  id uuid default gen_random_uuid() primary key,
  news_id int,
  article_url text,
  article_title text,
  critique_text text not null,
  critique_type text default 'general',
  editor_notes jsonb default '{}',
  version int default 1,
  parent_id uuid references critiques(id),
  created_at timestamptz default now()
);
create index if not exists idx_critiques_news on critiques(news_id);

-- 5. Critique embeddings (for RAG retrieval)
create table if not exists critique_embeddings (
  id uuid default gen_random_uuid() primary key,
  critique_id uuid references critiques(id) on delete cascade,
  chunk_text text not null,
  embedding vector(1536),
  metadata jsonb default '{}',
  created_at timestamptz default now()
);

-- 6. Analysis embeddings (GPT analysis for RAG retrieval)
create table if not exists analysis_embeddings (
  id uuid default gen_random_uuid() primary key,
  news_id int,
  article_url text,
  chunk_text text not null,
  chunk_type text default 'analysis',
  embedding vector(1536),
  metadata jsonb default '{}',
  created_at timestamptz default now()
);

-- 7. Media cache (avoid repeated API costs)
create table if not exists media_cache (
  id uuid default gen_random_uuid() primary key,
  news_id int,
  media_type text check (media_type in ('thumbnail','tts')),
  file_url text not null,
  generation_params jsonb default '{}',
  created_at timestamptz default now()
);
create index if not exists idx_media_cache_news on media_cache(news_id, media_type);

-- 8. Analysis Feedback (Admin critique → GPT revision 무한 루프)
create table if not exists analysis_feedback (
  id uuid default gen_random_uuid() primary key,
  article_id int,                        -- MySQL news.id
  article_url text,
  revision_number int default 1,         -- 1=초안, 2=1차 수정, 3=...

  -- Admin 피드백
  admin_comment text,                    -- Admin이 쓴 코멘트
  score int check (score between 1 and 10),  -- 품질 점수 (1~10)

  -- GPT 분석 (초안 또는 수정본)
  gpt_analysis jsonb default '{}',       -- {news_title, content_summary, key_points, narration}

  -- GPT 재분석 (Admin 피드백 반영)
  gpt_revision jsonb,                    -- 재분석 결과
  revision_prompt text,                  -- 재분석 시 사용된 프롬프트

  -- 메타
  status text default 'draft' check (status in ('draft','reviewed','revised','approved')),
  parent_id uuid references analysis_feedback(id),
  created_at timestamptz default now()
);
create index if not exists idx_feedback_article on analysis_feedback(article_id);
create index if not exists idx_feedback_url on analysis_feedback(article_url);
create index if not exists idx_feedback_status on analysis_feedback(status);

-- 9. Knowledge Library (Layer 3: 정책/이론/역사 프레임워크)
create table if not exists knowledge_library (
  id uuid default gen_random_uuid() primary key,
  category text not null,                -- 'ir_theory', 'geopolitics', 'economics', 'history'
  framework_name text not null,          -- 'realism', 'liberalism', 'constructivism'
  title text not null,
  content text not null,                 -- 프레임워크 설명/원칙
  keywords text[] default '{}',          -- 검색용 키워드 배열
  embedding vector(1536),                -- RAG 검색용
  source text,                           -- 출처 (교과서, 논문 등)
  created_at timestamptz default now()
);
create index if not exists idx_knowledge_category on knowledge_library(category);
create index if not exists idx_knowledge_framework on knowledge_library(framework_name);
create index if not exists idx_knowledge_emb_hnsw
  on knowledge_library using hnsw (embedding vector_cosine_ops)
  with (m = 16, ef_construction = 64);

-- ============================================================
-- Additional performance indexes
-- ============================================================
create index if not exists idx_conversations_admin on conversations(admin_user, created_at desc);
create index if not exists idx_conversations_article on conversations(article_url);
create index if not exists idx_critiques_url on critiques(article_url);
create index if not exists idx_analysis_emb_news on analysis_embeddings(news_id);
create index if not exists idx_analysis_emb_url on analysis_embeddings(article_url);
create index if not exists idx_critique_emb_critique on critique_embeddings(critique_id);
create index if not exists idx_media_cache_type_hash on media_cache(media_type, ((generation_params->>'hash')));

-- ============================================================
-- Vector search indexes (ivfflat / HNSW)
--
-- ivfflat: 빠르지만 데이터 삽입 후에 CREATE 해야 합니다.
--          lists 값은 sqrt(행수) 근사치. 1000행 → lists=32
-- HNSW:   삽입 시에도 인덱스가 유지되며, 소량 데이터에서도 동작.
--          PostgreSQL 16 / pgvector 0.5+ 필요.
--
-- 프로젝트에 맞는 인덱스 하나를 선택하세요.
-- 초기에는 HNSW 권장 (데이터 적을 때도 안전).
-- ============================================================

-- Option A: HNSW (권장 — 데이터 적어도 안전, 삽입/검색 모두 양호)
create index if not exists idx_critique_emb_hnsw
  on critique_embeddings using hnsw (embedding vector_cosine_ops)
  with (m = 16, ef_construction = 64);

create index if not exists idx_analysis_emb_hnsw
  on analysis_embeddings using hnsw (embedding vector_cosine_ops)
  with (m = 16, ef_construction = 64);

-- Option B: ivfflat (데이터 1000건+ 후 활성화)
-- 이미 HNSW가 있으면 ivfflat은 불필요합니다.
-- create index if not exists idx_critique_emb_ivfflat
--   on critique_embeddings using ivfflat (embedding vector_cosine_ops) with (lists = 32);
-- create index if not exists idx_analysis_emb_ivfflat
--   on analysis_embeddings using ivfflat (embedding vector_cosine_ops) with (lists = 32);

-- ============================================================
-- RPC functions for vector similarity search
-- ============================================================

-- Match critique embeddings by cosine similarity
create or replace function match_critique_embeddings(
  query_embedding vector(1536),
  match_count int default 5
)
returns table (
  id uuid,
  critique_id uuid,
  chunk_text text,
  metadata jsonb,
  similarity float
)
language plpgsql
as $$
begin
  return query
    select
      ce.id,
      ce.critique_id,
      ce.chunk_text,
      ce.metadata,
      1 - (ce.embedding <=> query_embedding) as similarity
    from critique_embeddings ce
    order by ce.embedding <=> query_embedding
    limit match_count;
end;
$$;

-- Match analysis embeddings by cosine similarity
create or replace function match_analysis_embeddings(
  query_embedding vector(1536),
  match_count int default 5
)
returns table (
  id uuid,
  news_id int,
  article_url text,
  chunk_text text,
  chunk_type text,
  metadata jsonb,
  similarity float
)
language plpgsql
as $$
begin
  return query
    select
      ae.id,
      ae.news_id,
      ae.article_url,
      ae.chunk_text,
      ae.chunk_type,
      ae.metadata,
      1 - (ae.embedding <=> query_embedding) as similarity
    from analysis_embeddings ae
    order by ae.embedding <=> query_embedding
    limit match_count;
end;
$$;

-- TTS media_cache 조회 (hash 기반, PostgREST JSONB 필터 대체)
create or replace function get_tts_cache_by_hash(p_hash text)
returns table (file_url text, generation_params jsonb)
language sql stable
as $$
  select mc.file_url, mc.generation_params
  from media_cache mc
  where mc.media_type = 'tts' and (mc.generation_params->>'hash') = p_hash
  limit 1;
$$;

-- Match knowledge library by cosine similarity
create or replace function match_knowledge_library(
  query_embedding vector(1536),
  match_count int default 3
)
returns table (
  id uuid,
  category text,
  framework_name text,
  title text,
  content text,
  similarity float
)
language plpgsql
as $$
begin
  return query
    select
      kl.id,
      kl.category,
      kl.framework_name,
      kl.title,
      kl.content,
      1 - (kl.embedding <=> query_embedding) as similarity
    from knowledge_library kl
    where kl.embedding is not null
    order by kl.embedding <=> query_embedding
    limit match_count;
end;
$$;
