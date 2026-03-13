<?php
/**
 * 스크래핑 테스트 스크립트
 * Usage: ?url=https://foreignaffairs.com/...
 */

header('Content-Type: text/html; charset=utf-8');

// 프로젝트 루트 찾기
function findProjectRoot(): string {
    $candidates = [
        __DIR__ . '/../',
        __DIR__ . '/../../',
    ];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . '/src/agents/autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new \RuntimeException('Project root not found');
}

$projectRoot = findProjectRoot();
require_once $projectRoot . 'src/agents/autoload.php';

$url = $_GET['url'] ?? '';

echo '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Scrape Test</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #00d4ff; }
        h2 { color: #4ecdc4; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .card { background: #16213e; padding: 20px; border-radius: 12px; margin: 15px 0; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .warning { color: #fbbf24; }
        pre { background: #0f3460; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 600px; overflow-y: auto; }
        input[type="text"] { width: 70%; padding: 10px; border-radius: 8px; border: none; background: #0f3460; color: #eee; }
        button { padding: 10px 20px; background: #00d4ff; color: #000; border: none; border-radius: 8px; cursor: pointer; }
        .subheading { background: #4ecdc4; color: #000; padding: 5px 10px; border-radius: 5px; margin: 5px; display: inline-block; }
    </style>
</head>
<body>
    <h1>🔍 Scrape Test</h1>
    
    <div class="card">
        <form method="get">
            <input type="text" name="url" placeholder="https://foreignaffairs.com/..." value="' . htmlspecialchars($url) . '">
            <button type="submit">테스트</button>
        </form>
    </div>';

if ($url !== '') {
    try {
        $startTime = microtime(true);
        
        $scraper = new \Agents\Services\WebScraperService(['timeout' => 60]);
        $article = $scraper->scrape($url);
        
        $elapsed = round((microtime(true) - $startTime) * 1000);
        $content = $article->getContent();
        $contentLength = mb_strlen($content);
        $subheadings = $article->getSubheadings();
        
        echo '<div class="card">';
        echo '<h2>📊 결과 요약</h2>';
        echo '<p><strong>소요 시간:</strong> ' . $elapsed . 'ms</p>';
        echo '<p><strong>제목 (Title):</strong> ' . htmlspecialchars($article->getTitle()) . '</p>';
        echo '<p><strong>부제목 (Subtitle):</strong> ' . htmlspecialchars($article->getDescription() ?? '(없음)') . '</p>';
        echo '<p><strong>본문 길이:</strong> <span class="' . ($contentLength >= 500 ? 'success' : 'error') . '">' . number_format($contentLength) . '자</span>';
        if ($contentLength < 500) {
            echo ' <span class="warning">⚠️ 본문이 너무 짧습니다 (페이월 가능성)</span>';
        }
        echo '</p>';
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>📑 소제목 (Subheadings) - ' . count($subheadings) . '개</h2>';
        if (empty($subheadings)) {
            echo '<p class="warning">소제목이 감지되지 않았습니다.</p>';
        } else {
            foreach ($subheadings as $i => $sh) {
                echo '<span class="subheading">' . ($i + 1) . '. ' . htmlspecialchars($sh) . '</span> ';
            }
        }
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>📄 본문 내용 (처음 5000자)</h2>';
        echo '<pre>' . htmlspecialchars(mb_substr($content, 0, 5000)) . '</pre>';
        if ($contentLength > 5000) {
            echo '<p class="warning">... (' . number_format($contentLength - 5000) . '자 더 있음)</p>';
        }
        echo '</div>';
        
        // 메타데이터
        echo '<div class="card">';
        echo '<h2>📋 메타데이터</h2>';
        echo '<pre>' . htmlspecialchars(json_encode([
            'url' => $article->getUrl(),
            'author' => $article->getAuthor(),
            'publishedAt' => $article->getPublishedAt(),
            'source' => $article->getSource(),
            'language' => $article->getLanguage(),
            'metadata' => $article->getMetadata(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
        echo '</div>';
        
    } catch (\Throwable $e) {
        echo '<div class="card">';
        echo '<h2 class="error">❌ 오류 발생</h2>';
        echo '<pre class="error">' . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
}

echo '</body></html>';
