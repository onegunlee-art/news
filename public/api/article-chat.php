<?php
/**
 * 기사 챗봇 API (관리자 베타 전용)
 *
 * GET  ?action=chips&news_id=1
 * GET  ?action=session&news_id=1
 * POST { news_id, message, chip_id?, session_key?, history?[] } — SSE 스트림
 *
 * URL: /api/article-chat.php (nginx가 .php 직접 실행)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

require_once __DIR__ . '/lib/cors.php';
handleOptionsRequest();
setCorsHeaders();

require_once __DIR__ . '/lib/auth.php';

use Agents\Services\OpenAIService;
use Agents\Services\RAGService;
use Agents\Services\SupabaseService;

function findProjectRootArticleChat(): string
{
    $candidates = [__DIR__ . '/../../', __DIR__ . '/../../../'];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found');
}

function sendJsonArticleChat(array $data, int $code = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendJsonErrorArticleChat(string $msg, int $code = 400): void
{
    sendJsonArticleChat(['success' => false, 'error' => $msg], $code);
}

function sendSSEArticleChat(string $event, $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

function chipIdToAnswerType(?string $chipId): ?string
{
    if ($chipId === null || $chipId === '') {
        return null;
    }
    $map = [
        'content_summary_5' => 'summary',
        'why_important' => 'summary',
        'impact_korea' => 'structure',
        'scenario_forecast' => 'scenario',
        'understand_summary' => 'summary',
        'structure_benefit' => 'structure',
        'intention_hidden' => 'intent',
        'risk_worst' => 'risk',
        'understand_why' => 'summary',
        'understand_oneline' => 'summary',
        'structure_loser' => 'structure',
        'structure_balance' => 'structure',
        'intention_choice' => 'intent',
        'intention_alternative' => 'intent',
        'risk_failure' => 'risk',
        'scenario_best_worst' => 'scenario',
    ];
    return $map[$chipId] ?? 'other';
}

function buildArticleBodyFromNews(array $news): string
{
    $parts = [];
    foreach (['why_important', 'narration', 'description', 'content'] as $f) {
        $t = isset($news[$f]) ? strip_tags((string) $news[$f]) : '';
        $t = preg_replace("/\s+/u", ' ', trim($t));
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    $body = implode("\n\n", $parts);
    if (mb_strlen($body) > 12000) {
        $body = mb_substr($body, 0, 12000) . "\n…(이하 생략)";
    }
    return $body;
}

/**
 * @return array{lines: string[], refs: array<int, array<string, mixed>>}
 */
