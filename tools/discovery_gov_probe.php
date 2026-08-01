<?php
declare(strict_types=1);

/**
 * Probe government/international org RSS feeds to see which ones actually work.
 * Usage: php tools/discovery_gov_probe.php
 */

echo "=== Government & International Org RSS Feed Probe ===\n\n";

$feedsToProbe = [
    // US Government
    ['name' => 'White House', 'url' => 'https://www.whitehouse.gov/feed/'],
    ['name' => 'White House Briefings', 'url' => 'https://www.whitehouse.gov/briefing-room/feed/'],
    ['name' => 'State Dept', 'url' => 'https://www.state.gov/rss-feed/press-releases/feed/'],
    ['name' => 'State Dept Travel', 'url' => 'https://www.state.gov/feed/'],
    ['name' => 'Treasury', 'url' => 'https://home.treasury.gov/news/press-releases/rss.xml'],
    ['name' => 'Defense.gov', 'url' => 'https://www.defense.gov/News/feed/'],
    ['name' => 'Fed Reserve', 'url' => 'https://www.federalreserve.gov/feeds/press_all.xml'],
    
    // International Orgs
    ['name' => 'UN News', 'url' => 'https://news.un.org/feed/subscribe/en/news/all/rss.xml'],
    ['name' => 'UN News Topic', 'url' => 'https://news.un.org/feed/subscribe/en/news/topic/peace-and-security/feed/rss.xml'],
    ['name' => 'NATO News', 'url' => 'https://www.nato.int/cps/en/natohq/news.xml'],
    ['name' => 'NATO Topics', 'url' => 'https://www.nato.int/cps/en/natohq/topics.xml'],
    ['name' => 'EU Newsroom', 'url' => 'https://ec.europa.eu/commission/presscorner/api/rss'],
    ['name' => 'EU External', 'url' => 'https://www.eeas.europa.eu/eeas/rss.xml'],
    ['name' => 'IMF News', 'url' => 'https://www.imf.org/en/News/rss'],
    ['name' => 'IMF Podcast', 'url' => 'https://www.imf.org/en/News/Podcasts/rss/imf-podcasts.xml'],
    ['name' => 'World Bank News', 'url' => 'https://www.worldbank.org/en/news/rss.xml'],
    ['name' => 'World Bank API', 'url' => 'https://search.worldbank.org/api/v2/news?format=rss'],
    ['name' => 'WHO News', 'url' => 'https://www.who.int/rss-feeds/news-english.xml'],
    ['name' => 'WHO Features', 'url' => 'https://www.who.int/feeds/entity/mediacentre/news/en/rss.xml'],
    ['name' => 'WTO News', 'url' => 'https://www.wto.org/english/news_e/news_e.rss'],
    ['name' => 'OECD News', 'url' => 'https://www.oecd.org/newsroom/index.xml'],
    ['name' => 'ECB Press', 'url' => 'https://www.ecb.europa.eu/rss/press.html'],
    
    // Other governments
    ['name' => 'UK Gov News', 'url' => 'https://www.gov.uk/government/news.atom'],
    ['name' => 'UK Foreign Office', 'url' => 'https://www.gov.uk/government/organisations/foreign-commonwealth-development-office.atom'],
    ['name' => 'Japan MOFA', 'url' => 'https://www.mofa.go.jp/rss/index.xml'],
    ['name' => 'Japan MOFA Press', 'url' => 'https://www.mofa.go.jp/press/release/rss/index.xml'],
    
    // Think tanks
    ['name' => 'CSIS', 'url' => 'https://www.csis.org/analysis/feed'],
    ['name' => 'Brookings', 'url' => 'https://www.brookings.edu/feed/'],
    ['name' => 'Carnegie', 'url' => 'https://carnegieendowment.org/rss/solr/?fa=experts'],
    ['name' => 'Chatham House', 'url' => 'https://www.chathamhouse.org/rss.xml'],
    ['name' => 'CFR', 'url' => 'https://www.cfr.org/rss.xml'],
    ['name' => 'Atlantic Council', 'url' => 'https://www.atlanticcouncil.org/feed/'],
    ['name' => 'RAND', 'url' => 'https://www.rand.org/news/press.xml'],
    ['name' => 'War on Rocks', 'url' => 'https://warontherocks.com/feed/'],
];

