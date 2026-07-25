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

$date = $argv[1] ?? date('Y-m-d');
$result = $pipeline->run($date);

echo json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
