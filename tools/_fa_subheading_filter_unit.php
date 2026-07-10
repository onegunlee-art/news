<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/agents/autoload.php';

use Agents\Services\WebScraperService;

$ref = new ReflectionClass(WebScraperService::class);
$scraper = $ref->newInstanceWithoutConstructor();
$method = $ref->getMethod('isLikelySubheading');
$method->setAccessible(true);

$reject = [
    'SUBSCRIBE TO FOREIGN AFFAIRS THIS WEEK',
    'Subscribe to Foreign Affairs This Week',
    'subscribe to foreign affairs',
    'SIGN UP FOR OUR NEWSLETTER',
    'Sign Up',
    'NEWSLETTER',
    'MOST READ BY SUBSCRIBERS',
    'RECOMMENDED FOR YOU',
    'GET THE LATEST FROM FOREIGN AFFAIRS',
    'Advertisement',
    'ALREADY A SUBSCRIBER',
    'GIFT SUBSCRIPTIONS',
    'DELIVERED FREE TO YOUR INBOX',
    'ENTER YOUR EMAIL',
    'OUR EDITORS\' TOP PICKS',
    'KEEP READING WITH ONE OF THESE OPTIONS',
    'READ MORE FROM THE ECONOMIST',
    'EXPLORE MORE',
    'VIEW ALL 12 STORIES',
    'MANAGE ACCOUNT',
];

$accept = [
    'NATIONS FOR PEACE',
    'SETTING THE STAGE',
    'CONCLUSION',
    'A NEW WORLD ORDER',
    'THE END OF AN ERA',
    'INDIA ON THE RISE',
    'PAGE NOT FOUND',
    'NAVIGATION AND SUBSCRIPTION UI',
    'THE DONROE DOCTRINE',
    'NUCLEAR MUST BE PART OF SOLUTION',
];

$results = [];

foreach ($reject as $text) {
    $key = 'reject_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($text));
    $results[$key] = $method->invoke($scraper, $text) === false;
}

foreach ($accept as $text) {
    $key = 'accept_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($text));
    $results[$key] = $method->invoke($scraper, $text) === true;
}

// Synthetic FA HTML: subscribe bold + real section bold
$parseHtml = $ref->getMethod('parseHtml');
$parseHtml->setAccessible(true);
$html = <<<'HTML'
<!DOCTYPE html><html><body>
<article>
<p>Intro paragraph.</p>
<strong>SUBSCRIBE TO FOREIGN AFFAIRS THIS WEEK</strong>
<p>Newsletter CTA body.</p>
<strong>NATIONS FOR PEACE</strong>
<p>Peace section body.</p>
<strong>SETTING THE STAGE</strong>
<p>Stage section body.</p>
</article>
</body></html>
HTML;
$article = $parseHtml->invoke($scraper, 'https://www.foreignaffairs.com/test/article', $html);
$subs = $article->getSubheadings();
$results['html_excludes_subscribe'] = !in_array('SUBSCRIBE TO FOREIGN AFFAIRS THIS WEEK', $subs, true);
$results['html_keeps_nations_for_peace'] = in_array('NATIONS FOR PEACE', $subs, true);
$results['html_keeps_setting_the_stage'] = in_array('SETTING THE STAGE', $subs, true);
$results['html_subheading_count'] = count($subs) === 2;

$failed = [];
foreach ($results as $name => $ok) {
    if ($ok !== true) {
        $failed[] = $name;
    }
}

echo "=== subheading filter unit ===\n";
foreach ($results as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " {$name}\n";
}

if ($failed !== []) {
    echo "\nFAILED: " . implode(', ', $failed) . "\n";
    exit(1);
}

echo "\nALL PASS (" . count($results) . " checks)\n";
