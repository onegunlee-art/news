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

$wouldUpdate = 0;
$updated = 0;
$failed = 0;

foreach ($rows as $quest) {
    $hints = eduQuestRawHammerHints($quest);
    $frame = trim((string) ($hints['quest_frame'] ?? ''));
    if ($frame !== '') {
        continue;
    }

    $code = (string) ($quest['quest_code'] ?? '');
    $questId = (string) ($quest['id'] ?? '');
    if ($questId === '') {
        echo "  SKIP {$code} — missing id\n";
        $failed++;
        continue;
    }

    $hints['quest_frame'] = $defaultFrame;
    echo "  {$code} → quest_frame={$defaultFrame}\n";
    $wouldUpdate++;

    if ($apply) {
        $result = $supabase->update('edu_daily_quests', 'id=eq.' . $questId, [
            'hammer_hints' => json_encode($hints, JSON_UNESCAPED_UNICODE),
        ]);
        if ($result === null) {
            echo "    FAIL: " . ($supabase->getLastError() ?: 'unknown') . "\n";
            $failed++;
        } else {
            $updated++;
        }
    }
}

echo "\nTotal: {$wouldUpdate} quest(s) " . ($apply ? "updated {$updated}, failed {$failed}" : 'would update') . "\n";
exit($apply && $failed > 0 ? 1 : 0);
