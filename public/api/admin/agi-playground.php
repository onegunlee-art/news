<?php
/**
 * AGI Playground API - Judgment RAG 적용 생성 + 정합률 측정 + 학습
 *
 * POST action=generate { url } → AI 생성 결과 + 정합률
 * POST action=learn { ai_output, human_output } → 수정내용 패턴 학습
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(180);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;
use Agents\Services\RAGService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$action = $input['action'] ?? 'generate';

$openaiConfig = file_exists($projectRoot . 'config/openai.php')
    ? require $projectRoot . 'config/openai.php'
    : [];
if (file_exists($projectRoot . 'config/agents.php')) {
    $agentsConfig = require $projectRoot . 'config/agents.php';
    $openaiConfig = array_merge($openaiConfig, $agentsConfig['agents']['analysis'] ?? []);
}

$openai = new OpenAIService($openaiConfig);
$supabase = new SupabaseService([]);
$rag = new RAGService($openai, $supabase);

if (!$openai->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'OpenAI not configured']);
    exit;
}

$dbConfigPath = $projectRoot . 'config/database.php';
$db = null;
if (file_exists($dbConfigPath)) {
    $dbConfig = require $dbConfigPath;
    $dbConfig['dbname'] = $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand';
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // Continue without DB
    }
}

// ═══════════════════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════════════════

function fetchArticleContent(string $url): ?array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TheGistBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) {
        return null;
    }

    $title = '';
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }

    $content = '';
    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
    
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
        $content = strip_tags($m[1]);
    } elseif (preg_match('/<div[^>]+class=["\'][^"\']*(?:article|content|story|post)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
        $content = strip_tags($m[1]);
    } else {
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            $content = implode("\n\n", array_map('strip_tags', $matches[1]));
        }
    }

    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    if (mb_strlen($content) < 100) {
        return null;
    }

    return [
        'title' => $title,
        'content' => mb_substr($content, 0, 15000),
        'url' => $url,
    ];
}

/**
 * 최근 게시된 기사 예시 가져오기 (Few-shot learning용)
 */
