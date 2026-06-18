<?php
/**
 * Backfill hammer_hints.quest_frame for AUTO/catalog quests missing it
 *
 * Usage:
 *   php tools/edu_quest_backfill_quest_frame.php --dry-run
 *   php tools/edu_quest_backfill_quest_frame.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';

$apply = in_array('--apply', $argv ?? [], true);
$defaultFrame = 'decision_inquiry';

echo "=== EDU quest_frame backfill ===\n";
echo 'mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$supabase = eduSupabase();
$rows = $supabase->select(
    'edu_daily_quests',
    'order=created_at.desc',
    300
) ?? [];

$updated = 0;
foreach ($rows as $quest) {
    $hints = eduQuestRawHammerHints($quest);
    $frame = trim((string) ($hints['quest_frame'] ?? ''));
    if ($frame !== '') {
        continue;
    }

    $code = (string) ($quest['quest_code'] ?? '');
    $hints['quest_frame'] = $defaultFrame;
    echo "  {$code} → quest_frame={$defaultFrame}\n";

    if ($apply) {
        $supabase->update('edu_daily_quests', (string) $quest['id'], [
            'hammer_hints' => json_encode($hints, JSON_UNESCAPED_UNICODE),
        ]);
    }
    $updated++;
}

echo "\nTotal: {$updated} quest(s) " . ($apply ? 'updated' : 'would update') . "\n";
