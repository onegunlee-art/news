<?php
declare(strict_types=1);

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
$date = $dateArgs[0] ?? date('Y-m-d');
$result = $pipeline->run($date, $force);

echo json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

$reporter = new Discovery\DiscoveryRunReporter(new Discovery\SourceWhitelist(
    $config['source_whitelist'] ?? [],
    $config['source_blocklist'] ?? [],
));
$reporter->printReport($result->verifiedChanges, $result->discardedChanges);
