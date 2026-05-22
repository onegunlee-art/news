<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

class IntelligenceEmbeddingService
{
    private OpenAIService $openai;
    private SupabaseService $supabase;

    public function __construct(?OpenAIService $openai = null, ?SupabaseService $supabase = null)
    {
        $this->openai = $openai ?? new OpenAIService([]);
        $this->supabase = $supabase ?? new SupabaseService([]);
    }

    public function isConfigured(): bool
    {
        return $this->openai->isConfigured() && $this->supabase->isConfigured();
    }

    public function deleteByArticleId(int $articleId): bool
    {
        if (!$this->supabase->isConfigured()) {
            return false;
        }
        return $this->supabase->delete('intelligence_embeddings', 'article_id=eq.' . $articleId);
    }

    /** @param array<int, array{index:int,total:int,text:string,word_count:int,has_overlap:bool}> $chunks */
    public function storeArticle(int $articleId, string $sourceApi, array $chunks, array $metadata): int
    {
        if (!$this->isConfigured() || $chunks === []) {
            return 0;
        }

        $this->deleteByArticleId($articleId);
        $stored = 0;
        foreach ($chunks as $chunk) {
            $text = trim((string) ($chunk['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            try {
                $embedding = $this->openai->createEmbedding($text);
            } catch (\Throwable $e) {
                error_log('IntelligenceEmbeddingService embedding error: ' . $e->getMessage());
                continue;
            }
            if ($embedding === []) {
                continue;
            }
            $row = [
                'article_id' => $articleId,
                'source_api' => $sourceApi,
                'chunk_text' => $text,
                'chunk_index' => (int) ($chunk['index'] ?? 0),
                'chunk_total' => (int) ($chunk['total'] ?? 1),
                'word_count' => (int) ($chunk['word_count'] ?? 0),
                'embedding' => $embedding,
                'metadata' => array_merge($metadata, [
                    'chunk_index' => (int) ($chunk['index'] ?? 0),
                    'chunk_total' => (int) ($chunk['total'] ?? 1),
                    'word_count' => (int) ($chunk['word_count'] ?? 0),
                    'has_overlap' => (bool) ($chunk['has_overlap'] ?? false),
                ]),
            ];
            $result = $this->supabase->insert('intelligence_embeddings', $row);
            if ($result !== null) {
                $stored++;
            }
        }
        return $stored;
    }

    public function search(string $query, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        try {
            $embedding = $this->openai->createEmbedding($query);
        } catch (\Throwable $e) {
            error_log('IntelligenceEmbeddingService search error: ' . $e->getMessage());
            return [];
        }
        if ($embedding === []) {
            return [];
        }
        $params = [
            'query_embedding' => $embedding,
            'match_count' => (int) ($options['match_count'] ?? 15),
            'min_relevance' => (int) ($options['min_relevance'] ?? 60),
        ];
        if (!empty($options['filter_region'])) {
            $params['filter_region'] = (string) $options['filter_region'];
        }
        if (!empty($options['filter_topic'])) {
            $params['filter_topic'] = (string) $options['filter_topic'];
        }
        if (!empty($options['filter_week'])) {
            $params['filter_week'] = (string) $options['filter_week'];
        }
        return $this->supabase->rpc('search_intelligence_weighted', $params) ?? [];
    }
}
