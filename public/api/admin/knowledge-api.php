<?php
/**
 * Knowledge Library API – Layer 3: 정책/이론/역사 프레임워크 관리
 *
 * POST action=add_framework  – 이론/프레임워크 추가 + 임베딩 저장
 * GET  action=list           – 카테고리별 목록
 * POST action=delete         – 삭제
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(120);

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

foreach ([
    $projectRoot . 'env.txt', $projectRoot . '.env',
    $projectRoot . '.env.production', dirname($projectRoot) . '/.env',
] as $f) { if (loadEnvFile($f)) break; }

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

// ── Helpers ─────────────────────────────────────────────
function sendResponse(array $data, int $status = 200): void {
    ob_clean();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError(string $msg, int $status = 400): void {
    sendResponse(['success' => false, 'error' => $msg], $status);
}

// ── Init services ───────────────────────────────────────
$supabase = new SupabaseService([]);
$supabaseOk = $supabase->isConfigured();
$openai = new OpenAIService([]);

// Supabase 미설정 시: list는 빈 배열, add/delete는 친절한 에러
function requireSupabaseKn(): void {
    global $supabaseOk;
    if (!$supabaseOk) {
        ob_clean();
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'error' => 'Supabase가 설정되지 않았습니다. 이론 라이브러리를 사용하려면 SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY를 env에 추가하세요.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// ── Route ───────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list ───────────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        if (!$supabaseOk) {
            sendResponse(['success' => true, 'items' => []]);
        }
        $category = $_GET['category'] ?? null;

        $query = 'order=created_at.desc';
        if ($category !== null && $category !== '' && $category !== 'all') {
            $query = "category=eq." . urlencode($category) . "&{$query}";
        }

        // embedding 컬럼은 거대하므로 select에서 제외
        $query .= '&select=id,category,framework_name,title,content,keywords,source,created_at';

        $rows = $supabase->select('knowledge_library', $query, 200);
        sendResponse(['success' => true, 'items' => $rows ?? []]);
    }

    sendError('Unknown GET action');
}

// ── POST actions ────────────────────────────────────────
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) sendError('Invalid JSON');

    $action = $input['action'] ?? '';

    // ── add_framework ───────────────────────────────────
    if ($action === 'add_framework') {
        requireSupabaseKn();
        $category      = trim($input['category'] ?? '');
        $frameworkName  = trim($input['framework_name'] ?? '');
        $title         = trim($input['title'] ?? '');
        $content       = trim($input['content'] ?? '');
        $keywords      = $input['keywords'] ?? [];
        $source        = $input['source'] ?? null;

        if ($category === '') sendError('category required');
        if ($frameworkName === '') sendError('framework_name required');
        if ($title === '') sendError('title required');
        if ($content === '') sendError('content required');

        // 임베딩 생성 (title + content를 합쳐서)
        $textForEmbedding = "{$title}\n\n{$content}";
        if (!empty($keywords) && is_array($keywords)) {
            $textForEmbedding .= "\n\n키워드: " . implode(', ', $keywords);
        }

        $embedding = null;
        try {
            if ($openai->isConfigured() && !$openai->isMockMode()) {
                $embedding = $openai->createEmbedding($textForEmbedding);
            }
        } catch (\Throwable $e) {
            error_log('Knowledge library embedding error: ' . $e->getMessage());
            // 임베딩 실패해도 저장은 계속
        }

        // keywords를 PostgreSQL text[] 포맷으로 (Supabase PostgREST는 JSON array 지원)
        $row = [
            'category'       => $category,
            'framework_name' => $frameworkName,
            'title'          => $title,
            'content'        => $content,
            'keywords'       => is_array($keywords) ? $keywords : [],
            'source'         => $source,
        ];

        if ($embedding !== null && !empty($embedding)) {
            $row['embedding'] = $embedding;
        }

        $inserted = $supabase->insert('knowledge_library', $row);
        if (!$inserted || empty($inserted[0]['id'])) {
            sendError('Failed to add framework: ' . $supabase->getLastError(), 500);
        }

        sendResponse([
            'success'       => true,
            'item'          => [
                'id'             => $inserted[0]['id'],
                'category'       => $inserted[0]['category'],
                'framework_name' => $inserted[0]['framework_name'],
                'title'          => $inserted[0]['title'],
                'created_at'     => $inserted[0]['created_at'],
            ],
            'has_embedding' => $embedding !== null,
        ]);
    }

    // ── delete ──────────────────────────────────────────
    if ($action === 'delete') {
        requireSupabaseKn();
        $id = $input['id'] ?? '';
        if ($id === '') sendError('id required');

        $deleted = $supabase->delete('knowledge_library', "id=eq.{$id}");
        if (!$deleted) {
            sendError('Failed to delete: ' . $supabase->getLastError(), 500);
        }

        sendResponse([
            'success' => true,
            'deleted_id' => $id,
        ]);
    }

    sendError('Unknown POST action');
}

sendError('Method not allowed', 405);
