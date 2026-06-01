<?php
/**
 * Search analysis — optional external intel snippets (Option A: evidence cards only)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/backend/autoload.php';
require_once dirname(__DIR__, 3) . '/src/agents/autoload.php';

use App\Services\IntelligenceEmbeddingService;
use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

/**
 * @return list<array{source_api: string, snippet: string, similarity: float, article_id: int}>
 */
function searchAnalysisFetchExternalIntel(string $queryText, array $config): array
{
    if (empty($config['external_intel_enabled'])) {
        return [];
    }

    $openai = new OpenAIService(require dirname(__DIR__, 3) . '/config/openai.php');
    $embedder = new IntelligenceEmbeddingService($openai, new SupabaseService([]));
    if (!$embedder->isConfigured()) {
        return [];
    }

    $searchText = mb_substr(trim($queryText), 0, 2000);
    if ($searchText === '') {
        return [];
    }

    try {
        $hits = $embedder->search($searchText, [
            'match_count' => (int) ($config['external_intel_max'] ?? 3) * 2,
            'min_relevance' => 50,
        ]);
    } catch (Throwable $e) {
        error_log('[search-analysis] external intel: ' . $e->getMessage());
        return [];
    }

    $minSim = (float) ($config['external_similarity_min'] ?? 0.55);
    $max = (int) ($config['external_intel_max'] ?? 3);
    $results = [];
    $seen = [];

    foreach ($hits as $hit) {
        $articleId = (int) ($hit['article_id'] ?? 0);
        $similarity = (float) ($hit['semantic_similarity'] ?? $hit['final_score'] ?? 0);
        if ($similarity < $minSim || $articleId <= 0 || isset($seen[$articleId])) {
            continue;
        }
        $seen[$articleId] = true;
        $meta = $hit['metadata'] ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }
        $sourceApi = (string) ($meta['source_api'] ?? 'external');
        $snippet = mb_substr(trim((string) ($hit['chunk_text'] ?? '')), 0, 400);
        if ($snippet === '') {
            continue;
        }
        $results[] = [
            'source_api' => $sourceApi,
            'snippet' => $snippet,
            'similarity' => round($similarity, 3),
            'article_id' => $articleId,
        ];
        if (count($results) >= $max) {
            break;
        }
    }

    return $results;
}
