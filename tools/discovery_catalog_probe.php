<?php
declare(strict_types=1);

/**
 * Probe the RSS catalog to see which feeds are actually returning articles.
 * Usage: php tools/discovery_catalog_probe.php [YYYY-MM-DD]
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$config = discoveryConfig($root);
$whitelist = new Discovery\SourceWhitelist(
    $config['source_whitelist'] ?? [],
    $config['source_blocklist'] ?? [],
);
$catalog = new Discovery\DiscoveryArticleCatalog($config, $whitelist);

$date = $argv[1] ?? discoveryTodayKst();
echo "=== Discovery Catalog Probe date={$date} ===\n\n";

$feeds = $config['rss_feeds'] ?? [];
echo "--- Feed Status ---\n";

$feedStats = [];
foreach ($feeds as $feed) {
    $name = (string) ($feed['name'] ?? 'Unknown');
    $url = (string) ($feed['url'] ?? '');
    
    if ($url === '') {
        echo sprintf("%-25s SKIP (no url)\n", $name);
        continue;
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GistDiscoveryCatalog/1.0)',
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($body === false || $httpCode < 200 || $httpCode >= 400) {
        echo sprintf("%-25s FAIL http=%d\n", $name, $httpCode);
        $feedStats[$name] = ['status' => 'fail', 'http' => $httpCode, 'count' => 0];
        continue;
    }
    
    $root = @simplexml_load_string($body);
    if ($root === false) {
        echo sprintf("%-25s FAIL (invalid XML)\n", $name);
        $feedStats[$name] = ['status' => 'fail', 'http' => $httpCode, 'count' => 0];
        continue;
    }
    
    $itemCount = 0;
    $sampleUrl = '';
    $sampleDomain = '';
    
    if (isset($root->channel->item)) {
        $itemCount = count($root->channel->item);
        if ($itemCount > 0) {
            $firstItem = $root->channel->item[0];
            $sampleUrl = (string) ($firstItem->link ?? $firstItem->guid ?? '');
        }
    } elseif (isset($root->entry)) {
        $itemCount = count($root->entry);
        if ($itemCount > 0) {
            foreach ($root->entry[0]->link ?? [] as $linkNode) {
                $attrs = $linkNode->attributes();
                if ($attrs) {
                    $sampleUrl = (string) ($attrs['href'] ?? '');
                    break;
                }
            }
        }
    }
    
    if ($sampleUrl !== '') {
        $host = parse_url($sampleUrl, PHP_URL_HOST) ?: '';
        $sampleDomain = preg_replace('/^www\./', '', $host) ?: $host;
    }
    
    $whitelisted = $sampleUrl !== '' && $whitelist->isWhitelistedUrl($sampleUrl);
    $whitelistStatus = $whitelisted ? 'whitelist=OK' : 'whitelist=FAIL';
    
    echo sprintf("%-25s OK items=%d domain=%s %s\n", $name, $itemCount, $sampleDomain, $whitelistStatus);
    $feedStats[$name] = [
        'status' => 'ok',
        'http' => $httpCode,
        'count' => $itemCount,
        'domain' => $sampleDomain,
        'whitelist' => $whitelisted,
        'sample_url' => $sampleUrl,
    ];
}

echo "\n--- Catalog Fetch Result ---\n";
$articles = $catalog->fetch($date);
echo "total_articles=" . count($articles) . "\n\n";

$bySource = [];
foreach ($articles as $article) {
    $source = $article['source_name'];
    if (!isset($bySource[$source])) {
        $bySource[$source] = 0;
    }
    $bySource[$source]++;
}

arsort($bySource);
echo "--- Articles by Source ---\n";
foreach ($bySource as $source => $count) {
    echo sprintf("  %-25s %d\n", $source, $count);
}

echo "\n--- Sample Articles (first 5) ---\n";
foreach (array_slice($articles, 0, 5) as $i => $article) {
    $host = parse_url($article['url'], PHP_URL_HOST) ?: '';
    echo sprintf("#%d [%s] %s\n   url: %s\n\n", $i + 1, $article['source_name'], $article['title'], $article['url']);
}

echo "\n--- Whitelist Domains vs Actual ---\n";
$actualDomains = [];
foreach ($articles as $article) {
    $host = parse_url($article['url'], PHP_URL_HOST) ?: '';
    $domain = preg_replace('/^www\./', '', $host) ?: $host;
    $actualDomains[$domain] = ($actualDomains[$domain] ?? 0) + 1;
}
arsort($actualDomains);
foreach ($actualDomains as $domain => $count) {
    echo sprintf("  %-30s %d articles\n", $domain, $count);
}
