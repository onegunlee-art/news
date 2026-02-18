<?php
/**
 * 원문 URL의 HTML에서 <title> 태그 추출
 * 
 * @param string|null $url 원문 기사 URL
 * @return string|null 추출된 제목, 실패 시 null
 */
function extractTitleFromHtml(?string $url): ?string {
    if ($url === null || trim($url) === '') {
        return null;
    }
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $html = fetchHtml($url);
    if ($html === null || $html === '') {
        return null;
    }

    // <title>...</title> 추출 (대소문자 무시)
    if (preg_match('#<title[^>]*>([^<]+)</title>#is', $html, $m)) {
        $title = trim($m[1]);
        // HTML 엔티티 디코딩
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // " | Site Name" 등 제거 (선택적 - 원문 제목만 추출)
        if (preg_match('/^(.+?)\s*[\|\-–—]\s*.+$/u', $title, $split)) {
            $title = trim($split[1]);
        }
        return $title !== '' ? $title : null;
    }
    return null;
}

/**
 * URL에서 HTML 가져오기 (cURL 사용, User-Agent 설정)
 */
function fetchHtml(string $url): ?string {
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
    ]);
    $html = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $html === false) {
        return null;
    }
    return is_string($html) ? $html : null;
}