function buildRagForArticle(RAGService $rag, string $query, int $newsId, float $minSim): array
{
    if (!$rag->isConfigured()) {
        return ['lines' => [], 'refs' => []];
    }
    $ctx = $rag->retrieveRelevantContext($query, 10);
    $fromArticle = [];
    $other = [];
    foreach ($ctx['analyses'] ?? [] as $row) {
        $sim = (float) ($row['similarity'] ?? 0);
        if ($sim < $minSim) {
            continue;
        }
        $nid = isset($row['news_id']) ? (int) $row['news_id'] : 0;
        $text = trim((string) ($row['chunk_text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $item = ['sim' => $sim, 'text' => $text, 'news_id' => $nid];
        if ($nid === $newsId) {
            $fromArticle[] = $item;
        } else {
            $other[] = $item;
        }
    }
    usort($fromArticle, fn ($a, $b) => $b['sim'] <=> $a['sim']);
    usort($other, fn ($a, $b) => $b['sim'] <=> $a['sim']);
    $fromArticle = array_slice($fromArticle, 0, 2);
    $other = array_slice($other, 0, 2);

    $lines = [];
    $refs = [];
    foreach ($fromArticle as $x) {
        $lines[] = '- [동일 기사·관련 청크, 유사도 ' . round($x['sim'], 3) . '] ' . mb_substr($x['text'], 0, 800);
        $refs[] = ['scope' => 'same_article', 'news_id' => $newsId, 'similarity' => $x['sim']];
    }
    foreach ($other as $x) {
        $lines[] = '- [다른 기사·참고, 유사도 ' . round($x['sim'], 3) . '] ' . mb_substr($x['text'], 0, 600);
        $refs[] = ['scope' => 'global', 'news_id' => $x['news_id'], 'similarity' => $x['sim']];
    }
    foreach ($ctx['knowledge'] ?? [] as $k) {
        $sim = (float) ($k['similarity'] ?? 0);
        if ($sim < $minSim) {
            continue;
        }
        $title = (string) ($k['title'] ?? '');
        $content = mb_substr((string) ($k['content'] ?? ''), 0, 400);
        $lines[] = '- [지식라이브러리, 유사도 ' . round($sim, 3) . '] ' . $title . ': ' . $content;
        $refs[] = ['scope' => 'knowledge', 'title' => $title, 'similarity' => $sim];
        if (count($lines) >= 6) {
            break;
        }
    }

    return ['lines' => $lines, 'refs' => $refs];
}

try {
    $projectRoot = findProjectRootArticleChat();
} catch (Throwable $e) {
    sendJsonErrorArticleChat($e->getMessage(), 500);
}

foreach ([
    $projectRoot . 'env.txt',
    $projectRoot . '.env',
    $projectRoot . '.env.production',
] as $f) {
    if (is_file($f) && is_readable($f)) {
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ($line[0] ?? '') === '#') {
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
        break;
    }
}

require_once $projectRoot . 'src/agents/autoload.php';

$acConfig = require $projectRoot . 'config/article-chat.php';

$token = getBearerToken();
if (!$token) {
    sendJsonErrorArticleChat('로그인이 필요합니다.', 401);
}
$payload = decodeJwt($token);
if (!$payload || empty($payload['user_id'])) {
    sendJsonErrorArticleChat('유효하지 않은 토큰입니다.', 401);
}

try {
    $pdo = getDb();
} catch (Throwable $e) {
    sendJsonErrorArticleChat('데이터베이스 연결 실패', 500);
}

$userId = (int) $payload['user_id'];
$stmtUser = $pdo->prepare('SELECT id, role, email FROM users WHERE id = ? LIMIT 1');
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    sendJsonErrorArticleChat('사용자를 찾을 수 없습니다.', 403);
}

$isAdmin = ($userRow['role'] ?? '') === 'admin';
if (!$isAdmin) {
    sendJsonErrorArticleChat('관리자만 이용할 수 있습니다. (베타)', 403);
}

$enabled = !empty($acConfig['enabled']);
if (!$enabled) {
    // 비활성 시에도 관리자는 사용 가능 (베타)
}

$limits = $acConfig['limits'] ?? [];
$maxQ = (int) ($limits['max_questions_per_session'] ?? 3);
$maxChars = (int) ($limits['max_input_chars'] ?? 500);
$minRagSim = 0.42;

$openaiCfg = array_merge(require $projectRoot . 'config/openai.php', $acConfig['openai'] ?? []);
$openai = new OpenAIService($openaiCfg);
$supabase = new SupabaseService([]);
$rag = new RAGService($openai, $supabase);

// ── GET ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? '';
    $newsId = isset($_GET['news_id']) ? (int) $_GET['news_id'] : 0;

    if ($action === 'chips') {
        if ($newsId <= 0) {
            sendJsonErrorArticleChat('news_id가 필요합니다.', 400);
        }
        $fixed = $acConfig['chips']['fixed'] ?? [];
        sendJsonArticleChat([
            'success' => true,
            'data' => [
                'fixed' => $fixed,
                'dynamic' => [],
                'intro' => $acConfig['prompts']['disclaimer_footer'] ?? '',
            ],
        ]);
    }

    if ($action === 'session') {
        if ($newsId <= 0) {
            sendJsonErrorArticleChat('news_id가 필요합니다.', 400);
        }
        try {
            $st = $pdo->prepare(
                'SELECT id, question_count, question_limit FROM article_chat_sessions WHERE news_id = ? AND user_id = ? LIMIT 1'
            );
            $st->execute([$newsId, $userId]);
            $sess = $st->fetch(PDO::FETCH_ASSOC);
            if (!$sess) {
                sendJsonArticleChat([
                    'success' => true,
                    'data' => [
                        'session_id' => null,
                        'question_count' => 0,
                        'question_limit' => $maxQ,
                        'remaining' => $maxQ,
                        'messages' => [],
                    ],
                ]);
            }
            $sid = (int) $sess['id'];
            $qc = (int) $sess['question_count'];
            $ql = (int) $sess['question_limit'];
            $msgSt = $pdo->prepare(
                'SELECT role, content, chip_id, created_at FROM article_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 50'
            );
            $msgSt->execute([$sid]);
            $messages = $msgSt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonArticleChat([
                'success' => true,
                'data' => [
                    'session_id' => $sid,
                    'question_count' => $qc,
                    'question_limit' => $ql,
                    'remaining' => max(0, $ql - $qc),
                    'messages' => $messages,
                ],
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'article_chat') !== false) {
                sendJsonErrorArticleChat('article_chat 테이블이 없습니다. database/migrations/add_article_chat_tables.sql 을 실행하세요.', 503);
            }
            throw $e;
        }
    }

    sendJsonErrorArticleChat('Unknown action', 400);
}

// ── POST (SSE) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonErrorArticleChat('POST only', 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    sendJsonErrorArticleChat('Invalid JSON', 400);
}

