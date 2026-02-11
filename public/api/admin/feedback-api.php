<?php
/**
 * Analysis Feedback API – Admin 피드백 → GPT 재분석 무한 루프
 *
 * POST action=save_feedback      – Admin 코멘트 + 점수 저장 (revision 생성)
 * POST action=request_revision   – GPT 재분석 요청 (피드백 반영)
 * POST action=approve            – 최종 승인 → Layer 1,2,3 임베딩 저장
 * GET  action=get_history        – 기사별 revision 히스토리
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

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
use Agents\Services\RAGService;
use Agents\Agents\AnalysisAgent;
use Agents\Services\GoogleTTSService;

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

// ── GET: get_history ────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get_history') {
        $articleId = $_GET['article_id'] ?? null;
        $articleUrl = $_GET['article_url'] ?? null;

        if ($articleId === null && $articleUrl === null) {
            sendError('article_id or article_url required');
        }

        $query = 'order=revision_number.asc';
        if ($articleId !== null) {
            $query = "article_id=eq.{$articleId}&{$query}";
        } elseif ($articleUrl !== null) {
            $query = "article_url=eq." . urlencode($articleUrl) . "&{$query}";
        }

        $rows = $supabase->select('analysis_feedback', $query, 100);
        sendResponse(['success' => true, 'history' => $rows ?? []]);
    }

    sendError('Unknown GET action');
}

// ── POST actions ────────────────────────────────────────
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) sendError('Invalid JSON');

    $action = $input['action'] ?? '';

    // ── save_feedback ───────────────────────────────────
    if ($action === 'save_feedback') {
        $articleId  = $input['article_id'] ?? null;
        $articleUrl = $input['article_url'] ?? null;
        $adminComment = trim($input['admin_comment'] ?? '');
        $score = isset($input['score']) ? (int) $input['score'] : null;
        $gptAnalysis = $input['gpt_analysis'] ?? [];

        if ($adminComment === '') sendError('admin_comment required');
        if ($score !== null && ($score < 1 || $score > 10)) sendError('score must be 1-10');

        // 기존 revision 수 확인하여 revision_number 계산
        $revisionNumber = 1;
        $parentId = null;
        if ($articleId !== null || $articleUrl !== null) {
            $q = 'order=revision_number.desc';
            if ($articleId !== null) {
                $q = "article_id=eq.{$articleId}&{$q}";
            } else {
                $q = "article_url=eq." . urlencode($articleUrl) . "&{$q}";
            }
            $existing = $supabase->select('analysis_feedback', $q, 1);
            if (!empty($existing) && is_array($existing) && !empty($existing[0])) {
                $revisionNumber = ((int) ($existing[0]['revision_number'] ?? 0)) + 1;
                $parentId = $existing[0]['id'] ?? null;
            }
        }

        $row = [
            'article_id'      => $articleId,
            'article_url'     => $articleUrl,
            'revision_number' => $revisionNumber,
            'admin_comment'   => $adminComment,
            'score'           => $score,
            'gpt_analysis'    => $gptAnalysis,
            'status'          => 'reviewed',
            'parent_id'       => $parentId,
        ];

        $inserted = $supabase->insert('analysis_feedback', $row);
        if (!$inserted || empty($inserted[0]['id'])) {
            sendError('Failed to save feedback: ' . $supabase->getLastError(), 500);
        }

        sendResponse([
            'success'  => true,
            'feedback' => $inserted[0],
        ]);
    }

    // ── request_revision ────────────────────────────────
    if ($action === 'request_revision') {
        $feedbackId   = $input['feedback_id'] ?? '';
        $articleId    = $input['article_id'] ?? null;
        $articleUrl   = $input['article_url'] ?? null;
        $adminComment = trim($input['admin_comment'] ?? '');
        $score        = isset($input['score']) ? (int) $input['score'] : null;
        $originalAnalysis = $input['original_analysis'] ?? [];

        if ($adminComment === '') sendError('admin_comment required');

        // AnalysisAgent revise 호출
        $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php')
            ? require $projectRoot . 'config/google_tts.php'
            : [];
        $googleTts = new GoogleTTSService($googleTtsConfig);

        $analysisAgent = new AnalysisAgent($openai, [
            'enable_tts' => false,
            'rag_service' => $rag->isConfigured() ? $rag : null,
        ], $googleTts, $rag->isConfigured() ? $rag : null);

        $analysisAgent->initialize();

        try {
            $revised = $analysisAgent->revise($originalAnalysis, $adminComment, $score);
        } catch (\Throwable $e) {
            sendError('GPT 재분석 실패: ' . $e->getMessage(), 500);
        }

        // 재분석 결과 저장 (새 revision)
        $revisionNumber = 1;
        $parentId = null;
        if ($articleId !== null || $articleUrl !== null) {
            $q = 'order=revision_number.desc';
            if ($articleId !== null) {
                $q = "article_id=eq.{$articleId}&{$q}";
            } else {
                $q = "article_url=eq." . urlencode($articleUrl) . "&{$q}";
            }
            $existing = $supabase->select('analysis_feedback', $q, 1);
            if (!empty($existing) && is_array($existing) && !empty($existing[0])) {
                $revisionNumber = ((int) ($existing[0]['revision_number'] ?? 0)) + 1;
                $parentId = $existing[0]['id'] ?? null;
            }
        }

        // 재분석 시 사용한 프롬프트 구성
        $revisionPrompt = "Admin 피드백: {$adminComment}";
        if ($score !== null) {
            $revisionPrompt .= " (점수: {$score}/10)";
        }

        $row = [
            'article_id'      => $articleId,
            'article_url'     => $articleUrl,
            'revision_number' => $revisionNumber,
            'admin_comment'   => $adminComment,
            'score'           => $score,
            'gpt_analysis'    => $originalAnalysis,
            'gpt_revision'    => $revised,
            'revision_prompt' => $revisionPrompt,
            'status'          => 'revised',
            'parent_id'       => $parentId,
        ];

        $inserted = $supabase->insert('analysis_feedback', $row);
        if (!$inserted || empty($inserted[0]['id'])) {
            sendError('Failed to save revision: ' . $supabase->getLastError(), 500);
        }

        sendResponse([
            'success'  => true,
            'feedback' => $inserted[0],
            'revision' => $revised,
        ]);
    }

    // ── approve ─────────────────────────────────────────
    if ($action === 'approve') {
        $feedbackId   = $input['feedback_id'] ?? '';
        $articleId    = $input['article_id'] ?? null;
        $articleUrl   = $input['article_url'] ?? null;
        $finalAnalysis = $input['final_analysis'] ?? [];

        if ($feedbackId === '') sendError('feedback_id required');

        // 상태를 approved로 업데이트
        $updated = $supabase->update('analysis_feedback', "id=eq.{$feedbackId}", [
            'status' => 'approved',
        ]);

        if ($updated === null) {
            sendError('Failed to approve: ' . $supabase->getLastError(), 500);
        }

        // Layer 1,2,3 임베딩 저장
        $embeddedCount = 0;
        if ($rag->isConfigured()) {
            // Layer 2: 분석 기록 임베딩
            $textToEmbed = '';
            if (!empty($finalAnalysis['content_summary'])) {
                $textToEmbed .= $finalAnalysis['content_summary'] . "\n\n";
            }
            if (!empty($finalAnalysis['narration'])) {
                $textToEmbed .= $finalAnalysis['narration'] . "\n\n";
            }
            if (!empty($finalAnalysis['key_points']) && is_array($finalAnalysis['key_points'])) {
                $textToEmbed .= implode("\n", $finalAnalysis['key_points']);
            }

            if (trim($textToEmbed) !== '') {
                $embeddedCount += $rag->storeAnalysisEmbedding(
                    $articleId ? (int) $articleId : null,
                    $articleUrl,
                    $textToEmbed,
                    'approved_analysis',
                    [
                        'news_title' => $finalAnalysis['news_title'] ?? '',
                        'approved' => true,
                    ]
                );
            }
        }

        sendResponse([
            'success'         => true,
            'approved'        => true,
            'feedback_id'     => $feedbackId,
            'embedded_chunks' => $embeddedCount,
        ]);
    }

    sendError('Unknown POST action');
}

sendError('Method not allowed', 405);
