<?php
/**
 * GIST EDU Mix-up Phase 0 — READ-only diagnostic (no fixes)
 *
 * Verifies findMixUpPairs / search_intelligence_weighted field mismatches
 * and logs how many pairs are returned for sample topics.
 *
 * Usage:
 *   php tools/edu_mixup_phase0_diagnose.php
 *   php tools/edu_mixup_phase0_diagnose.php --topic "이란 전쟁"
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/src/backend/autoload.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use App\Services\IntelligenceEmbeddingService;
use Services\Edu\EduRagService;

$customTopic = null;
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if ($arg === '--topic' && isset($argv[$i + 1])) {
        $customTopic = (string) $argv[$i + 1];
    }
}

$topics = [
    '이란 전쟁을 끝낼 수 있는가 — 외교적 출구 vs 군사적 결말 불가',
    '대만 해협 위협 — 임박했는가 vs 지연되었는가',
    '미중 관계 — 중국 우위 강화 vs 기회 낭비',
];

if ($customTopic !== null && $customTopic !== '') {
    $topics = [$customTopic];
}

$intel = new IntelligenceEmbeddingService();
$rag = new EduRagService();

echo "=== Mix-up Phase 0 diagnostic (READ-only) ===\n";
echo 'timestamp: ' . date('c') . "\n";
echo 'branch: feature/mixup-stance (expected)' . "\n\n";

echo "--- Service configuration ---\n";
echo 'intelligence_configured: ' . ($intel->isConfigured() ? 'yes' : 'no') . "\n";
echo 'supabase_configured: ' . (eduSupabase()->isConfigured() ? 'yes' : 'no') . "\n\n";

if (!$intel->isConfigured()) {
    fwrite(STDERR, "IntelligenceEmbeddingService not configured (OpenAI + Supabase required).\n");
    exit(1);
}

$supabase = eduSupabase();

echo "--- Table READ sample (intelligence_embeddings, no embedding API) ---\n";
$tableRows = $supabase->select('intelligence_embeddings', 'order=created_at.desc', 5) ?? [];
echo 'table_row_count_sample: ' . count($tableRows) . "\n";
if ($tableRows !== []) {
    $t0 = $tableRows[0];
    echo 'table_first_row_keys: ' . implode(', ', array_keys($t0)) . "\n";
    echo 'table_first_row.source_api: ' . json_encode($t0['source_api'] ?? null) . "\n";
    $tm = $t0['metadata'] ?? [];
    if (is_string($tm)) {
        $tm = json_decode($tm, true) ?: [];
    }
    echo 'table_first_row.metadata.source_api: ' . json_encode($tm['source_api'] ?? null) . "\n";
    echo 'table_first_row.metadata.event_type: ' . json_encode($tm['event_type'] ?? null) . "\n";
}

$probeTopic = $topics[0];
$searchOptions = [
    'match_count' => 6,
    'min_relevance' => 55,
    'filter_topic' => $probeTopic,
];

echo "--- Bug check: RPC raw row shape (search_intelligence_weighted) ---\n";
echo 'probe_topic: ' . $probeTopic . "\n";
$rawRows = $intel->search($probeTopic, $searchOptions);
echo 'rpc_row_count: ' . count($rawRows) . "\n";

if ($rawRows === []) {
    echo "rpc_rows: (empty — filter_topic may be too strict for natural-language query)\n";
    $rawRows = $intel->search($probeTopic, [
        'match_count' => 6,
        'min_relevance' => 55,
    ]);
    echo 'rpc_row_count_without_topic_filter: ' . count($rawRows) . "\n";
}

if ($rawRows !== []) {
    $first = $rawRows[0];
    $keys = array_keys($first);
    sort($keys);
    echo 'rpc_first_row_keys: ' . implode(', ', $keys) . "\n";

    $phpExpects = ['similarity', 'relevance_score', 'source_api'];
    $rpcReturns = ['semantic_similarity', 'final_score'];
    echo "\nBug 1 — relevance field name mismatch:\n";
    echo '  PHP reads: similarity, relevance_score' . "\n";
    echo '  RPC returns: semantic_similarity, final_score' . "\n";
    echo '  first_row.similarity: ' . json_encode($first['similarity'] ?? null) . "\n";
    echo '  first_row.relevance_score: ' . json_encode($first['relevance_score'] ?? null) . "\n";
    echo '  first_row.semantic_similarity: ' . json_encode($first['semantic_similarity'] ?? null) . "\n";
    echo '  first_row.final_score: ' . json_encode($first['final_score'] ?? null) . "\n";

    $meta = $first['metadata'] ?? [];
    if (is_string($meta)) {
        $meta = json_decode($meta, true) ?: [];
    }

    echo "\nBug 2 — source_api missing at RPC top level:\n";
    echo '  first_row.source_api: ' . json_encode($first['source_api'] ?? null) . "\n";
    echo '  metadata.source_api: ' . json_encode($meta['source_api'] ?? null) . "\n";
    echo '  metadata.source (legacy): ' . json_encode($meta['source'] ?? null) . "\n";

    echo "\nBug 3 — RPC SELECT vs table columns:\n";
    echo '  RPC SELECT: id, article_id, chunk_text, metadata, semantic_similarity, final_score' . "\n";
    echo '  table has source_api column but RPC does not return it' . "\n";

    echo "\nSample raw rows (first 3):\n";
    foreach (array_slice($rawRows, 0, 3) as $idx => $row) {
        $m = $row['metadata'] ?? [];
        if (is_string($m)) {
            $m = json_decode($m, true) ?: [];
        }
        $excerpt = mb_substr((string) ($row['chunk_text'] ?? ''), 0, 80);
        echo sprintf(
            "  [%d] article_id=%s semantic=%s final=%s meta_source_api=%s excerpt=%s\n",
            $idx,
            (string) ($row['article_id'] ?? '?'),
            (string) ($row['semantic_similarity'] ?? 'null'),
            (string) ($row['final_score'] ?? 'null'),
            (string) ($m['source_api'] ?? 'null'),
            str_replace(["\n", "\r"], ' ', $excerpt)
        );
    }
} else {
    echo "rpc_rows: still empty after retry without filter_topic\n";
    if ($tableRows !== []) {
        echo "\n(static) Simulating findMixUpPairs mapping on table sample (RPC unavailable locally):\n";
        foreach (array_slice($tableRows, 0, 3) as $idx => $row) {
            $meta = $row['metadata'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $mappedRelevance = (float) ($row['similarity'] ?? $row['relevance_score'] ?? 0);
            $mappedSource = (string) ($row['source_api'] ?? $meta['source'] ?? 'external');
            echo sprintf(
                "  sim[%d] mapped_source=%s mapped_relevance=%s table_source_api=%s\n",
                $idx,
                $mappedSource,
                (string) $mappedRelevance,
                (string) ($row['source_api'] ?? 'null')
            );
        }
    }
}

echo "\n--- findMixUpPairs output per topic ---\n";
$summary = [];

foreach ($topics as $topic) {
    echo "\n[topic] {$topic}\n";
    $pairs = $rag->findMixUpPairs($topic, '', 3);
    $count = count($pairs);
    $summary[] = ['topic' => $topic, 'pair_count' => $count];

    echo "pair_count: {$count}\n";

    if ($pairs === []) {
        echo "pairs: (none)\n";
        continue;
    }

    $zeroRelevance = 0;
    $externalSource = 0;
    foreach ($pairs as $i => $p) {
        $rel = (float) ($p['relevance'] ?? 0);
        if ($rel <= 0.0) {
            $zeroRelevance++;
        }
        $src = (string) ($p['source'] ?? '');
        if ($src === 'external' || $src === '') {
            $externalSource++;
        }
        $excerpt = mb_substr((string) ($p['excerpt'] ?? ''), 0, 100);
        echo sprintf(
            "  pair[%d] source=%s relevance=%s week=%s excerpt=%s\n",
            $i,
            $src !== '' ? $src : '(empty)',
            (string) $rel,
            (string) ($p['week'] ?? ''),
            str_replace(["\n", "\r"], ' ', $excerpt)
        );
    }

    echo "pairs_with_relevance_zero: {$zeroRelevance}/{$count}\n";
    echo "pairs_with_source_external_or_empty: {$externalSource}/{$count}\n";
}

echo "\n--- Phase 0 summary ---\n";
echo json_encode([
    'rpc_configured' => $intel->isConfigured(),
    'bugs_observed' => [
        'relevance_field_mismatch' => 'PHP expects similarity/relevance_score; RPC returns semantic_similarity/final_score',
        'source_api_not_in_rpc' => 'source_api column exists on table but not in RPC return; fallback metadata.source_api or external',
        'rpc_schema_drift' => 'findMixUpPairs mapping does not align with search_intelligence_weighted contract',
    ],
    'topic_results' => $summary,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
