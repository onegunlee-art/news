<?php
declare(strict_types=1);

/**
 * Discovery 디자인 검증용 시드 전체 삭제.
 *
 * Usage: php tools/discovery_seed_clear.php
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);
discoveryEnsureSeedMigration($pdo, $root);

$repo = new Discovery\DiscoveryRepository($pdo);

$beforeEditions = $repo->countSeedEditions();
$beforeChanges = $repo->countSeedChanges();

$deleted = $repo->deleteAllSeedEditions();

echo "=== Discovery seed clear ===\n";
echo "removed_editions={$deleted} (was {$beforeEditions})\n";
echo "removed_changes≈{$beforeChanges}\n";
echo 'remaining_seed_editions=' . $repo->countSeedEditions() . "\n";
echo 'remaining_seed_changes=' . $repo->countSeedChanges() . "\n";
echo "OK: seed data cleared. Real editions (is_seed=0) untouched.\n";
