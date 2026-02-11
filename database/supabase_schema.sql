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
