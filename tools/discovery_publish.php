<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
discoveryEnsureEnabled($root);

$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);

$repo = new Discovery\DiscoveryRepository($pdo);
$date = $argv[1] ?? date('Y-m-d');
$edition = $repo->publishEditionByDate($date);

if (!$edition) {
    fwrite(STDERR, "No edition for {$date}\n");
    exit(1);
}

echo json_encode($edition, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
