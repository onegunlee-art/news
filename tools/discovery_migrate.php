<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);
discoveryEnsurePublicMigration($pdo, $root);

echo "Discovery tables ensured.\n";
