<?php
/**
 * NYT API 테스트 페이지
 */

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NYT API Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #00d4ff; }
        h2 { color: #4ecdc4; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .warning { color: #fbbf24; }
        .info { color: #60a5fa; }
        pre { background: #16213e; padding: 15px; border-radius: 8px; overflow-x: auto; }
        .card { background: #16213e; padding: 20px; border-radius: 12px; margin: 15px 0; }
        .article { border-left: 3px solid #00d4ff; padding-left: 15px; margin: 10px 0; }
        .article h3 { margin: 0 0 5px 0; font-size: 16px; }
        .article p { margin: 5px 0; color: #aaa; font-size: 14px; }
        a { color: #00d4ff; }
        .btn { display: inline-block; padding: 10px 20px; background: #00d4ff; color: #000; text-decoration: none; border-radius: 8px; margin: 5px; }
        .btn:hover { background: #00b8d4; }
    </style>
</head>
<body>
    <h1>🗞️ NYT API Test</h1>
';

// Config 확인
$configPath = __DIR__ . '/../config/nyt.php';
if (!file_exists($configPath)) {
    echo '<div class="card"><p class="error">❌ config/nyt.php 파일이 없습니다.</p></div>';
    exit;
}

$config = require $configPath;
$apiKey = $config['api_key'];

echo '<div class="card">';
echo '<h2>📋 설정 정보</h2>';
echo '<p><strong>API Key:</strong> ' . (strlen($apiKey) > 10 ? substr($apiKey, 0, 8) . '***' : '<span class="warning">설정 필요</span>') . '</p>';
echo '<p><strong>Rate Limits:</strong> ' . $config['rate_limits']['requests_per_day'] . ' 요청/일, ' . $config['rate_limits']['requests_per_minute'] . ' 요청/분</p>';
echo '</div>';

// API 테스트
if ($apiKey !== 'YOUR_NYT_API_KEY_HERE' && strlen($apiKey) > 10) {
    echo '<div class="card">';
    echo '<h2>🧪 API 테스트 - Top Stories (Home)</h2>';
    
    $url = 'https://api.nytimes.com/svc/topstories/v2/home.json?api-key=' . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo '<p class="error">❌ cURL 오류: ' . htmlspecialchars($error) . '</p>';
    } elseif ($httpCode !== 200) {
        echo '<p class="error">❌ HTTP ' . $httpCode . '</p>';
        echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
    } else {
        $data = json_decode($response, true);
        $count = count($data['results'] ?? []);
        echo '<p class="success">✅ API 연결 성공! (' . $count . ' 기사)</p>';
        
        // 상위 5개 기사 표시
        echo '<h3>📰 최신 기사 (Top 5)</h3>';
        $articles = array_slice($data['results'] ?? [], 0, 5);
        foreach ($articles as $article) {
            echo '<div class="article">';
            echo '<h3>' . htmlspecialchars($article['title'] ?? 'No title') . '</h3>';
            echo '<p>' . htmlspecialchars(substr($article['abstract'] ?? '', 0, 150)) . '...</p>';
            echo '<p><strong>섹션:</strong> ' . htmlspecialchars($article['section'] ?? 'N/A') . ' | <a href="' . htmlspecialchars($article['url'] ?? '#') . '" target="_blank">원문 보기</a></p>';
            echo '</div>';
        }
    }
    echo '</div>';
} else {
    echo '<div class="card">';
    echo '<h2>⚙️ API Key 설정 방법</h2>';
    echo '<ol>';
    echo '<li><a href="https://developer.nytimes.com/get-started" target="_blank">NYT Developer Portal</a>에서 계정 생성</li>';
    echo '<li>새 앱 등록 후 API Key 발급</li>';
    echo '<li><code>config/nyt.php</code> 파일에서 <code>api_key</code> 수정</li>';
    echo '</ol>';
    echo '<a href="https://developer.nytimes.com/get-started" target="_blank" class="btn">NYT Developer Portal →</a>';
    echo '</div>';
}

// 사용 가능한 섹션 목록
echo '<div class="card">';
echo '<h2>📂 사용 가능한 섹션</h2>';
echo '<p style="line-height: 2;">';
foreach ($config['sections'] as $section) {
    echo '<span style="background: #0f3460; padding: 5px 12px; border-radius: 20px; margin: 3px; display: inline-block;">' . $section . '</span> ';
}
echo '</p>';
echo '</div>';

// API 엔드포인트
echo '<div class="card">';
echo '<h2>🔗 API 엔드포인트</h2>';
echo '<pre>';
echo "GET /api/news/nyt/top?section=home\n";
echo "GET /api/news/nyt/search?q=keyword\n";
echo "GET /api/news/nyt/popular?type=viewed&period=1\n";
echo "GET /api/news/nyt/sections\n";
echo '</pre>';
echo '</div>';

echo '</body></html>';
