<?php
/**
 * 회귀 가드 0.1: 현행(A) 파이프라인 기준선 스냅샷 수집
 *
 * Usage:
 *   php tools/fa_regression_baseline_snapshot.php
 *   php tools/fa_regression_baseline_snapshot.php --url="https://..."
 *   php tools/fa_regression_baseline_snapshot.php --skip-thumbnail
 *
 * Output: docs/regression_baseline_snapshot.json
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__) . '/';

function loadEnvFile(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env'] as $envFile) {
    if (loadEnvFile($envFile)) {
        break;
    }
}

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Pipeline\AgentPipeline;

$defaultUrls = [
    // FA (3) — test-scrape / thumbnail-preview에서 사용된 실 URL
    'https://www.foreignaffairs.com/united-states/real-risks-saudi-uae-feud',
    'https://www.foreignaffairs.com/united-states/perils-militarizing-law-enforcement',
    'https://www.foreignaffairs.com/united-states/america-needs-alliance-audit',
    // 비FA (2)
    'https://www.economist.com/finance-and-economics/2024/03/27/how-india-could-become-an-asian-tiger',
    'https://www.ft.com/content/fefc0f14-5b7b-4eca-aa29-6156e3c4b72e',
];

$urls = $defaultUrls;
$skipThumbnail = in_array('--skip-thumbnail', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--url=')) {
        $urls = [substr($arg, 6)];
    }
}

$googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php')
    ? require $projectRoot . 'config/google_tts.php'
    : [];

function extractSnapshotFields(array $analysis, ?array $article, array $pipelineResults, bool $skipThumbnail): array
{
    $whyImportant = null;
    if (isset($analysis['critical_analysis']['why_important'])) {
        $whyImportant = $analysis['critical_analysis']['why_important'];
    } elseif (isset($analysis['geopolitical_implication'])) {
        $whyImportant = $analysis['geopolitical_implication'];
    }

    $thumbnailUrl = null;
    if (!$skipThumbnail) {
        $thumb = $pipelineResults['ThumbnailAgent'] ?? null;
        if ($thumb && method_exists($thumb, 'isSuccess') && $thumb->isSuccess()) {
            $data = $thumb->getData();
            if (is_array($data)) {
                $thumbnailUrl = $data['image_url'] ?? $data['thumbnail_url'] ?? null;
            }
        }
    }

    return [
        'news_title' => $analysis['news_title'] ?? null,
        'original_title' => $analysis['original_title'] ?? null,
        'content_summary' => $analysis['content_summary'] ?? null,
        'narration' => $analysis['narration'] ?? null,
        'why_important' => $whyImportant,
        'key_points' => $analysis['key_points'] ?? [],
        'section_analysis' => $analysis['section_analysis'] ?? [],
        'introduction_summary' => $analysis['introduction_summary'] ?? null,
        'geopolitical_implication' => $analysis['geopolitical_implication'] ?? null,
        'thumbnail_url' => $thumbnailUrl,
        'article_title' => is_array($article) ? ($article['title'] ?? null) : null,
        'article_subtitle' => is_array($article) ? ($article['description'] ?? null) : null,
        'narration_length' => isset($analysis['narration']) ? mb_strlen((string) $analysis['narration']) : 0,
        'content_summary_length' => isset($analysis['content_summary']) ? mb_strlen((string) $analysis['content_summary']) : 0,
    ];
}

function runBaselineForUrl(string $url, string $projectRoot, array $googleTtsConfig, bool $skipThumbnail): array
{
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    $isFA = str_contains(strtolower((string) $host), 'foreignaffairs.com');

    $scraperConfig = ['timeout' => 60];
    $agentsPath = $projectRoot . 'config/agents.php';
    if (is_file($agentsPath)) {
        $agents = require $agentsPath;
        $scraperConfig = array_merge($agents['scraper'] ?? [], $scraperConfig);
        if (PHP_OS_FAMILY === 'Windows' && getenv('SCRAPER_VERIFY_SSL') !== 'true') {
            $scraperConfig['verify_ssl'] = false;
        }
    }

    $pipelineConfig = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'openai' => [],
        'scraper' => $scraperConfig,
        'enable_interpret' => false,
        'enable_learning' => false,
        'google_tts' => $googleTtsConfig,
        'analysis' => [
            'enable_tts' => false,
            'model' => 'gpt-5.4',
            'temperature' => 0.35,
            'timeout' => 180,
            'max_tokens' => 8000,
            'admin_pure_prompt_mode' => true,
        ],
        'narration' => [
            'model' => 'gpt-5.4',
            'timeout' => 180,
            'max_tokens' => 4096,
            'temperature' => 0.5,
        ],
        'editing' => [
            'model' => 'gpt-5.4',
            'timeout' => 120,
            'max_tokens' => 4096,
            'temperature' => 0.3,
        ],
        'stop_on_failure' => true,
    ];

    $pipeline = new AgentPipeline($pipelineConfig);
    if ($skipThumbnail) {
        $pipeline->setupDefaultPipeline();
        // ThumbnailAgent 제거 — 분석 회귀 기준선에 집중
        $reflection = new ReflectionClass($pipeline);
        $prop = $reflection->getProperty('agents');
        $prop->setAccessible(true);
        $agents = $prop->getValue($pipeline);
        $agents = array_values(array_filter($agents, fn($a) => $a->getName() !== 'ThumbnailAgent'));
        $prop->setValue($pipeline, $agents);
    } else {
        $pipeline->setupDefaultPipeline();
    }

    $start = microtime(true);
    $result = $pipeline->run($url);
    $durationMs = round((microtime(true) - $start) * 1000, 2);

    if (!$result->isSuccess()) {
        return [
            'url' => $url,
            'host' => $host,
            'is_foreign_affairs' => $isFA,
            'success' => false,
            'error' => $result->getError(),
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($result->getResults()),
        ];
    }

    $analysis = $result->getFinalAnalysis() ?? [];
    $articleData = $result->context?->getArticleData();
    $article = $articleData ? $articleData->toArray() : null;

    return [
        'url' => $url,
        'host' => $host,
        'is_foreign_affairs' => $isFA,
        'success' => true,
        'track' => 'A_baseline',
        'duration_ms' => $durationMs,
        'agents_executed' => array_keys($result->getResults()),
        'mock_mode' => $pipeline->isMockMode(),
        'fields' => extractSnapshotFields($analysis, $article, $result->getResults(), $skipThumbnail),
    ];
}

echo "=== FA Regression Baseline Snapshot ===\n";
echo "skip_thumbnail=" . ($skipThumbnail ? 'true' : 'false') . "\n";

if (PHP_OS_FAMILY === 'Windows' && getenv('PHP_CURL_SSL_NO_VERIFY') !== 'true') {
    putenv('PHP_CURL_SSL_NO_VERIFY=1');
    $_ENV['PHP_CURL_SSL_NO_VERIFY'] = '1';
    echo "note: PHP_CURL_SSL_NO_VERIFY=1 (local Windows SSL)\n";
}
echo "\n";

$snapshot = [
    'generated_at' => date('c'),
    'purpose' => 'Pre-change baseline for track=A regression guard (0.1)',
    'note' => 'CHUNK1 intentionally changes bullet prefix and register; post-CHUNK1 compare structure/fields not byte-identical text.',
    'skip_thumbnail' => $skipThumbnail,
    'results' => [],
];

foreach ($urls as $url) {
    echo "Running: {$url}\n";
    try {
        $row = runBaselineForUrl($url, $projectRoot, $googleTtsConfig, $skipThumbnail);
        $snapshot['results'][] = $row;
        echo $row['success'] ? "  OK ({$row['duration_ms']} ms)\n" : "  FAIL: {$row['error']}\n";
    } catch (Throwable $e) {
        $snapshot['results'][] = [
            'url' => $url,
            'success' => false,
            'error' => $e->getMessage(),
        ];
        echo "  EXCEPTION: {$e->getMessage()}\n";
    }
}

$outPath = $projectRoot . 'docs/regression_baseline_snapshot.json';
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}
file_put_contents($outPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "\nSaved: {$outPath}\n";
$ok = count(array_filter($snapshot['results'], fn($r) => !empty($r['success'])));
echo "Success: {$ok}/" . count($snapshot['results']) . "\n";
