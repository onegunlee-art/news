<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
$config = discoveryConfig($root);
$date = $argv[1] ?? discoveryTodayKst();
$whitelist = new Discovery\SourceWhitelist(
    $config['source_whitelist'] ?? [],
    $config['source_blocklist'] ?? [],
);
$catalog = new Discovery\DiscoveryArticleCatalog($config, $whitelist);
$articles = $catalog->fetch($date);

echo "=== Discovery RSS Catalog date={$date} ===\n";
echo 'article_count=' . count($articles) . "\n\n";

foreach (array_slice($articles, 0, 15) as $article) {
    echo sprintf(
        "#%d [%s] %s\n  url=%s\n  published=%s\n\n",
        (int) $article['index'],
        $article['source_name'],
        mb_substr($article['title'], 0, 90),
        $article['url'],
        $article['published_at'] ?: '?'
    );
}
