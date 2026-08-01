<?php
declare(strict_types=1);

/**
 * Discovery 자동 생성 (매일 05:00 KST)
 * - 멀티 에이전트 파이프라인 실행
 * - 검증 통과분 draft로 저장
 * - 05:30 재시도 (실패 시)
 * 
 * Usage: php cron/discovery_generate.php [YYYY-MM-DD]
 */

require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);

// Kill switch check
$cronEnabled = $_ENV['ENABLE_DISCOVERY_CRON'] ?? getenv('ENABLE_DISCOVERY_CRON');
if (is_string($cronEnabled) && strtolower(trim($cronEnabled)) === 'false') {
    echo "SKIP: ENABLE_DISCOVERY_CRON=false\n";
    exit(0);
}

discoveryEnsureEnabled($root);

$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);

$config = discoveryConfig($root);
$agentConfig = discoveryAgentsConfig($root);
$apiKey = discoveryOpenAiApiKey();

$repo = new Discovery\DiscoveryRepository($pdo);
$verifier = new Discovery\SourceVerifier();

$curatorLlm = Discovery\Agents\LLMClient::forAgent('curator', $agentConfig, $apiKey);
$brieferLlm = Discovery\Agents\LLMClient::forAgent('briefer', $agentConfig, $apiKey);

$curator = new Discovery\Agents\CuratorAgent($curatorLlm);
$extractor = new Discovery\Agents\ExtractorAgent();
$briefer = new Discovery\Agents\BrieferAgent($brieferLlm);
$pipeline = new Discovery\Agents\Pipeline($repo, $curator, $extractor, $briefer, $verifier, $config, $agentConfig);

$date = $argv[1] ?? discoveryTodayKst();

echo "=== Discovery Cron Generate date={$date} ===\n";

$started = microtime(true);

try {
    $result = $pipeline->run($date, true);
    $elapsed = round(microtime(true) - $started, 1);

    $summary = [
        'success' => true,
        'date' => $date,
        'edition_id' => $result->edition['id'] ?? null,
        'verified_count' => count($result->verifiedChanges),
        'discarded_count' => count($result->discardedChanges),
        'discarded' => array_slice($result->discardedChanges, 0, 20),
        'extraction_full' => $result->meta['extraction_full'] ?? 0,
        'extraction_summary_only' => $result->meta['extraction_summary_only'] ?? 0,
        'stage_logs' => $result->meta['stage_logs'] ?? [],
        'elapsed_sec' => $elapsed,
    ];

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    file_put_contents(
        $root . 'storage/discovery_last_generate.json',
        json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    exit(0);
} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $started, 1);

    $summary = [
        'success' => false,
        'date' => $date,
        'error' => $e->getMessage(),
        'elapsed_sec' => $elapsed,
    ];

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");

    file_put_contents(
        $root . 'storage/discovery_last_generate.json',
        json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    exit(1);
}