$newsId = (int) ($input['news_id'] ?? 0);
$message = isset($input['message']) ? trim((string) $input['message']) : '';
$chipId = isset($input['chip_id']) ? (string) $input['chip_id'] : '';
$sessionKey = isset($input['session_key']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $input['session_key']), 0, 64) : '';
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

if ($newsId <= 0 || $message === '') {
    sendJsonErrorArticleChat('news_id와 message가 필요합니다.', 400);
}
if (mb_strlen($message) > $maxChars) {
    sendJsonErrorArticleChat("메시지는 {$maxChars}자 이하로 입력해 주세요.", 400);
}

try {
    $newsSt = $pdo->prepare(
        'SELECT id, title, url, narration, description, content, why_important FROM news WHERE id = ? LIMIT 1'
    );
    $newsSt->execute([$newsId]);
    $news = $newsSt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'article_chat') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
        sendJsonErrorArticleChat('DB 스키마를 확인하세요. news 컬럼 또는 article_chat 마이그레이션.', 503);
    }
    throw $e;
}

if (!$news) {
    sendJsonErrorArticleChat('기사를 찾을 수 없습니다.', 404);
}

$articleTitle = (string) ($news['title'] ?? '');
$articleBody = buildArticleBodyFromNews($news);
if ($articleBody === '') {
    sendJsonErrorArticleChat('기사 본문이 비어 있어 답변할 수 없습니다.', 400);
}

try {
    $sessSt = $pdo->prepare(
        'SELECT * FROM article_chat_sessions WHERE news_id = ? AND user_id = ? LIMIT 1'
    );
    $sessSt->execute([$newsId, $userId]);
    $session = $sessSt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        if ($sessionKey === '') {
            $sessionKey = bin2hex(random_bytes(16));
        }
        $ins = $pdo->prepare(
            'INSERT INTO article_chat_sessions (news_id, user_id, session_key, question_limit) VALUES (?,?,?,?)'
        );
        $ins->execute([$newsId, $userId, $sessionKey, $maxQ]);
        $sessSt->execute([$newsId, $userId]);
        $session = $sessSt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'article_chat') !== false) {
        sendJsonErrorArticleChat('article_chat 테이블이 없습니다. database/migrations/add_article_chat_tables.sql 을 실행하세요.', 503);
    }
    throw $e;
}

