<?php
declare(strict_types=1);

/**
 * Multi-agent discovery pipeline run (Phase 1: Collector → Extractor → Briefer → Gates).
 * Usage: php tools/discovery_multi_agent_run.php [YYYY-MM-DD] [--force]
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
discoveryEnsureEnabled($root);

$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);

$config = discoveryConfig($root);
$agentConfig = discoveryAgentsConfig($root);
$apiKey = discoveryOpenAiApiKey();

$repo = new Discovery\DiscoveryRepository($pdo);
$verifier = new Discovery\SourceVerifier();

$brieferLlm = Discovery\Agents\LLMClient::forAgent('briefer', $agentConfig, $apiKey);
$extractor = new Discovery\Agents\ExtractorAgent();
$briefer = new Discovery\Agents\BrieferAgent($brieferLlm);
$pipeline = new Discovery\Agents\Pipeline($repo, $extractor, $briefer, $verifier, $config, $agentConfig);

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$dateArgs = array_values(array_filter($args, static fn($a) => $a !== '--force'));
$date = $dateArgs[0] ?? discoveryTodayKst();

echo "=== Discovery Multi-Agent Pipeline date={$date} ===\n";
echo 'OPENAI configured=' . ($brieferLlm->isConfigured() ? 'yes' : 'no') . "\n";
echo 'briefer_model=' . $brieferLlm->getModel() . "\n";

if (!$force && $repo->hasPublishedRealEditionForDate($date)) {
    $edition = $repo->findPublishedEditionByDate($date, false) ?? $repo->findEditionByDate($date);
    echo "SKIP: published real edition already exists for {$date}\n";
    if ($edition) {
        echo json_encode($edition, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    echo "Use --force to regenerate.\n";
    exit(0);
}

$started = microtime(true);
$result = $pipeline->run($date, $force);
$elapsed = round(microtime(true) - $started, 1);

$summary = $result->toArray();
$summary['elapsed_sec'] = $elapsed;
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== Stage Logs ===\n";
foreach ($result->meta['stage_logs'] ?? [] as $log) {
    echo sprintf(
        "  %s: input=%d output=%d discarded=%d\n",
        $log['stage'] ?? '?',
        $log['input'] ?? 0,
        $log['output'] ?? 0,
        $log['discarded'] ?? 0,
    );
}

if (isset($result->verifiedChanges[0])) {
    echo "\n=== Sample briefing (first verified change) ===\n";
    $first = $result->verifiedChanges[0];
    echo json_encode($first['briefing'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo 'source_url=' . (($first['sources'][0]['url'] ?? '?')) . "\n";
}

$reporter = new Discovery\DiscoveryRunReporter(new Discovery\SourceWhitelist(
    $config['source_whitelist'] ?? [],
    $config['source_blocklist'] ?? [],
));
$reporter->printReport($result->verifiedChanges, $result->discardedChanges);

$verified = (int) ($summary['verified_count'] ?? 0);
if ($verified < 1) {
    fwrite(STDERR, "FAIL: verified_count={$verified} (need >= 1)\n");
    exit(1);
}

echo "\n=== Done verified={$verified} elapsed={$elapsed}s ===\n";
