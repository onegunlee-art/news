<?php
/**
 * Admin analyze_content(붙여넣기) 경로 검증 — AgentPipeline + ArticleData (ai-analyze analyzeContent 동일)
 *
 * Usage:
 *   php tools/fa_track_b_pasted_verify.php
 *   php tools/fa_track_b_pasted_verify.php --track=A --hamas
 *   php tools/fa_track_b_pasted_verify.php --economist
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

use Agents\Models\ArticleData;
use Agents\Pipeline\AgentPipeline;

$track = 'B';
$mode = 'hamas_pasted';
foreach ($argv as $arg) {
    if ($arg === '--track=A') {
        $track = 'A';
    }
    if ($arg === '--economist') {
        $mode = 'economist_pasted';
    }
}

function assessContentSummary(string $cs): array
{
    $blocks = array_values(array_filter(explode("\n\n", $cs), fn($b) => trim($b) !== ''));
    $sectionHeaders = 0;
    $bullets = 0;
    $hasWhy = str_contains($cs, '왜 중요한가');
    foreach ($blocks as $block) {
        if (preg_match('/^\d+\.\s/u', $block)) {
            $sectionHeaders++;
        }
        $bullets += preg_match_all('/^· /m', $block);
    }
    return [
        'blocks' => count($blocks),
        'section_headers' => $sectionHeaders,
        'bullet_lines' => $bullets,
        'has_why_block' => $hasWhy,
        'head' => array_slice($blocks, 0, 6),
    ];
}

function runPastedPipeline(string $url, string $title, string $content, string $track, string $projectRoot): array
{
    $description = 'The convenient fiction of continued menace.';
    try {
        $scraperConfig = ['timeout' => 60, 'verify_ssl' => PHP_OS_FAMILY !== 'Windows'];
        if (is_file($projectRoot . 'config/agents.php')) {
            $agents = require $projectRoot . 'config/agents.php';
            $scraperConfig = array_merge($agents['scraper'] ?? [], $scraperConfig);
        }
        $scraper = new \Agents\Services\WebScraperService($scraperConfig);
        $scraped = $scraper->scrape($url);
        if ($scraped->getDescription()) {
            $description = $scraped->getDescription();
        }
    } catch (\Throwable $e) {
        // metadata optional for pasted path
    }

    $article = new ArticleData(
        url: $url,
        title: $title,
        content: $content,
        description: $description,
        source: 'Foreign Affairs',
        language: 'en',
        metadata: ['pasted_content' => true, 'content_length' => mb_strlen($content)]
    );

    $googleTts = file_exists($projectRoot . 'config/google_tts.php') ? require $projectRoot . 'config/google_tts.php' : [];
    $config = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'scraper' => ['timeout' => 60],
        'google_tts' => $googleTts,
        'prompt_track' => $track,
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

    $pipeline = new AgentPipeline($config);
    $pipeline->setupDefaultPipeline();
    $ref = new ReflectionClass($pipeline);
    $prop = $ref->getProperty('agents');
    $prop->setAccessible(true);
    $agentsList = $prop->getValue($pipeline);
    $agentsList = array_values(array_filter($agentsList, fn($a) => $a->getName() !== 'ThumbnailAgent'));
    $prop->setValue($pipeline, $agentsList);

    $t0 = microtime(true);
    $result = $pipeline->run($url, $article);
    $ms = round((microtime(true) - $t0) * 1000, 2);
    if (!$result->isSuccess()) {
        return ['success' => false, 'error' => $result->getError(), 'duration_ms' => $ms];
    }
    $analysis = $result->getFinalAnalysis() ?? [];
    return ['success' => true, 'duration_ms' => $ms, 'analysis' => $analysis];
}

if ($mode === 'economist_pasted') {
    $url = 'https://www.economist.com/finance-and-economics/2024/03/27/how-india-could-become-an-asian-tiger';
    $contentFile = $projectRoot . 'docs/_verify_economist_pasted.txt';
    $title = 'How India could become an Asian tiger';
    $track = 'A';
} else {
    $url = 'https://www.foreignaffairs.com/palestinian-territories/end-hamas';
    $contentFile = $projectRoot . 'docs/_verify_hamas_pasted.txt';
    $title = 'The End of Hamas';
}

if (!is_file($contentFile)) {
    fwrite(STDERR, "Missing {$contentFile}\n");
    exit(1);
}
$content = (string) file_get_contents($contentFile);

echo "mode={$mode} track={$track} path=pasted_content content_len=" . mb_strlen($content) . "\n";

$result = runPastedPipeline($url, $title, $content, $track, $projectRoot);
if (!($result['success'] ?? false)) {
    fwrite(STDERR, 'FAILED: ' . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

$analysis = $result['analysis'];
$cs = (string) ($analysis['content_summary'] ?? '');
$assess = assessContentSummary($cs);
$sections = $analysis['section_analysis'] ?? [];

$report = [
    'generated_at' => date('c'),
    'verify_path' => 'admin_analyze_content_equivalent (pasted ArticleData + prompt_track)',
    'mode' => $mode,
    'track' => $track,
    'url' => $url,
    'content_length' => mb_strlen($content),
    'duration_ms' => $result['duration_ms'],
    'section_analysis_count' => count($sections),
    'section_fields_sample' => isset($sections[0]) && is_array($sections[0]) ? array_keys($sections[0]) : [],
    'content_summary_assessment' => $assess,
    'geopolitical_implication_set' => !empty($analysis['geopolitical_implication']),
    'content_summary' => $cs,
];

if ($mode === 'hamas_pasted' && $track === 'B') {
    $report['pass'] = $assess['section_headers'] >= 3 && $assess['bullet_lines'] >= 6 && $assess['has_why_block'];
    $report['pass_criteria'] = 'section_headers>=3, bullet_lines>=6, has_why_block';
} elseif ($mode === 'economist_pasted' && $track === 'A') {
    $report['pass'] = $assess['bullet_lines'] >= 3 && mb_strlen($cs) > 200;
    $report['pass_criteria'] = 'non-FA A: bullets>=3, content_summary not empty';
} else {
    $report['pass'] = $assess['bullet_lines'] >= 1;
}

$out = $projectRoot . 'docs/fa_track_b_pasted_verify.json';
file_put_contents($out, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "duration_ms: {$result['duration_ms']}\n";
echo "sections: " . count($sections) . "\n";
echo "section_headers: {$assess['section_headers']}\n";
echo "bullet_lines: {$assess['bullet_lines']}\n";
echo "has_why_block: " . ($assess['has_why_block'] ? 'yes' : 'no') . "\n";
echo 'pass: ' . (($report['pass'] ?? false) ? 'true' : 'false') . "\n";
echo "Saved: {$out}\n";
exit(($report['pass'] ?? false) ? 0 : 1);
