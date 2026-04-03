<?php
/**
 * RAG Metadata Enrichment API – Admin-only batch enrichment
 *
 * GET              → 현재 상태 (total / enriched / missing)
 * POST { batch }   → batch 건만큼 metadata 추출·보강 후 결과 반환
 *
 * 브라우저에서 호출:
 *   GET  https://www.thegist.co.kr/api/admin/enrich-metadata.php
 *   POST https://www.thegist.co.kr/api/admin/enrich-metadata.php  {"batch":10}
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(600);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Project root & env ─────────────────────────────────

function findProjectRoot(): string {
    $rawCandidates = [__DIR__.'/../../../', __DIR__.'/../../', __DIR__.'/../'];
    foreach ($rawCandidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
        if (file_exists($dir . '/src/agents/autoload.php')) return rtrim($dir, '/\\') . '/';
    }
    throw new \RuntimeException('Project root not found');
}

function loadEnvFile(string $path): bool {
    if (!is_file($path) || !is_readable($path)) return false;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') { putenv("$name=$value"); $_ENV[$name] = $value; }
        }
    }
    return true;
}

try {
    $projectRoot = findProjectRoot();
} catch (\Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production', dirname($projectRoot) . '/.env'] as $f) {
    if (loadEnvFile($f)) break;
}

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

$openai   = new OpenAIService([]);
$supabase = new SupabaseService([]);

if (!$supabase->isConfigured() || !$openai->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Supabase or OpenAI not configured']);
    exit;
}

// ── GET: 상태 조회 ─────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $total = 0;
    $enriched = 0;
    $shortLabels = 0;
    $offset = 0;
    while (true) {
        $rows = $supabase->select(
            'analysis_embeddings',
            'select=id,metadata&order=created_at.asc&offset=' . $offset,
            100
        );
        if (!$rows || $rows === []) break;
        foreach ($rows as $r) {
            $total++;
            $m = $r['metadata'] ?? [];
            $label = is_array($m) ? trim($m['topic_label'] ?? '') : '';
            if ($label !== '') {
                $enriched++;
                if (mb_strlen($label) < 10) {
                    $shortLabels++;
                }
            }
        }
        $offset += count($rows);
        if (count($rows) < 100) break;
    }
    ob_clean();
    echo json_encode([
        'success'      => true,
        'total'        => $total,
        'enriched'     => $enriched,
        'missing'      => $total - $enriched,
        'short_labels' => $shortLabels,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── POST: 배치 보강 ────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET or POST only']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?: [];
$batchSize = max(1, min(50, (int) ($input['batch'] ?? 10)));
$reEnrichShort = !empty($input['re_enrich_short']);

$results = [];
$processed = 0;
$updated   = 0;
$skipped   = 0;
$failed    = 0;
$offset    = 0;

while ($processed < $batchSize) {
    $rows = $supabase->select(
        'analysis_embeddings',
        'select=id,chunk_text,metadata&order=created_at.asc&offset=' . $offset,
        100
    );
    if (!$rows || $rows === []) break;

    foreach ($rows as $row) {
        if ($processed >= $batchSize) break;

        $id   = $row['id'] ?? '';
        $text = (string) ($row['chunk_text'] ?? '');
        $meta = $row['metadata'] ?? [];
        if (!is_array($meta)) $meta = [];

        $existingLabel = trim($meta['topic_label'] ?? '');
        if ($reEnrichShort) {
            if ($existingLabel === '' || mb_strlen($existingLabel) >= 10) continue;
        } else {
            if ($existingLabel !== '') continue;
        }
        if ($id === '' || trim($text) === '') { $skipped++; $processed++; continue; }

        try {
            $enriched = $openai->extractRagChunkMetadata($text);
        } catch (\Throwable $e) {
            $results[] = ['id' => $id, 'status' => 'error', 'message' => $e->getMessage()];
            $failed++;
            $processed++;
            continue;
        }

        if ($enriched === []) {
            $results[] = ['id' => $id, 'status' => 'skip-empty'];
            $skipped++;
            $processed++;
            continue;
        }

        $merged = array_merge($meta, $enriched);
        $patch  = $supabase->update(
            'analysis_embeddings',
            'id=eq.' . rawurlencode((string) $id),
            ['metadata' => $merged]
        );

        if ($patch === null) {
            $results[] = ['id' => $id, 'status' => 'fail', 'message' => $supabase->getLastError()];
            $failed++;
        } else {
            $results[] = ['id' => $id, 'status' => 'ok', 'metadata' => $enriched];
            $updated++;
        }
        $processed++;
        usleep(200000);
    }

    $offset += count($rows);
    if (count($rows) < 100) break;
}

ob_clean();
echo json_encode([
    'success'   => true,
    'processed' => $processed,
    'updated'   => $updated,
    'skipped'   => $skipped,
    'failed'    => $failed,
    'results'   => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
