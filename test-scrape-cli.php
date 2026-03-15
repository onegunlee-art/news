<?php
/**
 * CLI 스크래핑 테스트
 * Usage: php test-scrape-cli.php "URL"
 */

require_once __DIR__ . '/src/agents/autoload.php';

$url = $argv[1] ?? 'https://www.foreignaffairs.com/united-states/perils-militarizing-law-enforcement';

echo "Testing URL: $url\n\n";

try {
    $scraper = new \Agents\Services\WebScraperService(['timeout' => 60]);
    $article = $scraper->scrape($url);

    echo "=== TITLE ===\n";
    echo $article->getTitle() . "\n\n";

    echo "=== SUBTITLE ===\n";
    echo ($article->getDescription() ?? '(none)') . "\n\n";

    echo "=== SUBHEADINGS (" . count($article->getSubheadings()) . ") ===\n";
    foreach ($article->getSubheadings() as $i => $sh) {
        echo ($i + 1) . ". $sh\n";
    }
    echo "\n";

    echo "=== CONTENT LENGTH ===\n";
    echo mb_strlen($article->getContent()) . " chars\n\n";

    echo "=== CONTENT (first 4000 chars) ===\n";
    echo mb_substr($article->getContent(), 0, 4000) . "\n";

    if (mb_strlen($article->getContent()) > 4000) {
        echo "\n... (" . (mb_strlen($article->getContent()) - 4000) . " more chars)\n";
    }

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
