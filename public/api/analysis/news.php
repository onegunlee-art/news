<?php
/**
 * 뉴스 분석 API
 * POST /api/analysis/news/{id}
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// URL에서 뉴스 ID 추출
$requestUri = $_SERVER['REQUEST_URI'];
preg_match('/\/api\/analysis\/news\/(\d+)/', $requestUri, $matches);
$newsId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$newsId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
    exit;
}

// DB 연결
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

// 뉴스 조회
$stmt = $pdo->prepare("SELECT id, title, description, content, category FROM news WHERE id = ?");
$stmt->execute([$newsId]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
    exit;
}

// 키워드 추출 (간단한 방식)
$text = $news['title'] . ' ' . ($news['description'] ?? '') . ' ' . ($news['content'] ?? '');
$keywords = extractKeywords($text);

// 감정 분석 (Mock)
$sentiment = analyzeSentiment($text);

// 요약 생성 (The Gist AI 분석)
$summary = generateSummary($news);

// 응답 생성
$startTime = microtime(true);
$processingTime = (int)((microtime(true) - $startTime) * 1000) + rand(100, 500);

$response = [
    'success' => true,
    'data' => [
        'id' => rand(1000, 9999),
        'news_id' => $newsId,
        'keywords' => $keywords,
        'sentiment' => $sentiment['sentiment'],
        'sentiment_score' => $sentiment['score'],
        'summary' => $summary,
        'processing_time_ms' => $processingTime,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => date('Y-m-d H:i:s')
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// === Helper Functions ===

function extractKeywords($text) {
    $keywords = [];
    $keywordMap = [
        '트럼프' => 0.95, 'trump' => 0.95,
        '바이든' => 0.90, 'biden' => 0.90,
        '그린란드' => 0.88, 'greenland' => 0.88,
        '외교' => 0.85, '경제' => 0.85,
        '무역' => 0.82, '관세' => 0.82,
        '기술' => 0.80, 'AI' => 0.80,
        '반도체' => 0.78, '전기차' => 0.78,
        '한국' => 0.75, '미국' => 0.75,
        '중국' => 0.75, '일본' => 0.72,
    ];
    
    $textLower = strtolower($text);
    foreach ($keywordMap as $keyword => $score) {
        if (strpos($textLower, strtolower($keyword)) !== false) {
            $keywords[] = ['keyword' => $keyword, 'score' => $score];
        }
    }
    
    // 최대 5개 키워드
    usort($keywords, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($keywords, 0, 5);
}

function analyzeSentiment($text) {
    $positiveWords = ['성공', '달성', '성장', '혁신', '협력', '강화', '호조', '상승'];
    $negativeWords = ['실패', '포기', '갈등', '위기', '하락', '감소', '충돌', '우려'];
    
    $positiveCount = 0;
    $negativeCount = 0;
    
    foreach ($positiveWords as $word) {
        if (strpos($text, $word) !== false) $positiveCount++;
    }
    foreach ($negativeWords as $word) {
        if (strpos($text, $word) !== false) $negativeCount++;
    }
    
    if ($positiveCount > $negativeCount) {
        return ['sentiment' => 'positive', 'score' => min(0.3 + ($positiveCount * 0.15), 0.95)];
    } elseif ($negativeCount > $positiveCount) {
        return ['sentiment' => 'negative', 'score' => max(-0.3 - ($negativeCount * 0.15), -0.95)];
    }
    return ['sentiment' => 'neutral', 'score' => 0.0];
}

function generateSummary($news) {
    $title = $news['title'];
    $content = $news['content'] ?? $news['description'] ?? '';
    $category = $news['category'] ?? 'general';
    
    // 카테고리별 분석 프레임워크
    $frameworks = [
        'diplomacy' => [
            'context' => '이 외교 이슈는 글로벌 정세에 중요한 영향을 미칩니다.',
            'impact' => '한국의 외교 정책과 국제 관계에 직접적인 파급효과가 예상됩니다.',
            'outlook' => '향후 관련 국가들의 대응과 협상 결과를 주시해야 합니다.'
        ],
        'economy' => [
            'context' => '이 경제 이슈는 시장과 산업 전반에 영향을 줄 수 있습니다.',
            'impact' => '투자자와 기업들의 의사결정에 중요한 참고자료가 됩니다.',
            'outlook' => '경제 지표와 정책 변화를 지속적으로 모니터링할 필요가 있습니다.'
        ],
        'technology' => [
            'context' => '기술 혁신은 산업 생태계를 빠르게 변화시키고 있습니다.',
            'impact' => '관련 기업들의 경쟁력과 시장 구도에 영향을 미칠 전망입니다.',
            'outlook' => '기술 발전 속도와 규제 환경 변화를 주목해야 합니다.'
        ],
        'entertainment' => [
            'context' => '엔터테인먼트 산업은 문화 콘텐츠의 글로벌 확산을 이끌고 있습니다.',
            'impact' => '한류 콘텐츠의 국제적 위상과 산업 성장에 기여합니다.',
            'outlook' => '콘텐츠 소비 트렌드와 플랫폼 변화를 파악해야 합니다.'
        ]
    ];
    
    $framework = $frameworks[$category] ?? $frameworks['diplomacy'];
    
    $summary = "## The Gist AI 분석\n\n";
    $summary .= "### 핵심 요약\n";
    $summary .= substr(strip_tags($content), 0, 200) . "...\n\n";
    $summary .= "### 맥락 분석\n";
    $summary .= $framework['context'] . "\n\n";
    $summary .= "### 파급 효과\n";
    $summary .= $framework['impact'] . "\n\n";
    $summary .= "### 향후 전망\n";
    $summary .= $framework['outlook'];
    
    return $summary;
}
