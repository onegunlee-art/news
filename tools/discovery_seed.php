<?php
declare(strict_types=1);

/**
 * Discovery 디자인 검증용 시드 삽입 (2026-07-16 ~ 2026-07-25, 하루 9개).
 * 재실행 시 기존 시드만 지우고 다시 삽입. 진짜 발행분(is_seed=0)은 건드리지 않음.
 *
 * Usage: php tools/discovery_seed.php
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';
require_once __DIR__ . '/../src/discovery/seed/DiscoverySeedCatalog.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$pdo = discoveryGetDb($root);
discoveryEnsureTables($pdo, $root);
discoveryEnsurePublicMigration($pdo, $root);
discoveryEnsureSeedMigration($pdo, $root);

$repo = new Discovery\DiscoveryRepository($pdo);

echo "=== Discovery design seed ===\n";
echo 'DISCOVERY_SHOW_SEED=' . (discoveryConfig($root)['show_seed'] ? 'true' : 'false') . "\n\n";

$deleted = $repo->deleteAllSeedEditions();
echo "Cleared existing seed editions: {$deleted}\n";

$dates = Discovery\DiscoverySeedCatalog::dates();
$insertedDays = 0;
$insertedChanges = 0;
$skippedDays = [];

foreach ($dates as $dayIndex => $date) {
    if ($repo->hasRealEditionForDate($date)) {
        $skippedDays[] = $date;
        echo "SKIP {$date}: real edition exists (is_seed=0)\n";
        continue;
    }

    $changes = Discovery\DiscoverySeedCatalog::changesForDate($date);
    $edition = $repo->insertSeedEdition($date, $changes, $dayIndex);
    if (!$edition) {
        $skippedDays[] = $date;
        echo "SKIP {$date}: insert blocked\n";
        continue;
    }

    $insertedDays++;
    $insertedChanges += count($changes);
    echo "OK {$date}: " . count($changes) . " changes (edition_id={$edition['id']})\n";
}

echo "\n--- Summary ---\n";
echo "days_inserted={$insertedDays}\n";
echo "changes_inserted={$insertedChanges}\n";
echo 'seed_editions=' . $repo->countSeedEditions() . "\n";
echo 'seed_changes=' . $repo->countSeedChanges() . "\n";
if ($skippedDays !== []) {
    echo 'skipped_dates=' . implode(', ', $skippedDays) . "\n";
}
echo "\nTo expose seed on public /discovery, set DISCOVERY_SHOW_SEED=true in .env\n";
echo "To remove all seed: php tools/discovery_seed_clear.php\n";
