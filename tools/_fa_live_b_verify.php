<?php
/**
 * FA Track B 실기사 검증 — 스크래핑 + 문체/불릿 실출력
 *
 * Usage:
 *   php tools/_fa_live_b_verify.php
 *   php tools/_fa_live_b_verify.php "https://www.foreignaffairs.com/americas/trump-remaking-latin-america"
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__) . '/';
foreach ([$projectRoot . 'env.txt', $projectRoot . '.env'] as $envFile) {
    if (!is_file($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || ($line[0] ?? '') === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name !== '') {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}
if (PHP_OS_FAMILY === 'Windows' && getenv('PHP_CURL_SSL_NO_VERIFY') !== '1') {
    putenv('PHP_CURL_SSL_NO_VERIFY=1');
}

require_once $projectRoot . 'src/agents/autoload.php';

$url = $argv[1] ?? 'https://www.foreignaffairs.com/americas/trump-remaking-latin-america';
$pastedFile = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--pasted=')) {
        $pastedFile = substr($arg, 9);
    }
}

$scraperConfig = ['timeout' => 60, 'verify_ssl' => PHP_OS_FAMILY !== 'Windows'];
if (is_file($projectRoot . 'config/agents.php')) {
    $agents = require $projectRoot . 'config/agents.php';
    $scraperConfig = array_merge($agents['scraper'] ?? [], $scraperConfig);
    if (PHP_OS_FAMILY === 'Windows' && getenv('SCRAPER_VERIFY_SSL') !== 'true') {
        $scraperConfig['verify_ssl'] = false;
    }
}

$scraper = new \Agents\Services\WebScraperService($scraperConfig);

if ($pastedFile !== null && is_file($pastedFile)) {
    $raw = (string) file_get_contents($pastedFile);
    $lines = preg_split('/\r\n|\r|\n/', $raw, 4) ?: [];
    $title = trim($lines[0] ?? 'FA Article');
    $subtitle = trim($lines[1] ?? '');
    $author = trim($lines[2] ?? '');
    $content = trim(implode("\n", array_slice($lines, 3)));
    $subheadings = [];
    foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '' && $line === mb_strtoupper($line) && preg_match('/^[A-Z][A-Z\s\']+$/', $line) && mb_strlen($line) <= 60) {
            $subheadings[] = $line;
        }
    }
    $article = new \Agents\Models\ArticleData(
        url: $url,
        title: $title,
        content: $content,
        description: $subtitle,
        source: 'Foreign Affairs',
        language: 'en',
        subheadings: $subheadings,
        metadata: ['pasted_content' => true, 'content_length' => mb_strlen($content)]
    );
    echo "=== PASTED CONTENT ===\n";
} else {
    $article = $scraper->scrape($url);
}

echo "=== SCRAPE ===\n";
echo "url: {$url}\n";
echo 'title: ' . $article->getTitle() . "\n";
echo 'content_len: ' . mb_strlen($article->getContent()) . "\n";
echo 'subheadings: ' . count($article->getSubheadings()) . "\n";
if ($article->getSubheadings() !== []) {
    echo 'subheading_list: ' . implode(' | ', array_slice($article->getSubheadings(), 0, 8)) . "\n";
}
$isNotFound = stripos($article->getTitle(), 'not found') !== false
    || mb_strlen(trim($article->getContent())) < 2000;
echo 'scrape_ok: ' . ($isNotFound ? 'NO (Not Found or too short)' : 'YES') . "\n\n";
if ($isNotFound) {
    fwrite(STDERR, "Scrape failed — aborting pipeline.\n");
    exit(1);
}

$config = [
    'project_root' => rtrim($projectRoot, '/\\'),
    'scraper' => $scraperConfig,
    'prompt_track' => 'B',
    'skip_tts' => true,
    'enable_interpret' => false,
    'enable_learning' => false,
    'analysis' => [
        'enable_tts' => false,
        'model' => 'gpt-5.4',
        'temperature' => 0.35,
        'timeout' => 180,
        'max_tokens' => 8000,
        'admin_pure_prompt_mode' => true,
    ],
    'narration' => ['model' => 'gpt-5.4', 'timeout' => 180, 'max_tokens' => 4096, 'temperature' => 0.5],
    'editing' => ['model' => 'gpt-5.4', 'timeout' => 120, 'max_tokens' => 4096, 'temperature' => 0.3],
    'stop_on_failure' => true,
];

$pipeline = new \Agents\Pipeline\AgentPipeline($config);
$pipeline->setupDefaultPipeline();
$ref = new ReflectionClass($pipeline);
$prop = $ref->getProperty('agents');
$prop->setAccessible(true);
$agentsList = $prop->getValue($pipeline);
$agentsList = array_values(array_filter($agentsList, fn($a) => $a->getName() !== 'ThumbnailAgent'));
$prop->setValue($pipeline, $agentsList);

echo "=== TRACK B PIPELINE ===\n";
$t0 = microtime(true);
$result = $pastedFile !== null
    ? $pipeline->run($url, $article)
    : $pipeline->run($url);
$ms = round((microtime(true) - $t0) * 1000, 2);
if (!$result->isSuccess()) {
    fwrite(STDERR, 'Pipeline failed: ' . ($result->getError() ?? 'unknown') . "\n");
    exit(1);
}

$analysis = $result->getFinalAnalysis() ?? [];
$cs = (string) ($analysis['content_summary'] ?? '');
$sections = $analysis['section_analysis'] ?? [];

echo "duration_ms: {$ms}\n";
echo 'news_title: ' . ($analysis['news_title'] ?? '') . "\n";
echo 'section_count: ' . count($sections) . "\n";
echo 'content_summary_len: ' . mb_strlen($cs) . "\n";
$bulletLines = preg_match_all('/^· /m', $cs);
$indentedBullets = preg_match_all('/^[ \t]+·/m', $cs);
echo "bullet_lines: {$bulletLines}, indented_bullets: {$indentedBullets}\n\n";

echo "=== INTRO (introduction_summary) ===\n";
echo ($analysis['introduction_summary'] ?? '') . "\n\n";

echo "=== SECTIONS (summary + key_insight) ===\n";
foreach ($sections as $i => $sec) {
    $n = $i + 1;
    echo "--- {$n}. " . ($sec['section_title_ko'] ?? '') . ' (' . ($sec['section_title'] ?? '') . ") ---\n";
    echo '[summary] ' . ($sec['summary'] ?? '') . "\n";
    echo '[key_insight] ' . ($sec['key_insight'] ?? '') . "\n\n";
}

echo "=== GEOPOLITICAL (왜 중요한가) ===\n";
echo ($analysis['geopolitical_implication'] ?? '') . "\n\n";

echo "=== CONTENT_SUMMARY EXCERPT (first 3 section blocks) ===\n";
$blocks = array_values(array_filter(explode("\n\n", $cs), fn($b) => trim($b) !== ''));
foreach (array_slice($blocks, 0, 6) as $block) {
    echo $block . "\n\n";
}

echo "=== CONTENT_SUMMARY FULL ===\n";
echo $cs . "\n\n";

$outPath = $projectRoot . 'docs/fa_track_b_live_verify.json';
file_put_contents($outPath, json_encode([
    'generated_at' => date('c'),
    'url' => $url,
    'duration_ms' => $ms,
    'scrape' => [
        'title' => $article->getTitle(),
        'content_len' => mb_strlen($article->getContent()),
        'subheadings' => $article->getSubheadings(),
    ],
    'analysis' => $analysis,
    'bullet_lines' => $bulletLines,
    'indented_bullets' => $indentedBullets,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Saved: {$outPath}\n";
