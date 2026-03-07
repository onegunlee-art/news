<?php
/**
 * Knowledge Library 시드 데이터 로드
 * database/seeds/knowledge_library_seed.json 을 읽어 OpenAI 임베딩 생성 후 Supabase knowledge_library에 삽입.
 * 브라우저 또는 CLI에서 1회 실행: GET 또는 POST (action=run)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'run';
} else {
    $seedKey = $_ENV['SEED_KNOWLEDGE_KEY'] ?? getenv('SEED_KNOWLEDGE_KEY') ?: 'seed-' . date('Ymd');
    $givenKey = $_GET['key'] ?? '';
    if ($givenKey !== $seedKey) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing key',
            'hint' => 'Use ?key=YOUR_SECRET&action=run (default key: seed-YYYYMMDD, e.g. seed-' . date('Ymd') . ')',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function findProjectRoot(): string {
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
    throw new \RuntimeException('Project root not found');
}

function loadEnvFile(string $path): bool {
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
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

try {
    $projectRoot = findProjectRoot();
} catch (\Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production'] as $f) {
    if (loadEnvFile($f)) {
        break;
    }
}

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

$seedPath = $projectRoot . 'database/seeds/knowledge_library_seed.json';
if (!file_exists($seedPath) || !is_readable($seedPath)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Seed file not found: database/seeds/knowledge_library_seed.json'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$raw = file_get_contents($seedPath);
$items = json_decode($raw, true);
if (!is_array($items)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid JSON in seed file'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$supabaseConfig = file_exists($projectRoot . 'config/supabase.php') ? require $projectRoot . 'config/supabase.php' : [];
$openaiConfig = file_exists($projectRoot . 'config/openai.php') ? require $projectRoot . 'config/openai.php' : [];
if (file_exists($projectRoot . 'config/agents.php')) {
    $agentsConfig = require $projectRoot . 'config/agents.php';
    $openaiConfig = array_merge($openaiConfig, $agentsConfig['agents']['analysis'] ?? []);
}

$openai = new OpenAIService($openaiConfig);
$supabase = new SupabaseService($supabaseConfig);

if (!$supabase->isConfigured() || !$openai->isConfigured()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Supabase or OpenAI not configured. Check config/supabase.php and config/openai.php (or env).',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$inserted = 0;
$errors = [];

foreach ($items as $idx => $item) {
    $category = trim((string) ($item['category'] ?? ''));
    $frameworkName = trim((string) ($item['framework_name'] ?? ''));
    $title = trim((string) ($item['title'] ?? ''));
    $content = trim((string) ($item['content'] ?? ''));
    $keywords = isset($item['keywords']) && is_array($item['keywords']) ? $item['keywords'] : [];
    $source = trim((string) ($item['source'] ?? ''));

    if ($title === '' || $content === '') {
        $errors[] = "Item {$idx}: title or content empty, skipped.";
        continue;
    }

    $textForEmbedding = $title . "\n\n" . $content;
    if (!empty($keywords)) {
        $textForEmbedding .= "\n\n키워드: " . implode(', ', $keywords);
    }

    $embedding = null;
    try {
        if (!$openai->isMockMode()) {
            $embedding = $openai->createEmbedding($textForEmbedding);
        }
    } catch (\Throwable $e) {
        $errors[] = "Item {$idx} ({$title}): embedding failed - " . $e->getMessage();
    }

    $row = [
        'category' => $category,
        'framework_name' => $frameworkName,
        'title' => $title,
        'content' => $content,
        'keywords' => $keywords,
        'source' => $source !== '' ? $source : null,
    ];
    if ($embedding !== null && !empty($embedding)) {
        $row['embedding'] = $embedding;
    }

    try {
        $result = $supabase->insert('knowledge_library', $row);
        if ($result !== null && !empty($result[0]['id'])) {
            $inserted++;
        } else {
            $errors[] = "Item {$idx} ({$title}): insert failed - " . $supabase->getLastError();
        }
    } catch (\Throwable $e) {
        $errors[] = "Item {$idx} ({$title}): " . $e->getMessage();
    }
}

ob_clean();
echo json_encode([
    'success' => true,
    'inserted' => $inserted,
    'total' => count($items),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