$sessionId = (int) $session['id'];
$qCount = (int) $session['question_count'];
$qLimit = (int) $session['question_limit'];
if ($qCount >= $qLimit) {
    sendJsonErrorArticleChat('이 기사에 대한 질문 한도에 도달했습니다.', 429);
}

$ragPack = buildRagForArticle($rag, $message, $newsId, $minRagSim);
$ragText = $ragPack['lines'] === [] ? '(보조 맥락 없음)' : implode("\n", $ragPack['lines']);
$refsJson = json_encode($ragPack['refs'], JSON_UNESCAPED_UNICODE);

$promptTemplate = $acConfig['prompts']['article_single'] ?? '';
$systemPrompt = str_replace(
    ['<<ARTICLE_TITLE>>', '<<ARTICLE_BODY>>', '<<RAG_CONTEXT>>'],
    [$articleTitle, $articleBody, $ragText],
    $promptTemplate
);

$userInput = '';
foreach ($history as $msg) {
    if (!is_array($msg)) {
        continue;
    }
    $role = (($msg['role'] ?? 'user') === 'assistant') ? 'Assistant' : 'User';
    $c = trim((string) ($msg['content'] ?? ''));
    if ($c !== '') {
        $userInput .= "[{$role}]: {$c}\n\n";
    }
}
$userInput .= "[User]: {$message}";

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
while (ob_get_level()) {
    ob_end_flush();
}

sendSSEArticleChat('start', ['session_id' => $sessionId]);

$answerType = chipIdToAnswerType($chipId !== '' ? $chipId : null);
$maxOut = (int) ($openaiCfg['max_tokens'] ?? 2000);
$temp = (float) ($openaiCfg['temperature'] ?? 0.6);
$timeout = (int) ($openaiCfg['timeout'] ?? 60);

$fullResponse = '';
$disclaimerFooter = (string) ($acConfig['prompts']['disclaimer_footer'] ?? '');

try {
    $insUser = $pdo->prepare(
        'INSERT INTO article_chat_messages (session_id, role, content, chip_id, answer_type, retrieved_refs_json) VALUES (?,?,?,?,?,?)'
    );
    $insUser->execute([$sessionId, 'user', $message, $chipId !== '' ? $chipId : null, $answerType, $refsJson]);
    $updCt = $pdo->prepare(
        'UPDATE article_chat_sessions SET question_count = question_count + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $updCt->execute([$sessionId]);
} catch (PDOException $e) {
    sendJsonErrorArticleChat('메시지 저장 실패: ' . $e->getMessage(), 500);
}

$openai->chatStream(
    $systemPrompt,
    $userInput,
    function (string $delta) use (&$fullResponse) {
        $fullResponse .= $delta;
        sendSSEArticleChat('token', ['text' => $delta]);
    },
    function (string $full) use ($pdo, $sessionId, $refsJson, $answerType, $disclaimerFooter) {
        $full = trim($full);
        if ($full !== '') {
            try {
                $insAsst = $pdo->prepare(
                    'INSERT INTO article_chat_messages (session_id, role, content, chip_id, answer_type, retrieved_refs_json) VALUES (?,?,?,?,?,?)'
                );
                $insAsst->execute([$sessionId, 'assistant', $full, null, $answerType, $refsJson]);
            } catch (PDOException $e) {
                error_log('article-chat assistant save: ' . $e->getMessage());
            }
        }
        sendSSEArticleChat('done', [
            'full_text' => $full,
            'disclaimer' => $disclaimerFooter,
        ]);
    },
    [
        'max_tokens' => $maxOut,
        'timeout' => $timeout,
        'temperature' => $temp,
        'model' => $openaiCfg['model'] ?? 'gpt-4o-mini',
    ]
);

sendSSEArticleChat('end', []);
