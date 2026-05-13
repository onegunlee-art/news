<?php
/**
 * AGI Playground API - Judgment RAG 적용 생성 + 정합률 측정
 *
 * POST { url, compare_with_published } → AI 생성 결과 + 정합률
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
if (!$input || empty(trim($input['url'] ?? ''))) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'url is required']);
    exit;
}

$url = trim($input['url']);
$compareWithPublished = (bool) ($input['compare_with_published'] ?? true);

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

// Database connection for comparison
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
        // Continue without DB comparison
    }
}

// Fetch article content
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

    // Extract title
    $title = '';
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }

    // Extract main content (simplified)
    $content = '';
    
    // Remove scripts and styles
    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
    
    // Try to find article body
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
        $content = strip_tags($m[1]);
    } elseif (preg_match('/<div[^>]+class=["\'][^"\']*(?:article|content|story|post)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
        $content = strip_tags($m[1]);
    } else {
        // Fallback: extract all paragraph text
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

// Load Judgment patterns
function loadJudgementPatterns(SupabaseService $supabase, int $minFrequency = 2): array
{
    if (!$supabase->isConfigured()) {
        return [];
    }
    $patterns = $supabase->select(
        'judgement_patterns',
        'is_active=eq.true&frequency=gte.' . $minFrequency . '&order=weight.desc',
        20
    );
    return is_array($patterns) ? $patterns : [];
}

// Build Judgment-enhanced system prompt
function buildJudgementPrompt(array $patterns, string $ragContext): string
{
    $basePrompt = <<<'PROMPT'
당신은 "The Gist"의 수석 에디터입니다. 해외 뉴스를 한국어로 분석하여 독자가 핵심을 빠르게 파악할 수 있도록 합니다.

반드시 다음 JSON 형식으로만 응답하세요:
{
  "news_title": "한국어 제목",
  "narration": "2-3문장의 핵심 내레이션",
  "why_important": "왜 이 뉴스가 중요한지 설명",
  "content_summary": "본문 요약 (300-500자)",
  "key_points": ["핵심 포인트 1", "핵심 포인트 2", ...]
}
PROMPT;

    // Add Judgment patterns if available
    if (!empty($patterns)) {
        $patternSection = "\n\n[편집장 판단 패턴 - 이 기준을 반영하세요]\n";
        foreach ($patterns as $p) {
            $cat = $p['category'] ?? 'general';
            $desc = $p['description'] ?? '';
            $correction = $p['editor_correction'] ?? '';
            if ($correction) {
                $patternSection .= "- [{$cat}] {$desc} → 편집장 선호: {$correction}\n";
            } else {
                $patternSection .= "- [{$cat}] {$desc}\n";
            }
        }
        $basePrompt .= $patternSection;
    }

    // Add RAG context
    if (!empty($ragContext)) {
        $basePrompt .= "\n\n" . $ragContext;
    }

    return $basePrompt;
}

// Calculate text similarity (simple)
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
    
    // Word overlap similarity
    $wordsA = array_filter(preg_split('/\s+/', $a));
    $wordsB = array_filter(preg_split('/\s+/', $b));
    
    if (empty($wordsA) || empty($wordsB)) {
        return 0.0;
    }
    
    $intersection = count(array_intersect($wordsA, $wordsB));
    $union = count(array_unique(array_merge($wordsA, $wordsB)));
    
    $jaccard = $union > 0 ? ($intersection / $union) * 100 : 0;
    
    // Also consider length similarity
    $lenA = mb_strlen($a);
    $lenB = mb_strlen($b);
    $lenSim = min($lenA, $lenB) / max($lenA, $lenB) * 100;
    
    return round(($jaccard * 0.7 + $lenSim * 0.3), 1);
}

// Main execution
try {
    // 1. Fetch article
    $article = fetchArticleContent($url);
    if (!$article) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => '기사를 가져올 수 없습니다. URL을 확인해주세요.']);
        exit;
    }

    // 2. Load Judgment patterns
    $patterns = loadJudgementPatterns($supabase);

    // 3. Get RAG context
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

    // 4. Build enhanced prompt
    $systemPrompt = buildJudgementPrompt($patterns, $ragContext);

    // 5. Generate with AI
    $userPrompt = <<<USER
다음 기사를 분석해주세요:

[제목] {$article['title']}

[본문]
{$article['content']}

위의 JSON 형식으로만 응답하세요.
USER;

    $response = $openai->chat($systemPrompt, $userPrompt, [
        'model' => 'gpt-4o',
        'temperature' => 0.3,
        'max_tokens' => 2000,
        'timeout' => 120,
        'json_mode' => true,
    ]);

    $aiResult = json_decode($response, true);
    if (!is_array($aiResult)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'AI 응답 파싱 실패', 'raw' => mb_substr($response, 0, 500)]);
        exit;
    }

    // 6. Compare with published article if requested
    $comparison = null;
    if ($compareWithPublished && $db) {
        $stmt = $db->prepare('SELECT id, title, narration, why_important, content FROM news WHERE source_url = ? AND status = ? LIMIT 1');
        $stmt->execute([$url, 'published']);
        $published = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($published) {
            $differences = [
                [
                    'field' => '제목',
                    'ai_value' => mb_substr($aiResult['news_title'] ?? '', 0, 100),
                    'human_value' => mb_substr($published['title'] ?? '', 0, 100),
                    'similarity' => calculateSimilarity($aiResult['news_title'] ?? '', $published['title'] ?? ''),
                ],
                [
                    'field' => '내레이션',
                    'ai_value' => mb_substr($aiResult['narration'] ?? '', 0, 200),
                    'human_value' => mb_substr($published['narration'] ?? '', 0, 200),
                    'similarity' => calculateSimilarity($aiResult['narration'] ?? '', $published['narration'] ?? ''),
                ],
                [
                    'field' => '왜 중요한가',
                    'ai_value' => mb_substr($aiResult['why_important'] ?? '', 0, 200),
                    'human_value' => mb_substr($published['why_important'] ?? '', 0, 200),
                    'similarity' => calculateSimilarity($aiResult['why_important'] ?? '', $published['why_important'] ?? ''),
                ],
                [
                    'field' => '본문',
                    'ai_value' => mb_substr($aiResult['content_summary'] ?? '', 0, 300),
                    'human_value' => mb_substr(strip_tags($published['content'] ?? ''), 0, 300),
                    'similarity' => calculateSimilarity($aiResult['content_summary'] ?? '', strip_tags($published['content'] ?? '')),
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
                'content_summary' => $aiResult['content_summary'] ?? '',
                'key_points' => $aiResult['key_points'] ?? [],
            ],
            'applied_patterns' => array_map(function ($p) {
                return [
                    'id' => $p['id'] ?? '',
                    'category' => $p['category'] ?? '',
                    'description' => $p['description'] ?? '',
                    'weight' => $p['weight'] ?? 0,
                ];
            }, $patterns),
            'rag_context' => $ragCounts,
            'comparison' => $comparison,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