$working = [];
$failed = [];

foreach ($feedsToProbe as $feed) {
    $name = $feed['name'];
    $url = $feed['url'];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*'],
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($body === false || $httpCode < 200 || $httpCode >= 400) {
        $reason = $httpCode > 0 ? "HTTP {$httpCode}" : ($error ?: 'connection failed');
        echo sprintf("❌ %-25s FAIL (%s)\n", $name, $reason);
        $failed[] = ['name' => $name, 'url' => $url, 'reason' => $reason];
        continue;
    }
    
    // Try to parse as XML
    $xml = @simplexml_load_string($body);
    if ($xml === false) {
        // Check if it's HTML (common error page)
        if (stripos($body, '<html') !== false) {
            echo sprintf("❌ %-25s FAIL (returns HTML, not RSS)\n", $name);
            $failed[] = ['name' => $name, 'url' => $url, 'reason' => 'returns HTML'];
            continue;
        }
        echo sprintf("❌ %-25s FAIL (invalid XML)\n", $name);
        $failed[] = ['name' => $name, 'url' => $url, 'reason' => 'invalid XML'];
        continue;
    }
    
    // Count items
    $itemCount = 0;
    $sampleTitle = '';
    $sampleUrl = '';
    $sampleDomain = '';
    
    if (isset($xml->channel->item)) {
        $itemCount = count($xml->channel->item);
        if ($itemCount > 0) {
            $sampleTitle = (string) ($xml->channel->item[0]->title ?? '');
            $sampleUrl = (string) ($xml->channel->item[0]->link ?? $xml->channel->item[0]->guid ?? '');
        }
    } elseif (isset($xml->entry)) {
        $itemCount = count($xml->entry);
        if ($itemCount > 0) {
            $sampleTitle = (string) ($xml->entry[0]->title ?? '');
            foreach ($xml->entry[0]->link ?? [] as $link) {
                $attrs = $link->attributes();
                if ($attrs) {
                    $sampleUrl = (string) ($attrs['href'] ?? '');
                    if ($sampleUrl) break;
                }
            }
        }
    }
    
    if ($itemCount === 0) {
        echo sprintf("⚠️  %-25s OK but 0 items\n", $name);
        $failed[] = ['name' => $name, 'url' => $url, 'reason' => '0 items'];
        continue;
    }
    
    if ($sampleUrl !== '') {
        $host = parse_url($sampleUrl, PHP_URL_HOST) ?: '';
        $sampleDomain = preg_replace('/^www\./', '', $host) ?: $host;
    }
    
    echo sprintf("✅ %-25s OK items=%d domain=%s\n", $name, $itemCount, $sampleDomain);
    $working[] = [
        'name' => $name,
        'url' => $url,
        'items' => $itemCount,
        'domain' => $sampleDomain,
        'sample_title' => mb_substr($sampleTitle, 0, 60),
    ];
}

echo "\n=== Summary ===\n";
echo "Working: " . count($working) . "\n";
echo "Failed: " . count($failed) . "\n";

echo "\n=== Working Feeds (copy to discovery_feeds.php) ===\n";
foreach ($working as $feed) {
    echo sprintf("['name' => '%s', 'url' => '%s'],  // items=%d\n", $feed['name'], $feed['url'], $feed['items']);
}

echo "\n=== Failed Feeds ===\n";
foreach ($failed as $feed) {
    echo sprintf("  %s: %s\n", $feed['name'], $feed['reason']);
}

echo "\n=== Sample Articles from Working Feeds ===\n";
foreach (array_slice($working, 0, 10) as $feed) {
    echo sprintf("%s: %s\n", $feed['name'], $feed['sample_title']);
}
