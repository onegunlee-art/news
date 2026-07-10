<?php
declare(strict_types=1);
/**
 * FA UN 기사 스크래핑 — 구독(SUBSCRIBE) 소제목 유입 여부 확인
 *
 * Usage:
 *   php tools/_fa_subscribe_subheading_probe.php
 *   php tools/_fa_subscribe_subheading_probe.php "https://www.foreignaffairs.com/ukraine/how-save-irrelevance"
 */
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\WebScraperService;

$url = $argv[1] ?? 'https://www.foreignaffairs.com/ukraine/how-save-irrelevance';

$scraper = new WebScraperService(['verify_ssl' => false]);

// 1) Synthetic HTML — filter 동작은 페이월과 무관하게 확인
$ref = new ReflectionClass(WebScraperService::class);
$parseHtml = $ref->getMethod('parseHtml');
$parseHtml->setAccessible(true);
$syntheticHtml = <<<'HTML'
<!DOCTYPE html><html><body>
<article>
<strong>SUBSCRIBE TO FOREIGN AFFAIRS THIS WEEK</strong>
<strong>NATIONS FOR PEACE</strong>
<p>Section text.</p>
<strong>SETTING THE STAGE</strong>
</article>
</body></html>
HTML;
$synthetic = $parseHtml->invoke($scraper, $url, $syntheticHtml);
$synthSubs = $synthetic->getSubheadings();

echo "=== Synthetic HTML filter check ===\n";
echo 'subheadings: ' . implode(', ', $synthSubs) . "\n";
$synthOk = !in_array('SUBSCRIBE TO FOREIGN AFFAIRS THIS WEEK', $synthSubs, true)
    && in_array('NATIONS FOR PEACE', $synthSubs, true)
    && in_array('SETTING THE STAGE', $synthSubs, true);
echo ($synthOk ? 'PASS' : 'FAIL') . " subscribe filtered, real sections kept\n\n";

echo "=== Live scrape probe ===\nURL: {$url}\n\n";

try {
    $article = $scraper->scrape($url);
} catch (Throwable $e) {
    echo "SCRAPE_ERROR: " . $e->getMessage() . "\n";
    exit($synthOk ? 0 : 1);
}

$subs = $article->getSubheadings();
$content = $article->getContent();

echo 'title: ' . $article->getTitle() . "\n";
echo 'content_len: ' . mb_strlen($content) . "\n";
echo 'subheading_count: ' . count($subs) . "\n\n";

echo "--- subheadings ---\n";
foreach ($subs as $i => $h) {
    $flag = (stripos($h, 'SUBSCRIBE') !== false) ? ' [SUBSCRIBE!]' : '';
    echo ($i + 1) . '. ' . $h . $flag . "\n";
}

$hasSubscribeHeading = false;
foreach ($subs as $h) {
    if (stripos($h, 'SUBSCRIBE') !== false) {
        $hasSubscribeHeading = true;
        break;
    }
}

echo "\n--- content signals ---\n";
echo 'SUBSCRIBE in content: ' . (stripos($content, 'SUBSCRIBE') !== false ? 'yes' : 'no') . "\n";
echo 'NATIONS FOR PEACE in content: ' . (stripos($content, 'NATIONS FOR PEACE') !== false ? 'yes' : 'no') . "\n";
echo 'SETTING THE STAGE in content: ' . (stripos($content, 'SETTING THE STAGE') !== false ? 'yes' : 'no') . "\n";

echo "\n--- verdict ---\n";
if ($hasSubscribeHeading) {
    echo "FAIL: SUBSCRIBE still collected as subheading\n";
    exit(1);
}
echo "PASS: no SUBSCRIBE in live subheadings\n";
if (!$synthOk) {
    exit(1);
}

// Pasted fulltext baseline (no HTML sidebar)
$pasted = $root . '/docs/_verify_un_pasted.txt';
if (is_file($pasted)) {
    $pt = file_get_contents($pasted);
    echo "\n--- pasted fulltext baseline (admin paste path) ---\n";
    echo 'SUBSCRIBE in pasted: ' . (stripos((string) $pt, 'SUBSCRIBE') !== false ? 'yes' : 'no') . "\n";
    echo 'sections: NATIONS FOR PEACE=' . (stripos((string) $pt, 'NATIONS FOR PEACE') !== false ? 'yes' : 'no');
    echo ', SETTING THE STAGE=' . (stripos((string) $pt, 'SETTING THE STAGE') !== false ? 'yes' : 'no') . "\n";
}
