<?php
/**
 * URL의 마지막 경로(슬러그)를 추출하여 영어 제목 형태로 변환
 * e.g. "trump-signs-executive-order-2024" → "Trump Signs Executive Order 2024"
 */
function extractTitleFromUrl(?string $url): ?string {
    if ($url === null || trim($url) === '') {
        return null;
    }
    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }
    try {
        if (!preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://' . $trimmed;
        }
        $parsed = parse_url($trimmed);
        if ($parsed === false) {
            return null;
        }
        $path = $parsed['path'] ?? '';
        if ($path === '') {
            return null;
        }
        $segments = array_filter(explode('/', trim($path, '/')));
        if (empty($segments)) {
            return null;
        }
        $slug = end($segments);
        $slug = preg_replace('/\.(html?|php|aspx?)$/i', '', $slug);
        if ($slug === '') {
            return null;
        }
        $words = array_filter(explode('-', $slug));
        if (empty($words)) {
            return null;
        }
        $result = [];
        foreach ($words as $w) {
            $len = strlen($w);
            $result[] = $len > 0 ? (ucfirst(strtolower($w))) : $w;
        }
        return implode(' ', $result) ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}