function loadRecentPublishedExamples(?PDO $db, int $limit = 2): array
{
    if ($db === null) {
        return [];
    }
    try {
        $stmt = $db->query("
            SELECT title, narration, why_important, content, description
            FROM news 
            WHERE status = 'published' 
              AND narration IS NOT NULL 
              AND narration != ''
              AND why_important IS NOT NULL
              AND why_important != ''
            ORDER BY published_at DESC 
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Judgment 패턴 로드
 */
function loadJudgementPatterns(SupabaseService $supabase, int $minFrequency = 1): array
{
    if (!$supabase->isConfigured()) {
        return [];
    }
    $patterns = $supabase->select(
        'judgement_patterns',
        'is_active=eq.true&frequency=gte.' . $minFrequency . '&order=weight.desc',
        15
    );
    return is_array($patterns) ? $patterns : [];
}

/**
 * The Gist 스타일 기반 향상된 프롬프트 생성
 */
function buildEnhancedJudgementPrompt(array $patterns, array $examples, string $ragContext): string
{
    $basePrompt = <<<'PROMPT'
당신은 "The Gist"의 수석 에디터입니다. 해외 뉴스를 한국어로 분석하여 독자가 핵심을 빠르게 파악할 수 있도록 합니다.

## The Gist 작성 스타일 (필수 준수)

### 1. news_title (제목)
- 15-25자 내외의 한국어 제목
- 원문 번역이 아닌 핵심 메시지 재구성
- 독자의 호기심을 유발하되 과장 금지
- 예: "미중 기술패권 경쟁, 반도체 전선 확대"

### 2. narration (핵심 내레이션)
- 2-3문장으로 핵심만 전달
- "~했다", "~이다" 종결어미 사용
- 팩트 중심, 감정적 표현 배제
- 독자가 30초 안에 뉴스 핵심 파악 가능하게

### 3. why_important (왜 중요한가)
- 3-5문장으로 이 뉴스의 중요성 설명
- 한국/독자 관점에서의 영향 포함
- "이것이 중요한 이유는..." 형태로 시작 가능
- 배경 지식이 없는 독자도 이해할 수 있게

### 4. content (본문 - HTML 형식)
- highlight-box로 핵심 요약 시작
- h3 태그로 섹션 구분: 배경, 핵심 내용, 전망/시사점
- 300-600자 분량
- 형식 예시:
  <div class="highlight-box">핵심 한 줄 요약</div>
  <h3>배경</h3><p>관련 맥락 설명</p>
  <h3>핵심 내용</h3><p>뉴스의 주요 내용</p>
  <h3>시사점</h3><p>앞으로의 전망과 영향</p>

### 5. key_points (핵심 포인트)
- 3-5개의 불릿 포인트
- 각 포인트는 한 문장으로 완결
- 중복 없이 서로 다른 측면 다루기

반드시 다음 JSON 형식으로만 응답하세요:
{
  "news_title": "한국어 제목 (15-25자)",
  "narration": "2-3문장 핵심 내레이션",
  "why_important": "3-5문장 중요성 설명",
  "content": "<div class='highlight-box'>...</div><h3>배경</h3><p>...</p>...",
  "key_points": ["포인트1", "포인트2", "포인트3"]
}
PROMPT;

    // Few-shot 예제 추가
    if (!empty($examples)) {
        $basePrompt .= "\n\n## 실제 편집 예시 (이 스타일과 톤을 따르세요)\n";
        foreach ($examples as $i => $ex) {
            $num = $i + 1;
            $title = $ex['title'] ?? '';
            $narration = $ex['narration'] ?? '';
            $why = $ex['why_important'] ?? '';
            $contentPreview = mb_substr(strip_tags($ex['content'] ?? $ex['description'] ?? ''), 0, 200);
            
            $basePrompt .= <<<EX

### 예시 {$num}
**제목**: {$title}
**내레이션**: {$narration}
**왜 중요한가**: {$why}
**본문 미리보기**: {$contentPreview}...
EX;
        }
    }

    // Judgment 패턴 추가 (구체적 AI/에디터 비교 형태)
    if (!empty($patterns)) {
        $basePrompt .= "\n\n## 편집장 판단 패턴 (학습된 수정 방향)\n";
        $basePrompt .= "아래는 AI 초안을 편집장이 수정한 패턴입니다. 이를 미리 반영하세요:\n\n";
        
        foreach ($patterns as $p) {
            $cat = $p['category'] ?? 'general';
            $aiApproach = $p['ai_approach'] ?? '';
            $editorCorrection = $p['editor_correction'] ?? '';
            $desc = $p['description'] ?? '';
            
            if ($aiApproach && $editorCorrection) {
                $basePrompt .= "- [{$cat}] AI가 \"{$aiApproach}\"로 쓰면 → \"{$editorCorrection}\"로 수정\n";
            } elseif ($desc) {
                $basePrompt .= "- [{$cat}] {$desc}\n";
            }
        }
    }

    // RAG 컨텍스트 추가
    if (!empty($ragContext)) {
        $basePrompt .= "\n\n" . $ragContext;
    }

    return $basePrompt;
}

/**
 * 텍스트 유사도 계산
 */
function calculateSimilarity(string $a, string $b): float
{
    if (empty($a) || empty($b)) {
        return 0.0;
    }
    
    $a = mb_strtolower(trim($a));
    $b = mb_strtolower(trim($b));
    
    if ($a === $b) {
        return 100.0;
    }
    
    $wordsA = array_filter(preg_split('/\s+/', $a));
    $wordsB = array_filter(preg_split('/\s+/', $b));
    
    if (empty($wordsA) || empty($wordsB)) {
        return 0.0;
    }
    
    $intersection = count(array_intersect($wordsA, $wordsB));
    $union = count(array_unique(array_merge($wordsA, $wordsB)));
    
    $jaccard = $union > 0 ? ($intersection / $union) * 100 : 0;
    $lenA = mb_strlen($a);
    $lenB = mb_strlen($b);
    $lenSim = min($lenA, $lenB) / max($lenA, $lenB) * 100;
    
    return round(($jaccard * 0.7 + $lenSim * 0.3), 1);
}

/**
 * 사용자 수정 내용을 Judgment 패턴으로 학습
 */
function learnFromUserEdit(
    OpenAIService $openai,
    SupabaseService $supabase,
    array $aiOutput,
    array $humanOutput
): array {
    if (!$supabase->isConfigured()) {
        return ['success' => false, 'error' => 'Supabase not configured'];
    }

    // AI 텍스트 조립
    $aiText = '';
    if (!empty($aiOutput['news_title'])) {
        $aiText .= "[제목] " . $aiOutput['news_title'] . "\n\n";
    }
    if (!empty($aiOutput['narration'])) {
        $aiText .= "[내레이션] " . $aiOutput['narration'] . "\n\n";
    }
    if (!empty($aiOutput['why_important'])) {
        $aiText .= "[왜 중요한가] " . $aiOutput['why_important'] . "\n\n";
    }
    if (!empty($aiOutput['content'])) {
        $aiText .= "[본문] " . mb_substr(strip_tags($aiOutput['content']), 0, 2000);
    }

    // Human 텍스트 조립
    $humanText = '';
    if (!empty($humanOutput['news_title'])) {
        $humanText .= "[제목] " . $humanOutput['news_title'] . "\n\n";
    }
    if (!empty($humanOutput['narration'])) {
        $humanText .= "[내레이션] " . $humanOutput['narration'] . "\n\n";
    }
    if (!empty($humanOutput['why_important'])) {
        $humanText .= "[왜 중요한가] " . $humanOutput['why_important'] . "\n\n";
    }
    if (!empty($humanOutput['content'])) {
        $humanText .= "[본문] " . mb_substr(strip_tags($humanOutput['content']), 0, 2000);
    }

    if (mb_strlen($aiText) < 20 || mb_strlen($humanText) < 20) {
        return ['success' => false, 'error' => 'Not enough content to compare'];
    }

    // GPT로 시맨틱 diff 추출
    $system = <<<'SYS'
당신은 뉴스 편집 분석가입니다. AI 초안과 편집장 수정본을 비교하여 "판단 패턴"을 추출합니다.
반드시 요청된 JSON 형식으로만 응답하세요.
SYS;

    $user = <<<USER
[AI 초안]
{$aiText}

[편집장 수정본]
{$humanText}

다음 JSON만 출력하세요:
{
  "judgement_patterns": [
    {
      "category": "분류 (tone, structure, emphasis, style, length, addition, removal 중 하나)",
      "description": "어떤 판단 변화가 있었는지 한 문장",
      "ai_approach": "AI 쪽 경향 (짧게)",
      "editor_correction": "편집장 쪽 선호 (짧게)"
    }
  ],
  "overall_direction": "전체 편집 방향 한 문장"
}

패턴은 최대 5개까지. 의미 있는 차이가 없으면 빈 배열로.
USER;

    try {
        $raw = $openai->chat($system, $user, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'max_tokens' => 1000,
            'timeout' => 60,
            'json_mode' => true,
        ]);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Failed to parse AI response'];
        }

        $patterns = $decoded['judgement_patterns'] ?? [];
        $learnedCount = 0;

        foreach ($patterns as $p) {
            if (!is_array($p)) continue;
            
            $category = trim((string) ($p['category'] ?? 'general'));
            $description = trim((string) ($p['description'] ?? ''));
            if ($description === '') continue;
            
            $aiApproach = isset($p['ai_approach']) ? trim((string) $p['ai_approach']) : null;
            $editorCorrection = isset($p['editor_correction']) ? trim((string) $p['editor_correction']) : null;

            $hash = hash('sha256', $category . "\0" . mb_strtolower($description));

            $existing = $supabase->select('judgement_patterns', 'pattern_hash=eq.' . $hash, 1);
            if (is_array($existing) && $existing !== [] && isset($existing[0]['id'])) {
                $id = $existing[0]['id'];
                $freq = (int) ($existing[0]['frequency'] ?? 0) + 1;
                $weight = min(1.0, $freq / 30.0);
                $supabase->update('judgement_patterns', 'id=eq.' . rawurlencode((string) $id), [
                    'frequency' => $freq,
                    'weight' => $weight,
                    'last_seen_at' => date('c'),
                    'ai_approach' => $aiApproach,
                    'editor_correction' => $editorCorrection,
                ]);
                $learnedCount++;
            } else {
                $result = $supabase->insert('judgement_patterns', [
                    'pattern_hash' => $hash,
                    'category' => mb_substr($category, 0, 200),
                    'description' => mb_substr($description, 0, 2000),
                    'ai_approach' => $aiApproach !== null ? mb_substr($aiApproach, 0, 4000) : null,
                    'editor_correction' => $editorCorrection !== null ? mb_substr($editorCorrection, 0, 4000) : null,
                    'frequency' => 1,
                    'weight' => min(1.0, 1 / 30.0),
                    'is_active' => true,
                    'last_seen_at' => date('c'),
                ]);
                if ($result !== null) {
                    $learnedCount++;
                }
            }
        }

        return [
            'success' => true,
            'learned_patterns' => $learnedCount,
            'overall_direction' => $decoded['overall_direction'] ?? '',
            'patterns' => $patterns,
        ];

    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════
// Main Execution
// ═══════════════════════════════════════════════════════════════

try {
    if ($action === 'learn') {
        // 학습 모드: 사용자 수정 내용을 패턴으로 저장
        $aiOutput = $input['ai_output'] ?? [];
        $humanOutput = $input['human_output'] ?? [];
        
        if (empty($aiOutput) || empty($humanOutput)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ai_output and human_output are required']);
            exit;
        }

        $result = learnFromUserEdit($openai, $supabase, $aiOutput, $humanOutput);
        ob_clean();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // 생성 모드 (기본)
    $url = trim($input['url'] ?? '');
    if ($url === '') {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'url is required']);
        exit;
    }

    $compareWithPublished = (bool) ($input['compare_with_published'] ?? true);

    // 1. Fetch article
    $article = fetchArticleContent($url);
    if (!$article) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => '기사를 가져올 수 없습니다. URL을 확인해주세요.']);
        exit;
    }

    // 2. Load few-shot examples
    $examples = loadRecentPublishedExamples($db, 2);

    // 3. Load Judgment patterns
    $patterns = loadJudgementPatterns($supabase);

    // 4. Get RAG context
    $ragContext = '';
    $ragCounts = ['critiques' => 0, 'analyses' => 0, 'knowledge' => 0];
    if ($rag->isConfigured()) {
        $query = $article['title'] . ' ' . mb_substr($article['content'], 0, 500);
        $context = $rag->retrieveRelevantContext($query, 5);
        $ragCounts = [
            'critiques' => count($context['critiques'] ?? []),
            'analyses' => count($context['analyses'] ?? []),
            'knowledge' => count($context['knowledge'] ?? []),
        ];
        $ragContext = $rag->buildSystemPromptWithRAG('', $context);
        $ragContext = str_replace('--- RAG Context (편집 전문가 지식) ---', '', $ragContext);
    }

    // 5. Build enhanced prompt
    $systemPrompt = buildEnhancedJudgementPrompt($patterns, $examples, $ragContext);

    // 6. Generate with AI
    $userPrompt = <<<USER
