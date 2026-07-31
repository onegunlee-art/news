<?php
declare(strict_types=1);

/**
 * Phase 1: generate → preview summary → publish (single manual run).
 * Usage: php tools/discovery_phase1_run.php [YYYY-MM-DD] [--force]
 * Skips if a published real edition (is_seed=0) already exists unless --force.
 * Exit 1 if verified_count < 1 or publish fails.
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
discoveryEnsureEnabled($root);

$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);

$config = discoveryConfig($root);
$repo = new Discovery\DiscoveryRepository($pdo);
$llm = new Discovery\DiscoveryLLMClient(['model' => $config['model'] ?? 'gpt-4o']);
$agent = new Discovery\DiscoveryAgent($llm, $config);
$verifier = new Discovery\SourceVerifier();
$pipeline = new Discovery\DiscoveryPipeline($repo, $agent, $verifier, $config);

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$dateArgs = array_values(array_filter($args, static fn($a) => $a !== '--force'));
$date = $dateArgs[0] ?? discoveryTodayKst();

echo "=== Discovery Phase 1 run date={$date} ===\n";
echo 'OPENAI configured=' . ($llm->isConfigured() ? 'yes' : 'no') . "\n";

if (!$force && $repo->hasPublishedRealEditionForDate($date)) {
    $edition = $repo->findPublishedEditionByDate($date, false) ?? $repo->findEditionByDate($date);
    echo "SKIP: published real edition already exists for {$date}\n";
    if ($edition) {
        echo json_encode($edition, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        echo "change_count=" . (int) ($edition['change_count'] ?? 0) . "\n";
    }
    echo "Use --force to regenerate (will replace today's content).\n";
    exit(0);
}

$started = microtime(true);
$result = $pipeline->run($date, $force);
$elapsed = round(microtime(true) - $started, 1);

$summary = $result->toArray();
$summary['elapsed_sec'] = $elapsed;
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$verified = (int) ($summary['verified_count'] ?? 0);
if ($verified < 1) {
    fwrite(STDERR, "FAIL: verified_count={$verified} (need >= 1)\n");
    exit(1);
}

echo "\n=== Preview (admin) ===\n";
$edition = $repo->findEditionByDate($date);
if (!$edition) {
    fwrite(STDERR, "FAIL: edition missing after generate\n");
    exit(1);
}
echo "edition_id={$edition['id']} status={$edition['status']} change_count={$edition['change_count']} is_seed=" . ($edition['is_seed'] ?? '?') . "\n";
foreach ($repo->getChangesForEdition((int) $edition['id']) as $c) {
    echo sprintf("#%d [%s] %s\n", (int) $c['rank'], $c['category'], $c['title']);
    $src = $c['sources'][0]['name'] ?? '?';
    echo "  source: {$src}\n";
}

echo "\n=== Publish ===\n";
$published = $repo->publishEditionByDate($date);
if (!$published || ($published['status'] ?? '') !== 'published') {
    fwrite(STDERR, "FAIL: publish failed\n");
    exit(1);
}
echo json_encode($published, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== Done verified={$verified} elapsed={$elapsed}s ===\n";
