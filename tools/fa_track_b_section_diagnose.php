<?php
/**
 * Track B 섹션 빔 진단: GPT raw JSON vs 조립 입력
 *
 * Usage: php tools/fa_track_b_section_diagnose.php [url]
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

$url = $argv[1] ?? 'https://www.foreignaffairs.com/middle-east/end-hamas';

$scraperConfig = ['timeout' => 60, 'verify_ssl' => PHP_OS_FAMILY !== 'Windows'];
if (is_file($projectRoot . 'config/agents.php')) {
    $agents = require $projectRoot . 'config/agents.php';
    $scraperConfig = array_merge($agents['scraper'] ?? [], $scraperConfig);
}

$scraper = new \Agents\Services\WebScraperService($scraperConfig);
$article = $scraper->scrape($url);

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

echo "URL: {$url}\n";
echo "title: " . $article->getTitle() . "\n";
echo "content_len: " . mb_strlen($article->getContent()) . "\n";
echo "subheadings: " . count($article->getSubheadings()) . "\n\n";

$t0 = microtime(true);
$result = $pipeline->run($url);
$ms = round((microtime(true) - $t0) * 1000, 2);

if (!$result->isSuccess()) {
    fwrite(STDERR, "Pipeline failed: " . $result->getError() . "\n");
    exit(1);
}

$analysis = $result->getFinalAnalysis() ?? [];
$sections = $analysis['section_analysis'] ?? [];
$cs = (string) ($analysis['content_summary'] ?? '');

echo "duration_ms: {$ms}\n";
echo "section_analysis count: " . count($sections) . "\n\n";

foreach ($sections as $i => $sec) {
    $keys = array_keys(is_array($sec) ? $sec : []);
    $summary = trim((string) ($sec['summary'] ?? ''));
    $content = trim((string) ($sec['section_content'] ?? ''));
    echo "=== section[{$i}] keys: " . implode(', ', $keys) . " ===\n";
    echo "  section_title: " . ($sec['section_title'] ?? '') . "\n";
    echo "  summary len: " . mb_strlen($summary) . "\n";
    echo "  section_content len: " . mb_strlen($content) . "\n";
    if ($summary !== '') {
        echo "  summary head: " . mb_substr($summary, 0, 120) . "\n";
    }
    if ($content !== '') {
        echo "  section_content head: " . mb_substr($content, 0, 120) . "\n";
    }
    echo "\n";
}

echo "geopolitical_implication in final: " . (empty($analysis['geopolitical_implication']) ? 'EMPTY' : 'set') . "\n";
echo "why_important in critical_analysis: " . (empty($analysis['critical_analysis']['why_important']) ? 'EMPTY' : 'set') . "\n\n";

// content_summary 섹션 본문(· 줄) 개수
$bulletLines = preg_match_all('/^· /m', $cs);
echo "content_summary bullet lines (·): {$bulletLines}\n";
echo "content_summary length: " . mb_strlen($cs) . "\n\n";

// 최신 gpt_analysis 로그에서 raw preview
$logDir = $projectRoot . 'storage/logs';
$logs = glob($logDir . '/gpt_analysis_*.json') ?: [];
usort($logs, fn($a, $b) => filemtime($b) <=> filemtime($a));
if ($logs !== []) {
    $latest = json_decode((string) file_get_contents($logs[0]), true);
    echo "Latest analysis log: " . basename($logs[0]) . "\n";
    echo "  parsed section_analysis_count: " . ($latest['section_analysis_count'] ?? '?') . "\n";
    echo "  parsed_keys: " . implode(', ', $latest['parsed_keys'] ?? []) . "\n";
    $preview = (string) ($latest['raw_response_preview'] ?? '');
    if (preg_match('/"section_analysis"\s*:\s*\[[\s\S]{0,2500}/u', $preview, $m)) {
        echo "\nraw section_analysis excerpt:\n" . $m[0] . "...\n";
    }
}

$outPath = $projectRoot . 'docs/fa_track_b_section_diagnose.json';
file_put_contents($outPath, json_encode([
    'url' => $url,
    'duration_ms' => $ms,
    'section_analysis' => $sections,
    'content_summary' => $cs,
    'geopolitical_implication' => $analysis['geopolitical_implication'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nSaved: {$outPath}\n";
