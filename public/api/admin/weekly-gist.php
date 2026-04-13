<?php
/**
 * Weekly Gist API — Admin-only
 *
 * GET  ?action=articles&start=YYYY-MM-DD&end=YYYY-MM-DD  → 기간 내 기사 목록 + RAG 메타데이터
 * POST { action: "generate", start, end, articles }      → GPT 호출 → 위클리 Gist JSON
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

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production', dirname($projectRoot) . '/.env'] as $f) {
    if (loadEnvFile($f)) break;
}

// ── DB 연결 ────────────────────────────────────────────

function getDb(): PDO {
    global $projectRoot;
    $dbConfig = require $projectRoot . 'config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'], $dbConfig['port'] ?? 3306, $dbConfig['database']);
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

// ── Services ───────────────────────────────────────────

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

// ── GET: 기간 내 기사 + RAG 메타 조회 ──────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'articles') {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
        $end   = $_GET['end']   ?? date('Y-m-d');

        try {
            $pdo = getDb();

            $columns = [
                'id', 'title', 'description', 'source', 'category_parent', 'category',
                'published_at', 'created_at',
            ];
            $optionalCols = ['why_important', 'narration', 'future_prediction', 'original_title'];
            $existing = $pdo->query("SHOW COLUMNS FROM news")->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach ($optionalCols as $col) {
                if (in_array($col, $existing, true)) $columns[] = $col;
            }

            $colStr = implode(', ', $columns);
            $statusCond = in_array('status', $existing, true)
                ? "(status = 'published' OR status IS NULL)"
                : "1=1";

            $sql = "SELECT {$colStr} FROM news
                    WHERE {$statusCond}
                      AND COALESCE(published_at, created_at) BETWEEN :start AND :end
                    ORDER BY COALESCE(published_at, created_at) DESC
                    LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
            $articles = $stmt->fetchAll();

            // Supabase RAG 메타데이터 보강
            $supabase = new SupabaseService([]);
            if ($supabase->isConfigured() && count($articles) > 0) {
                $newsIds = array_column($articles, 'id');
                $idMap = [];

                foreach ($newsIds as $nid) {
                    $rows = $supabase->select(
                        'analysis_embeddings',
                        'news_id=eq.' . (int)$nid . '&select=news_id,metadata&limit=1',
                        1
                    );
                    if ($rows && count($rows) > 0) {
                        $meta = $rows[0]['metadata'] ?? [];
                        if (is_array($meta)) {
                            $idMap[(int)$nid] = [
                                'topic_label'    => $meta['topic_label'] ?? '',
                                'topic_category' => $meta['topic_category'] ?? '',
                                'entities'       => $meta['entities'] ?? [],
                                'region'         => $meta['region'] ?? [],
                            ];
                        }
                    }
                }

                foreach ($articles as &$art) {
                    $art['rag_metadata'] = $idMap[(int)$art['id']] ?? null;
                }
                unset($art);
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'data'    => [
                    'articles' => $articles,
                    'period'   => ['start' => $start, 'end' => $end],
                    'count'    => count($articles),
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (\Throwable $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action. Use ?action=articles']);
    exit;
}

// ── POST: GPT로 위클리 Gist 생성 ───────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET or POST only']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?: [];
$action   = $input['action'] ?? '';

if ($action !== 'generate') {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'POST action must be "generate"']);
    exit;
}

$startDate = $input['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate   = $input['end']   ?? date('Y-m-d');
$articles  = $input['articles'] ?? [];

if (empty($articles)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'articles 배열이 비어 있습니다.']);
    exit;
}

$openai = new OpenAIService([]);
if (!$openai->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'OpenAI가 설정되지 않았습니다.']);
    exit;
}

// 기사 입력 구성
$articleInputs = [];
foreach ($articles as $i => $art) {
    $entry = [
        'id'                => $art['id'] ?? $i,
        'title'             => $art['title'] ?? '',
        'source'            => $art['source'] ?? '',
        'category_parent'   => $art['category_parent'] ?? '',
        'description'       => mb_substr($art['description'] ?? '', 0, 300),
        'why_important'     => mb_substr($art['why_important'] ?? '', 0, 400),
        'future_prediction' => mb_substr($art['future_prediction'] ?? '', 0, 300),
        'narration'         => mb_substr($art['narration'] ?? '', 0, 500),
    ];
    if (!empty($art['rag_metadata'])) {
        $entry['rag_metadata'] = $art['rag_metadata'];
    }
    $articleInputs[] = $entry;
}

$articlesJson = json_encode($articleInputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$articleCount = count($articleInputs);

$systemPrompt = <<<SYSTEM
당신은 "The Gist"의 수석 에디터입니다.
의사결정자를 위한 주간 인텔리전스 브리핑을 작성합니다.
출력은 반드시 유효한 JSON으로만 응답하세요. 설명이나 마크다운 없이 JSON만 출력합니다.
SYSTEM;

$userPrompt = <<<PROMPT
##############################################################
# The Gist 위클리 브리핑 생성 가이드
##############################################################

[역할]
이번 주({$startDate} ~ {$endDate}) 발행된 기사 {$articleCount}개를
종합하여, 의사결정자를 위한 **주간 인텔리전스 브리핑**을 작성합니다.

[절대 규칙]
1. 기사별 요약 나열 금지. 이것은 뉴스레터가 아니라 분석 브리핑이다.
2. 개별 기사를 인용하되, 반드시 다른 기사와의 관계 속에서 언급할 것.
3. 서로 충돌하는 관점이 있으면 반드시 대비하여 정리할 것.
4. 각 이슈의 마지막은 "그래서 뭐냐(So What)"로 끝낼 것.
5. 출력은 반드시 아래 JSON 스키마를 따를 것.

[메타인지 규칙 — 입력 데이터의 한계를 인식하라]
입력의 why_important, narration, future_prediction은
개별 기사에 대한 1차 AI 분석 결과다. 즉, 이미 해석이 들어가 있다.

따라서:
1. 이것들을 그대로 정리하면 "요약의 요약"이 된다. 이건 실패다.
2. 서로 다른 기사의 1차 해석이 같은 방향을 가리키면,
   "왜 같은 결론인가"를 물어라. 근거가 같은가? 다른가?
3. 서로 다른 기사의 1차 해석이 충돌하면,
   어느 쪽이 더 강한 근거를 가지는지 판단하라.
4. 1차 해석 어디에도 없는 새로운 연결(cross-insight)을
   반드시 1개 이상 생성하라.

[RAG 메타데이터 사용 규칙]
topic_label, topic_category, entities, region은
클러스터링을 돕기 위한 "힌트"일 뿐이다.

1. topic_label이 같아도, 반드시 실제 내용 기반으로 재검증하라
2. topic_label이 달라도, 인과관계가 있으면 같은 흐름으로 묶어라
3. entities가 다르면 → 관점 차이로 해석하라
4. RAG 메타데이터와 기사 내용이 충돌하면 → 기사 내용을 우선하라
이 데이터는 "정답"이 아니라 "초기 가설"이다.

[프로세스]
Step 1 — 클러스터링:
  입력 기사들을 이슈별로 3~5개 클러스터로 묶어라.
  같은 사건, 같은 정책, 같은 시장을 다루면 같은 클러스터다.

Step 2 — 관점 통합:
  각 클러스터 내에서:
  - 기사들이 같은 방향이면: "일관되게 X를 가리킨다"
  - 기사들이 다른 방향이면: "A는 X라 하고, B는 Y라 한다. 차이의 근거는 Z"

Step 3 — 교차 연결:
  클러스터 간에 인과관계가 있으면 짚어라.
  예: "반도체 규제(이슈1) → 공급망 재편(이슈2) → 에너지 투자 변화(이슈3)"

Step 4 — So What:
  이번 주 전체를 관통하는 한 줄 메시지.

[출력 JSON 스키마]
{
  "headline": "이번 주 한 줄 (30자 이내)",
  "macro_so_what": "이번 주 전체를 관통하는 전략적 의미 (1~2문장)",
  "clusters": [
    {
      "cluster_id": 1,
      "title": "클러스터 제목 (20자 이내)",
      "category": "diplomacy|economy|technology|energy|security",
      "priority_rank": 1,
      "impact_score": 4,
      "confidence": "high|medium|low",
      "one_line_takeaway": "이 이슈의 핵심 한 줄",
      "source_article_ids": [12, 45, 67],
      "narrative": "관점 통합 서술 (200~400자)",
      "perspectives": [
        {
          "viewpoint": "관점 A 요약",
          "source": "기사 제목 또는 매체명",
          "difference_reason": "이 관점의 근거"
        },
        {
          "viewpoint": "관점 B 요약",
          "source": "기사 제목 또는 매체명",
          "difference_reason": "이 관점의 근거"
        }
      ],
      "so_what": "그래서 뭐냐 (1~2문장)"
    }
  ],
  "cross_connections": [
    {
      "from_cluster": 1,
      "to_cluster": 3,
      "relationship": "이슈1이 이슈3에 미치는 영향 설명"
    }
  ],
  "next_week_watch": ["다음 주 주목 포인트 1", "다음 주 주목 포인트 2"],
  "meta": {
    "total_articles": {$articleCount},
    "period": "{$startDate} ~ {$endDate}"
  }
}

[금지 패턴 — 이렇게 나오면 실패]
- "이번 주 A 기사에서는 X, B 기사에서는 Y, C 기사에서는 Z" (나열)
- 모든 클러스터의 impact_score가 동일
- perspectives가 1개뿐인 클러스터 (최소 2개 관점)
- so_what이 narrative의 반복

[성공 패턴 — 이렇게 나와야 함]
- "3개 매체가 일관되게 X를 경고하지만, 근거가 다르다: A는 수치 기반, B는 정치적 맥락, C는 역사적 선례"
- cluster 간 cross_connection으로 인과 사슬 제시
- so_what이 해당 이슈의 실질적 함의를 한 줄로 명시

##############################################################
# 입력 기사 목록
##############################################################
{$articlesJson}
PROMPT;

try {
    $openaiConfig = require $projectRoot . 'config/openai.php';
    $apiKey = $openaiConfig['api_key'] ?? '';
    $endpoint = $openaiConfig['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses';

    $requestBody = [
        'model'       => 'gpt-5.2',
        'input'       => $userPrompt,
        'instructions' => $systemPrompt,
        'temperature' => 0.6,
        'max_output_tokens' => 12000,
        'text' => ['format' => ['type' => 'json_object']],
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 240,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($requestBody),
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new \RuntimeException('cURL error: ' . $curlError);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new \RuntimeException("OpenAI HTTP {$httpCode}: " . mb_substr($raw, 0, 500));
    }

    $response = json_decode($raw, true);
    $outputText = '';
    if (isset($response['output'])) {
        foreach ($response['output'] as $block) {
            if (($block['type'] ?? '') === 'message') {
                foreach ($block['content'] ?? [] as $c) {
                    if (($c['type'] ?? '') === 'output_text') {
                        $outputText = $c['text'] ?? '';
                    }
                }
            }
        }
    }

    if ($outputText === '') {
        throw new \RuntimeException('GPT 응답에서 텍스트를 추출할 수 없습니다. Raw: ' . mb_substr($raw, 0, 500));
    }

    $gistData = json_decode($outputText, true);
    if (!$gistData) {
        throw new \RuntimeException('GPT 응답이 유효한 JSON이 아닙니다: ' . mb_substr($outputText, 0, 500));
    }

    $gistData['meta'] = array_merge($gistData['meta'] ?? [], [
        'generated_at'   => date('c'),
        'total_articles' => $articleCount,
        'period'         => "{$startDate} ~ {$endDate}",
        'model'          => 'gpt-5.2',
    ]);

    ob_clean();
    echo json_encode([
        'success' => true,
        'data'    => $gistData,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
