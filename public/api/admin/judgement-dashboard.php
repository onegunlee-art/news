<?php
/**
 * Judgement Dashboard API - 패턴 및 통계 조회
 *
 * GET → 패턴 목록 + 통계
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(30);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

use Agents\Services\SupabaseService;

$supabase = new SupabaseService([]);

if (!$supabase->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Supabase not configured']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET only']);
    exit;
}

// Fetch judgement records count
$recordsCount = 0;
$recordsResult = $supabase->select('judgement_records', 'select=id', 1000);
if (is_array($recordsResult)) {
    $recordsCount = count($recordsResult);
}

// Fetch all patterns
$patterns = $supabase->select('judgement_patterns', 'order=weight.desc,frequency.desc', 500) ?? [];

// Calculate stats
$totalPatterns = count($patterns);
$activePatterns = count(array_filter($patterns, fn($p) => ($p['is_active'] ?? false)));

// Top categories
$categoryCounts = [];
foreach ($patterns as $p) {
    $cat = $p['category'] ?? 'unknown';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
arsort($categoryCounts);
$topCategories = [];
foreach (array_slice($categoryCounts, 0, 5, true) as $cat => $count) {
    $topCategories[] = ['category' => $cat, 'count' => $count];
}

ob_clean();
echo json_encode([
    'success' => true,
    'stats' => [
        'total_records' => $recordsCount,
        'total_patterns' => $totalPatterns,
        'active_patterns' => $activePatterns,
        'top_categories' => $topCategories,
    ],
    'patterns' => $patterns,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
