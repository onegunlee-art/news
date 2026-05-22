
-- Supabase: Intelligence embeddings (isolated from analysis_embeddings)
create extension if not exists vector;

create table if not exists intelligence_embeddings (
  id uuid default gen_random_uuid() primary key,
  article_id int not null,
  source_api text,
  chunk_text text not null,
  chunk_index smallint default 0,
  chunk_total smallint default 1,
  word_count smallint default 0,
  embedding vector(1536),
  metadata jsonb default '{}',
  created_at timestamptz default now()
);

create index if not exists idx_intel_emb_article on intelligence_embeddings(article_id);
create index if not exists idx_intel_emb_hnsw
  on intelligence_embeddings using hnsw (embedding vector_cosine_ops)
  with (m = 16, ef_construction = 64);

create or replace function search_intelligence_weighted(
  query_embedding vector(1536),
  filter_region text default null,
  filter_topic text default null,
  filter_week text default null,
  min_relevance int default 60,
  match_count int default 15
)
returns table (
  id uuid,
  article_id int,
  chunk_text text,
  metadata jsonb,
  semantic_similarity float,
  final_score float
)
language plpgsql
as $$
begin
  return query
  select
    ie.id,
    ie.article_id,
    ie.chunk_text,
    ie.metadata,
    (1 - (ie.embedding <=> query_embedding))::float as semantic_similarity,
    (
      (1 - (ie.embedding <=> query_embedding)) * 0.45 +
      coalesce((ie.metadata->>'relevance_score')::float / 100, 0.5) * 0.20 +
      case (ie.metadata->>'trust_tier')
        when 'A' then 1.0
        when 'B' then 0.7
        else 0.4
      end * 0.15 +
      case
        when (ie.metadata->>'published_at')::timestamptz > now() - interval '7 days' then 1.0
        when (ie.metadata->>'published_at')::timestamptz > now() - interval '14 days' then 0.5
        else 0.2
      end * 0.10 +
      case (ie.metadata->>'event_type')
        when 'sanction' then 0.9
        when 'export_control' then 0.9
        when 'military_action' then 1.0
        when 'treaty' then 0.8
        else 0.5
      end * 0.10
    )::float as final_score
  from intelligence_embeddings ie
  where
    (filter_region is null or ie.metadata->'region' ? filter_region)
    and (filter_topic is null or ie.metadata->'topic' ? filter_topic)
    and (filter_week is null or ie.metadata->>'week' = filter_week)
    and coalesce((ie.metadata->>'relevance_score')::int, 0) >= min_relevance
  order by final_score desc
  limit match_count;
end;
$$;

alter table intelligence_embeddings enable row level security;
