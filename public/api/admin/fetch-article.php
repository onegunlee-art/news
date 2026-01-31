<?php
/**
 * URL에서 기사 정보 추출 API
 * GET: ?url=<article_url>
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// URL 파라미터 확인
$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'message' => 'URL이 필요합니다.'
    ]);
    exit;
}

// URL 유효성 검사
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode([
        'success' => false,
        'message' => '유효한 URL이 아닙니다.'
    ]);
    exit;
}

try {
    // URL에서 HTML 가져오기
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7'
            ],
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        throw new Exception('URL에서 콘텐츠를 가져올 수 없습니다.');
    }

    // 인코딩 처리
    $encoding = mb_detect_encoding($html, ['UTF-8', 'EUC-KR', 'ISO-8859-1'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    // 메타 데이터 추출
    $title = '';
    $description = '';
    $image = '';

    // Open Graph 태그 추출
    // og:title
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) ||
        preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\'][^>]*>/i', $html, $matches)) {
        $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    // og:description
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) ||
        preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:description["\'][^>]*>/i', $html, $matches)) {
        $description = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    // og:image
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) ||
        preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
        $image = $matches[1];
    }

    // 일반 메타 태그에서 fallback
    if (empty($title)) {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }
    }

    if (empty($description)) {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) ||
            preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $matches)) {
            $description = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
    }

    // 기사 본문 추출 시도 (article 태그 또는 특정 클래스)
    $content = '';
    
    // article 태그에서 p 태그 추출
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $articleMatch)) {
        if (preg_match_all('/<p[^>]*>([^<]+)<\/p>/i', $articleMatch[1], $pMatches)) {
            $paragraphs = array_filter($pMatches[1], function($p) {
                return strlen(trim($p)) > 50; // 50자 이상인 단락만
            });
            $content = implode("\n\n", array_map('trim', $paragraphs));
        }
    }

    // 본문이 없으면 description 사용
    if (empty($content)) {
        $content = $description;
    }

    // 결과 반환
    echo json_encode([
        'success' => true,
        'data' => [
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'image' => $image,
            'url' => $url
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
