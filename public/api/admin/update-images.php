<?php
/**
 * 뉴스 이미지 자동 매칭 API
 * 저작권 무료 이미지 (Unsplash) 자동 적용
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 간단한 PDO 연결
$host = 'localhost';
$dbname = 'ailand';
$username = 'ailand';
$password = 'ektlf1212';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}

// 키워드 매핑
$keywordMap = [
    // 인물
    '트럼프' => 'trump,president,politics',
    'trump' => 'trump,president,politics',
    '바이든' => 'biden,president,whitehouse',
    'biden' => 'biden,president,whitehouse',
    '시진핑' => 'china,politics,beijing',
    '푸틴' => 'russia,kremlin,politics',
    '윤석열' => 'korea,seoul,politics',
    '김정은' => 'northkorea,politics',
    '일론' => 'tesla,spacex,technology',
    '머스크' => 'tesla,spacex,technology',
    'elon' => 'tesla,spacex,technology',
    'musk' => 'tesla,spacex,technology',
    'openai' => 'artificial-intelligence,robot,technology',
    'chatgpt' => 'artificial-intelligence,chat,technology',
    'gpt' => 'artificial-intelligence,technology',
    
    // 경제/금융
    '주식' => 'stock-market,trading,finance',
    '증시' => 'stock-market,wall-street,finance',
    '금리' => 'bank,finance,money',
    '환율' => 'currency,money,exchange',
    '비트코인' => 'bitcoin,cryptocurrency,blockchain',
    '코인' => 'cryptocurrency,bitcoin,blockchain',
    '부동산' => 'real-estate,building,city',
    '경제' => 'economy,business,finance',
    '무역' => 'trade,shipping,container',
    '관세' => 'trade,customs,shipping',
    
    // 기술
    'ai' => 'artificial-intelligence,robot,technology',
    '인공지능' => 'artificial-intelligence,robot,future',
    '반도체' => 'semiconductor,chip,technology',
    '배터리' => 'battery,electric,energy',
    '전기차' => 'electric-car,tesla,automotive',
    '자율주행' => 'self-driving,car,technology',
    '로봇' => 'robot,automation,technology',
    '우주' => 'space,rocket,nasa',
    
    // 외교/정치
    '외교' => 'diplomacy,handshake,politics',
    '정상회담' => 'summit,diplomacy,politics',
    'nato' => 'nato,military,alliance',
    '유엔' => 'united-nations,diplomacy,global',
    '전쟁' => 'war,military,conflict',
    '우크라이나' => 'ukraine,europe,politics',
    '대만' => 'taiwan,asia,politics',
    '북한' => 'northkorea,military,politics',
    '핵' => 'nuclear,energy,military',
    '그린란드' => 'greenland,arctic,ice',
    
    // 엔터테인먼트
    'k-pop' => 'kpop,concert,music',
    '케이팝' => 'kpop,concert,music',
    'kpop' => 'kpop,concert,music',
    'bts' => 'concert,music,performance',
    '영화' => 'movie,cinema,film',
    '드라마' => 'television,drama,entertainment',
    
    // 기타
    '기후' => 'climate,environment,nature',
    '환경' => 'environment,nature,green',
    '에너지' => 'energy,solar,windmill',
];

// 카테고리별 기본값
$categoryDefaults = [
    'diplomacy' => 'diplomacy,politics,globe,summit',
    'economy' => 'economy,business,finance,stock-market',
    'technology' => 'technology,innovation,future,digital',
    'entertainment' => 'entertainment,music,movie,celebrity',
];

function extractKeywords($title, $category, $keywordMap, $categoryDefaults) {
    $title = mb_strtolower($title);
    $foundKeywords = [];
    
    foreach ($keywordMap as $keyword => $searchTerms) {
        if (mb_strpos($title, mb_strtolower($keyword)) !== false) {
            $foundKeywords[] = $searchTerms;
            if (count($foundKeywords) >= 2) {
                break;
            }
        }
    }
    
    if (empty($foundKeywords)) {
        $category = strtolower($category ?? '');
        if (isset($categoryDefaults[$category])) {
            return $categoryDefaults[$category];
        }
        return 'news,newspaper,global';
    }
    
    $allKeywords = [];
    foreach ($foundKeywords as $kw) {
        $allKeywords = array_merge($allKeywords, explode(',', $kw));
    }
    return implode(',', array_unique($allKeywords));
}

function getImageUrl($title, $category, $keywordMap, $categoryDefaults, $width = 800, $height = 500) {
    $keywords = extractKeywords($title, $category, $keywordMap, $categoryDefaults);
    $seed = substr(md5($title . time()), 0, 8);
    return "https://source.unsplash.com/{$width}x{$height}/?{$keywords}&sig={$seed}";
}

// API 동작
$action = $_GET['action'] ?? $_POST['action'] ?? 'update_all';

if ($action === 'update_all') {
    // 모든 뉴스 이미지 업데이트
    $stmt = $pdo->query("SELECT id, title, category, image_url FROM news ORDER BY id DESC");
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = [];
    
    foreach ($newsList as $news) {
        $newImageUrl = getImageUrl($news['title'], $news['category'] ?? '', $keywordMap, $categoryDefaults);
        
        $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
        $updateStmt->execute([$newImageUrl, $news['id']]);
        
        $updated[] = [
            'id' => $news['id'],
            'title' => mb_substr($news['title'], 0, 50) . '...',
            'keywords' => extractKeywords($news['title'], $news['category'] ?? '', $keywordMap, $categoryDefaults),
            'new_image' => $newImageUrl
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '모든 뉴스 이미지가 업데이트되었습니다.',
        'total' => count($newsList),
        'updated' => $updated
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} elseif ($action === 'update_one') {
    // 특정 뉴스 이미지 업데이트
    $newsId = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$newsId) {
        echo json_encode(['success' => false, 'error' => 'News ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, title, category FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$news) {
        echo json_encode(['success' => false, 'error' => 'News not found']);
        exit;
    }
    
    $newImageUrl = getImageUrl($news['title'], $news['category'] ?? '', $keywordMap, $categoryDefaults);
    
    $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
    $updateStmt->execute([$newImageUrl, $newsId]);
    
    echo json_encode([
        'success' => true,
        'id' => $newsId,
        'title' => $news['title'],
        'keywords' => extractKeywords($news['title'], $news['category'] ?? '', $keywordMap, $categoryDefaults),
        'new_image' => $newImageUrl
    ], JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'preview') {
    // 이미지 미리보기 (업데이트 없이 URL만 생성)
    $title = $_GET['title'] ?? $_POST['title'] ?? '';
    $category = $_GET['category'] ?? $_POST['category'] ?? '';
    
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title required']);
        exit;
    }
    
    $keywords = extractKeywords($title, $category, $keywordMap, $categoryDefaults);
    $imageUrl = getImageUrl($title, $category, $keywordMap, $categoryDefaults);
    
    echo json_encode([
        'success' => true,
        'title' => $title,
        'category' => $category,
        'keywords' => $keywords,
        'image_url' => $imageUrl
    ], JSON_UNESCAPED_UNICODE);
}