다음 기사를 The Gist 스타일로 분석해주세요:

[원문 제목] {$article['title']}

[원문 본문]
{$article['content']}

위에서 설명한 JSON 형식으로만 응답하세요. HTML 태그는 content 필드에만 사용하세요.
USER;

    $response = $openai->chat($systemPrompt, $userPrompt, [
        'model' => 'gpt-4o',
        'temperature' => 0.3,
        'max_tokens' => 2500,
        'timeout' => 120,
        'json_mode' => true,
    ]);

    $aiResult = json_decode($response, true);
    if (!is_array($aiResult)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'AI 응답 파싱 실패', 'raw' => mb_substr($response, 0, 500)]);
        exit;
    }

    // 7. Compare with published article if requested
    $comparison = null;
    $publishedArticle = null;
    if ($compareWithPublished && $db) {
        $stmt = $db->prepare('SELECT id, title, narration, why_important, content FROM news WHERE source_url = ? AND status = ? LIMIT 1');
        $stmt->execute([$url, 'published']);
        $published = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($published) {
            $publishedArticle = [
                'news_title' => $published['title'] ?? '',
                'narration' => $published['narration'] ?? '',
                'why_important' => $published['why_important'] ?? '',
                'content' => $published['content'] ?? '',
            ];

            $differences = [
                [
                    'field' => '제목',
                    'ai_value' => mb_substr($aiResult['news_title'] ?? '', 0, 100),
                    'human_value' => mb_substr($published['title'] ?? '', 0, 100),
                    'similarity' => calculateSimilarity($aiResult['news_title'] ?? '', $published['title'] ?? ''),
                ],
                [
                    'field' => '내레이션',
                    'ai_value' => mb_substr($aiResult['narration'] ?? '', 0, 300),
                    'human_value' => mb_substr($published['narration'] ?? '', 0, 300),
                    'similarity' => calculateSimilarity($aiResult['narration'] ?? '', $published['narration'] ?? ''),
                ],
                [
                    'field' => '왜 중요한가',
                    'ai_value' => mb_substr($aiResult['why_important'] ?? '', 0, 300),
                    'human_value' => mb_substr($published['why_important'] ?? '', 0, 300),
                    'similarity' => calculateSimilarity($aiResult['why_important'] ?? '', $published['why_important'] ?? ''),
                ],
                [
                    'field' => '본문',
                    'ai_value' => mb_substr(strip_tags($aiResult['content'] ?? ''), 0, 500),
                    'human_value' => mb_substr(strip_tags($published['content'] ?? ''), 0, 500),
                    'similarity' => calculateSimilarity($aiResult['content'] ?? '', strip_tags($published['content'] ?? '')),
                ],
            ];

            $totalSim = array_sum(array_column($differences, 'similarity'));
            $matchRate = round($totalSim / count($differences), 1);

            $comparison = [
                'match_rate' => $matchRate,
                'differences' => $differences,
            ];
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'result' => [
            'ai_generated' => [
                'news_title' => $aiResult['news_title'] ?? '',
                'narration' => $aiResult['narration'] ?? '',
                'why_important' => $aiResult['why_important'] ?? '',
                'content' => $aiResult['content'] ?? '',
                'key_points' => $aiResult['key_points'] ?? [],
            ],
            'published_article' => $publishedArticle,
            'applied_patterns' => array_map(function ($p) {
                return [
                    'id' => $p['id'] ?? '',
                    'category' => $p['category'] ?? '',
                    'description' => $p['description'] ?? '',
                    'ai_approach' => $p['ai_approach'] ?? '',
                    'editor_correction' => $p['editor_correction'] ?? '',
                    'weight' => $p['weight'] ?? 0,
                ];
            }, $patterns),
            'few_shot_count' => count($examples),
            'rag_context' => $ragCounts,
            'comparison' => $comparison,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
