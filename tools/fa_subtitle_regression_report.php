<?php
/**
 * 부제 분리 패치 회귀 보고: track A/B + baseline 대조
 *
 * Usage: php tools/fa_subtitle_regression_report.php
 * Output: docs/fa_subtitle_regression_report.json
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
require_once $projectRoot . 'tools/fa_regression_baseline_snapshot.php';

$faUrl = 'https://www.foreignaffairs.com/united-states/america-needs-alliance-audit';
$faUrl2 = 'https://www.foreignaffairs.com/united-states/broken-nuclear-umbrella-lind-press';
$googleTts = file_exists($projectRoot . 'config/google_tts.php') ? require $projectRoot . 'config/google_tts.php' : [];

$baselinePath = $projectRoot . 'docs/regression_baseline_snapshot.json';
$baselineJson = json_decode((string) file_get_contents($baselinePath), true);

function findBaseline(?array $json, string $url): ?array
{
    foreach ($json['results'] ?? [] as $row) {
        if (($row['url'] ?? '') === $url && ($row['success'] ?? false)) {
            return $row['fields'] ?? null;
        }
    }
    return null;
}

function sectionTitles(array $fields): array
{
    $out = [];
    foreach ($fields['section_analysis'] ?? [] as $s) {
        $out[] = $s['section_title'] ?? '';
    }
    return $out;
}

function contentSummaryBlocks(string $cs): array
{
    return array_values(array_filter(explode("\n\n", $cs), fn($b) => trim($b) !== ''));
}

function runTrack(string $url, string $track, string $projectRoot, array $googleTts): array
{
    $scraperConfig = ['timeout' => 60];
    if (is_file($projectRoot . 'config/agents.php')) {
        $agents = require $projectRoot . 'config/agents.php';
        $scraperConfig = array_merge($agents['scraper'] ?? [], $scraperConfig);
        if (PHP_OS_FAMILY === 'Windows' && getenv('SCRAPER_VERIFY_SSL') !== 'true') {
            $scraperConfig['verify_ssl'] = false;
        }
    }
    $config = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'scraper' => $scraperConfig,
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
    $pipeline = new \Agents\Pipeline\AgentPipeline($config);
    $pipeline->setupDefaultPipeline();
    $ref = new ReflectionClass($pipeline);
    $prop = $ref->getProperty('agents');
    $prop->setAccessible(true);
    $agents = $prop->getValue($pipeline);
    $agents = array_values(array_filter($agents, fn($a) => $a->getName() !== 'ThumbnailAgent'));
    $prop->setValue($pipeline, $agents);

    $t0 = microtime(true);
    $result = $pipeline->run($url);
    $ms = round((microtime(true) - $t0) * 1000, 2);
    if (!$result->isSuccess()) {
        return ['success' => false, 'track' => $track, 'url' => $url, 'error' => $result->getError(), 'duration_ms' => $ms];
    }
    $analysis = $result->getFinalAnalysis() ?? [];
    return [
        'success' => true,
        'track' => $track,
        'url' => $url,
        'duration_ms' => $ms,
        'agents_executed' => array_keys($result->results),
        'fields' => extractSnapshotFields($analysis, null, ['results' => $result->results], true),
    ];
}

echo "=== FA subtitle regression (A alliance-audit) ===\n";
$aCurrent = runTrack($faUrl, 'A', $projectRoot, $googleTts);
$baseA = findBaseline($baselineJson, $faUrl);

echo "=== FA subtitle regression (B broken-nuclear-umbrella) ===\n";
$bCurrent = runTrack($faUrl2, 'B', $projectRoot, $googleTts);

$report = [
    'generated_at' => date('c'),
    'patch' => 'subtitle_ko + original_subtitle separation',
    'checks' => [],
];

if ($aCurrent['success'] ?? false) {
    $cur = $aCurrent['fields'];
    $cs = (string) ($cur['content_summary'] ?? '');
    $blocks = contentSummaryBlocks($cs);
    $titleBlock = $blocks[0] ?? '';
    $secondBlock = $blocks[1] ?? '';
    $introBlock = $blocks[2] ?? ($blocks[1] ?? '');

    $hasSubtitleLine = count($blocks) >= 2
        && !str_starts_with($secondBlock, '·')
        && (str_contains($secondBlock, '(') || mb_strlen($secondBlock) < 120);
    $introMergedSubtitle = str_starts_with((string) ($cur['introduction_summary'] ?? ''), '부제')
        || str_contains((string) ($cur['introduction_summary'] ?? ''), "부제는 '");
    $baseTitles = sectionTitles($baseA ?? []);
    $curTitles = sectionTitles($cur);
    $sectionsMatch = $baseTitles === $curTitles;

    $baseNarr = mb_strlen((string) ($baseA['narration'] ?? ''));
    $curNarr = mb_strlen((string) ($cur['narration'] ?? ''));
    $narrRatio = $baseNarr > 0 ? round($curNarr / $baseNarr, 3) : null;

    $report['track_a_alliance_audit'] = [
        'duration_ms' => $aCurrent['duration_ms'],
        'subtitle_ko' => $cur['subtitle_ko'] ?? null,
        'original_subtitle' => $cur['original_subtitle'] ?? null,
        'introduction_summary_head' => mb_substr((string) ($cur['introduction_summary'] ?? ''), 0, 200),
        'content_summary_blocks_head' => array_slice($blocks, 0, 3),
        'section_titles' => $curTitles,
        'narration_length' => $curNarr,
    ];
    $report['checks'][] = [
        'name' => 'A_subtitle_line_in_content_summary',
        'pass' => $hasSubtitleLine && !empty($cur['subtitle_ko']) && !empty($cur['original_subtitle']),
        'detail' => 'block[1] should be 한글 (영문), not bullet intro',
    ];
    $report['checks'][] = [
        'name' => 'A_intro_not_merged_subtitle',
        'pass' => !$introMergedSubtitle,
        'detail' => 'introduction_summary should not start with 부제는',
    ];
    $report['checks'][] = [
        'name' => 'A_section_titles_unchanged',
        'pass' => $sectionsMatch,
        'detail' => 'section_title EN list vs baseline',
        'baseline' => $baseTitles,
        'current' => $curTitles,
    ];
    $report['checks'][] = [
        'name' => 'A_narration_length_stable',
        'pass' => $narrRatio === null || ($narrRatio >= 0.7 && $narrRatio <= 1.3),
        'detail' => "ratio={$narrRatio} (baseline {$baseNarr} vs current {$curNarr})",
    ];
} else {
    $report['track_a_error'] = $aCurrent['error'] ?? 'failed';
}

if ($bCurrent['success'] ?? false) {
    $cur = $bCurrent['fields'];
    $blocks = contentSummaryBlocks((string) ($cur['content_summary'] ?? ''));
    $report['track_b_nuclear_umbrella'] = [
        'duration_ms' => $bCurrent['duration_ms'],
        'subtitle_ko' => $cur['subtitle_ko'] ?? null,
        'original_subtitle' => $cur['original_subtitle'] ?? null,
        'content_summary_blocks_head' => array_slice($blocks, 0, 3),
    ];
    $report['checks'][] = [
        'name' => 'B_subtitle_bilingual',
        'pass' => !empty($cur['subtitle_ko']) && !empty($cur['original_subtitle']),
        'detail' => 'B track should expose subtitle_ko + original_subtitle',
    ];
    $second = $blocks[1] ?? '';
    $report['checks'][] = [
        'name' => 'B_subtitle_line_in_content_summary',
        'pass' => str_contains($second, '(') && str_contains(strtolower($second), 'extended'),
        'detail' => $second,
    ];
} else {
    $report['track_b_error'] = $bCurrent['error'] ?? 'failed';
}

$allPass = true;
foreach ($report['checks'] as $c) {
    $status = ($c['pass'] ?? false) ? 'PASS' : 'FAIL';
    if ($status === 'FAIL') {
        $allPass = false;
    }
    echo "[{$status}] {$c['name']}: {$c['detail']}\n";
}
$report['all_pass'] = $allPass;

$outPath = $projectRoot . 'docs/fa_subtitle_regression_report.json';
file_put_contents($outPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nSaved: {$outPath}\n";
echo 'all_pass: ' . ($allPass ? 'true' : 'false') . "\n";
exit($allPass ? 0 : 1);
