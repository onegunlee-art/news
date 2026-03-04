<?php
/**
 * URL 접근 테스트 스크립트
 *
 * ValidationAgent/WebScraperService와 동일한 설정으로 URL 접근 가능 여부를 확인합니다.
 * 수정 전·후에 Economist URL + 다른 뉴스 URL을 실행해 회귀 여부를 확인할 수 있습니다.
 *
 * 사용법:
 *   php scripts/test_url_access.php "https://www.economist.com/..."
 *   php scripts/test_url_access.php "https://url1" "https://url2"
 *   php scripts/test_url_access.php --scrape "https://..."
 *
 * @see DEPLOY_GUIDE.md, config/agents.php (scraper)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('CLI only.');
}

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/src/agents/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoload not found: {$autoload}\n");
    exit(1);
}
require_once $autoload;

$args = array_slice($argv, 1);
$withScrape = false;
$urls = [];
foreach ($args as $arg) {
    if ($arg === '--scrape') {
        $withScrape = true;
        continue;
    }
    if (filter_var($arg, FILTER_VALIDATE_URL)) {
        $urls[] = $arg;
    }
}

if (empty($urls)) {
    echo "Usage: php scripts/test_url_access.php [--scrape] <url> [url2 ...]\n";
    echo "  --scrape  after isAccessible, call scrape() and print title/content length\n";
    exit(1);
}

// Load scraper config (same as pipeline)
$agentsFile = $projectRoot . '/config/agents.php';
$scraperConfig = [];
if (is_file($agentsFile)) {
    $config = require $agentsFile;
    $scraperConfig = $config['scraper'] ?? [];
}

use Agents\Services\WebScraperService;

$scraper = new WebScraperService($scraperConfig);

echo "WebScraperService config: timeout=" . ($scraperConfig['timeout'] ?? 'default')
    . ", skip_head_domains=" . (isset($scraperConfig['skip_head_domains']) ? implode(', ', $scraperConfig['skip_head_domains']) : 'none')
    . "\n\n";

foreach ($urls as $url) {
    echo "URL: {$url}\n";

    $host = parse_url($url, PHP_URL_HOST);
    $skipHead = !empty($scraperConfig['skip_head_domains'])
        && in_array($host, $scraperConfig['skip_head_domains'], true);
    if ($skipHead) {
        echo "  (skip_head_domains: using GET only)\n";
    }

    $ok = $scraper->isAccessible($url);
    // Debug: show last HTTP code if available (optional, for diagnosis)
    if (method_exists($scraper, 'getLastHttpCode')) {
        $code = $scraper->getLastHttpCode();
        if ($code !== null) {
            echo "  last HTTP code: {$code}\n";
        }
    }
    echo "  isAccessible: " . ($ok ? "true" : "false") . "\n";

    if ($withScrape && $ok) {
        try {
            $article = $scraper->scrape($url);
            echo "  scrape: title=" . substr($article->getTitle(), 0, 60) . "...\n";
            echo "  scrape: content_length=" . $article->getContentLength() . "\n";
        } catch (Throwable $e) {
            echo "  scrape error: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

echo "Done.\n";
