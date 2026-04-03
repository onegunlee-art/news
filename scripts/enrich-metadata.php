<?php
/**
 * analysis_embeddings.metadata에 topic_label 등 구조화 필드 일괄 보강.
 * 프로젝트 루트에서: php scripts/enrich-metadata.php
 *
 * 필요: .env에 Supabase·OpenAI 설정, OpenAIService가 gpt-4o-mini 호출 가능.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

echo "RAG metadata enrichment (analysis_embeddings)\n";

$openai = new OpenAIService([]);
$supabase = new SupabaseService([]);

if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured (url / service_role_key).\n");
    exit(1);
}
if (!$openai->isConfigured()) {
    fwrite(STDERR, "OpenAI not configured. Set OPENAI_API_KEY (or config/openai.php).\n");
    exit(1);
}

$pageSize = 100;
$offset = 0;
$scanned = 0;
$updated = 0;
$skipped = 0;
$failed = 0;

while (true) {
    $q = 'order=created_at.asc&offset=' . $offset;
    $rows = $supabase->select('analysis_embeddings', $q, $pageSize);
    if ($rows === null) {
        fwrite(STDERR, 'SELECT failed: ' . $supabase->getLastError() . "\n");
        exit(1);
    }
    if ($rows === []) {
        break;
    }

    foreach ($rows as $row) {
        $scanned++;
        $id = $row['id'] ?? '';
        $chunkText = (string) ($row['chunk_text'] ?? '');
        $meta = $row['metadata'] ?? [];
        if (!\is_array($meta)) {
            $meta = [];
        }

        $existingLabel = $meta['topic_label'] ?? '';
        if (\is_string($existingLabel) && trim($existingLabel) !== '') {
            continue;
        }

        if ($id === '' || trim($chunkText) === '') {
            $skipped++;
            continue;
        }

        $enriched = $openai->extractRagChunkMetadata($chunkText);
        if ($enriched === []) {
            echo '[skip-empty-extract] ' . (string) $id . "\n";
            $skipped++;
            usleep(100000);
            continue;
        }

        $merged = array_merge($meta, $enriched);
        $patch = $supabase->update(
            'analysis_embeddings',
            'id=eq.' . rawurlencode((string) $id),
            ['metadata' => $merged]
        );
        if ($patch === null) {
            fwrite(STDERR, '[fail] ' . (string) $id . ' ' . $supabase->getLastError() . "\n");
            $failed++;
        } else {
            echo '[ok] ' . (string) $id . "\n";
            $updated++;
        }
        usleep(100000);
    }

    $n = count($rows);
    $offset += $n;
    if ($n < $pageSize) {
        break;
    }
}

echo "\nSummary: rows_scanned={$scanned} updated={$updated} skipped={$skipped} failed={$failed}\n";
