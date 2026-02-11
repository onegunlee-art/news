<?php
/**
 * Critique API – Admin-only CRUD for editor critiques
 *
 * POST action=save_critique     – Create critique + auto-embed
 * POST action=update_critique   – Update (new version) + auto-embed
 * GET  action=list_critiques    – List critiques for an article
 * GET  action=critique_versions – Version history for a critique
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

// ── Project root & env (same pattern as ai-analyze.php) ─
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
use Agents\Services\RAGService;

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
if (!$supabase->isConfigured()) {
    sendError('Supabase not configured', 500);
}
$openai = new OpenAIService([]);
$rag    = new RAGService($openai, $supabase);

// ── Route ───────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_critiques') {
        $newsId = $_GET['news_id'] ?? null;
        $articleUrl = $_GET['article_url'] ?? null;
        $query = 'order=created_at.desc';
        if ($newsId !== null) {
            $query = "news_id=eq.{$newsId}&{$query}";
        } elseif ($articleUrl !== null) {
            $query = "article_url=eq." . urlencode($articleUrl) . "&{$query}";
        }
        $rows = $supabase->select('critiques', $query, 50);
        sendResponse(['success' => true, 'critiques' => $rows ?? []]);
    }

    if ($action === 'critique_versions') {
        $critiqueId = $_GET['critique_id'] ?? '';
        if ($critiqueId === '') sendError('critique_id required');
        // Get the root critique, then all versions sharing the same parent chain
        $rows = $supabase->select('critiques', "or=(id.eq.{$critiqueId},parent_id.eq.{$critiqueId})&order=version.asc", 100);
        sendResponse(['success' => true, 'versions' => $rows ?? []]);
    }

    sendError('Unknown GET action');
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) sendError('Invalid JSON');

    $action = $input['action'] ?? '';

    // ── save_critique ───────────────────────────────────
    if ($action === 'save_critique') {
        $critiqueText = trim($input['critique_text'] ?? '');
        if ($critiqueText === '') sendError('critique_text required');

        $row = [
            'news_id'       => $input['news_id'] ?? null,
            'article_url'   => $input['article_url'] ?? null,
            'article_title' => $input['article_title'] ?? null,
            'critique_text' => $critiqueText,
            'critique_type' => $input['critique_type'] ?? 'general',
            'editor_notes'  => $input['editor_notes'] ?? [],
            'version'       => 1,
        ];

        $inserted = $supabase->insert('critiques', $row);
        if (!$inserted || empty($inserted[0]['id'])) {
            sendError('Failed to save critique: ' . $supabase->getLastError(), 500);
        }

        $critiqueId = $inserted[0]['id'];

        // Auto-embed for RAG
        $embeddedCount = 0;
        if ($rag->isConfigured()) {
            $embeddedCount = $rag->storeCritiqueEmbedding($critiqueId, $critiqueText, [
                'news_id' => $input['news_id'] ?? null,
                'article_url' => $input['article_url'] ?? null,
                'critique_type' => $input['critique_type'] ?? 'general',
            ]);
        }

        sendResponse([
            'success' => true,
            'critique' => $inserted[0],
            'embedded_chunks' => $embeddedCount,
        ]);
    }

    // ── update_critique ─────────────────────────────────
    if ($action === 'update_critique') {
        $parentId = $input['parent_id'] ?? $input['critique_id'] ?? '';
        if ($parentId === '') sendError('parent_id or critique_id required');
        $critiqueText = trim($input['critique_text'] ?? '');
        if ($critiqueText === '') sendError('critique_text required');

        // Fetch parent to get metadata and version
        $parents = $supabase->select('critiques', "id=eq.{$parentId}", 1);
        if (!$parents || empty($parents[0])) {
            sendError('Parent critique not found');
        }
        $parent = $parents[0];

        $row = [
            'news_id'       => $parent['news_id'],
            'article_url'   => $parent['article_url'],
            'article_title' => $parent['article_title'],
            'critique_text' => $critiqueText,
            'critique_type' => $input['critique_type'] ?? $parent['critique_type'] ?? 'general',
            'editor_notes'  => $input['editor_notes'] ?? [],
            'version'       => ((int) ($parent['version'] ?? 1)) + 1,
            'parent_id'     => $parentId,
        ];

        $inserted = $supabase->insert('critiques', $row);
        if (!$inserted || empty($inserted[0]['id'])) {
            sendError('Failed to update critique: ' . $supabase->getLastError(), 500);
        }

        $critiqueId = $inserted[0]['id'];

        // Auto-embed
        $embeddedCount = 0;
        if ($rag->isConfigured()) {
            $embeddedCount = $rag->storeCritiqueEmbedding($critiqueId, $critiqueText, [
                'news_id' => $parent['news_id'],
                'article_url' => $parent['article_url'],
                'critique_type' => $row['critique_type'],
                'version' => $row['version'],
            ]);
        }

        sendResponse([
            'success' => true,
            'critique' => $inserted[0],
            'embedded_chunks' => $embeddedCount,
        ]);
    }

    sendError('Unknown POST action');
}

sendError('Method not allowed', 405);
