<?php
/**
 * Judgement Layer 백필 – analysis_feedback.gpt_analysis + MySQL news 최종본으로 judgement_records 적재
 *
 * GET              → 통계 (후보 / 이미 처리됨 / 대기)
 * POST { batch }   → batch 건 처리 (기본 10, 최대 30)
 *
 * Supabase에 add_judgement_tables.sql 적용 후 사용.
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

function findProjectRoot(): string
{
    $rawCandidates = [__DIR__ . '/../../../', __DIR__ . '/../../', __DIR__ . '/../'];
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
        if (file_exists($dir . '/src/agents/autoload.php')) {
            return rtrim($dir, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found');
}

function loadEnvFile(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

try {
    $projectRoot = findProjectRoot();
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production', dirname($projectRoot) . '/.env'] as $f) {
    if (loadEnvFile($f)) {
        break;
    }
}

require_once $projectRoot . 'src/agents/autoload.php';
require_once $projectRoot . 'public/api/lib/storeJudgementRecord.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

$supabase = new SupabaseService([]);
$openai = new OpenAIService([]);

if (!$supabase->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Supabase not configured']);
    exit;
}

$dbConfigPath = $projectRoot . 'config/database.php';
if (!file_exists($dbConfigPath)) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'config/database.php not found']);
    exit;
}
$dbConfig = require $dbConfigPath;
$dbConfig['dbname'] = $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand';

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

/**
 * article_id -> analysis_feedback 행 (최대 revision_number 유지)
 *
 * @return array<int, array<string,mixed>>
 */
function judgementBackfillLoadBestFeedback(SupabaseService $supabase): array
{
    $best = [];
    $offset = 0;
    while ($offset < 100000) {
        $rows = $supabase->select(
            'analysis_feedback',
            'article_id=gte.1&select=article_id,gpt_analysis,revision_number&order=created_at.desc&offset=' . $offset,
            200
        );
        if ($rows === null || $rows === []) {
            break;
        }
        foreach ($rows as $r) {
            $aid = (int) ($r['article_id'] ?? 0);
            if ($aid < 1) {
                continue;
            }
            $ga = $r['gpt_analysis'] ?? null;
            if (!is_array($ga) || count($ga) === 0) {
                continue;
            }
            $rev = (int) ($r['revision_number'] ?? 0);
            if (!isset($best[$aid]) || $rev > (int) ($best[$aid]['revision_number'] ?? 0)) {
                $best[$aid] = $r;
            }
        }
        $offset += count($rows);
        if (count($rows) < 200) {
            break;
        }
    }
    return $best;
}

/**
 * @return array<int, true>
 */
function judgementBackfillLoadProcessedNewsIds(SupabaseService $supabase): array
{
    $set = [];
    $offset = 0;
    while ($offset < 100000) {
        $rows = $supabase->select('judgement_records', 'select=news_id&offset=' . $offset, 200);
        if ($rows === null || $rows === []) {
            break;
        }
        foreach ($rows as $r) {
            $nid = (int) ($r['news_id'] ?? 0);
            if ($nid > 0) {
                $set[$nid] = true;
            }
        }
        $offset += count($rows);
        if (count($rows) < 200) {
            break;
        }
    }
    return $set;
}

// ── GET: 통계 ───────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $best = judgementBackfillLoadBestFeedback($supabase);
    $processed = judgementBackfillLoadProcessedNewsIds($supabase);
    $eligible = count($best);
    $pending = 0;
    foreach (array_keys($best) as $aid) {
        if (!isset($processed[$aid])) {
            $pending++;
        }
    }
    ob_clean();
    echo json_encode([
        'success' => true,
        'eligible_with_gpt_analysis' => $eligible,
        'already_in_judgement_records' => count($processed),
        'pending_backfill' => $pending,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── POST: 배치 ───────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET or POST only']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];
$batchSize = max(1, min(30, (int) ($input['batch'] ?? 10)));

if (!$openai->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'OpenAI not configured (required for semantic diff)']);
    exit;
}

$best = judgementBackfillLoadBestFeedback($supabase);
$processed = judgementBackfillLoadProcessedNewsIds($supabase);

$pendingIds = [];
foreach (array_keys($best) as $aid) {
    if (!isset($processed[$aid])) {
        $pendingIds[] = $aid;
    }
}
sort($pendingIds);

$results = [];
$done = 0;
$skipped = 0;
$failed = 0;

foreach ($pendingIds as $newsId) {
    if ($done >= $batchSize) {
        break;
    }
    $row = $best[$newsId];
    $gpt = $row['gpt_analysis'] ?? null;
    if (!is_array($gpt) || count($gpt) === 0) {
        $skipped++;
        continue;
    }

    try {
        $stmt = $db->prepare('SELECT id, title, narration, why_important, content, status FROM news WHERE id = ? LIMIT 1');
        $stmt->execute([$newsId]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $results[] = ['news_id' => $newsId, 'status' => 'error', 'message' => $e->getMessage()];
        $failed++;
        continue;
    }

    if (!$news) {
        $results[] = ['news_id' => $newsId, 'status' => 'skip', 'message' => 'news not found'];
        $skipped++;
        continue;
    }
    if (($news['status'] ?? '') !== 'published') {
        $results[] = ['news_id' => $newsId, 'status' => 'skip', 'message' => 'not published'];
        $skipped++;
        continue;
    }

    try {
        storeJudgementRecord(
            $newsId,
            $gpt,
            [
                'title' => (string) ($news['title'] ?? ''),
                'narration' => $news['narration'] ?? null,
                'why_important' => $news['why_important'] ?? null,
                'content' => (string) ($news['content'] ?? ''),
            ],
            'backfill'
        );
        $results[] = ['news_id' => $newsId, 'status' => 'ok'];
        $done++;
    } catch (Throwable $e) {
        $results[] = ['news_id' => $newsId, 'status' => 'error', 'message' => $e->getMessage()];
        $failed++;
    }

    usleep(500000);
}

ob_clean();
echo json_encode([
    'success' => true,
    'batch_requested' => $batchSize,
    'processed_ok' => $done,
    'skipped' => $skipped,
    'failed' => $failed,
    'pending_before_this_batch' => count($pendingIds),
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
