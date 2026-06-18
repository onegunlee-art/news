<?php
/**
 * GIST EDU — Live gate: pilot_priority=A approved만 live_at (주 1~2건)
 *
 * Usage:
 *   php tools/edu_quest_live_gate.php --dry-run
 *   php tools/edu_quest_live_gate.php --apply --max=2
 *   php tools/edu_quest_live_gate.php --apply --max=1 --quest-code=Q-IRAN-FOREVER-001
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$dryRun = !in_array('--apply', $argv ?? [], true);
$maxLive = 2;
$questCode = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $maxLive = max(1, (int) substr($arg, 6));
    }
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = substr($arg, 13);
    }
}

echo "=== EDU Quest Live Gate ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . " max={$maxLive}\n\n";

$supabase = eduSupabase();

if ($questCode !== null) {
    $candidates = $supabase->select(
        'edu_daily_quests',
        'quest_code=eq.' . rawurlencode($questCode) . '&status=eq.approved',
        1
    ) ?? [];
} else {
    $candidates = $supabase->select(
        'edu_daily_quests',
        'status=eq.approved&pilot_priority=eq.A&live_at=is.null&order=created_at.asc',
        $maxLive
    ) ?? [];
}

if ($candidates === []) {
    echo "No approved A-priority quests waiting for live_at\n";
    exit(0);
}

$liveNow = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&live_at=not.is.null&live_at=lte.' . rawurlencode(date('c')) . '&order=live_at.desc',
    5
) ?? [];
echo 'Currently live: ' . count($liveNow) . "\n";
foreach ($liveNow as $l) {
    echo '  - ' . ($l['quest_code'] ?? '') . ' live_at=' . ($l['live_at'] ?? '') . "\n";
}
echo "\n";

$set = 0;
foreach (array_slice($candidates, 0, $maxLive) as $q) {
    $code = (string) ($q['quest_code'] ?? '');
    $priority = (string) ($q['pilot_priority'] ?? '');
    if ($priority !== 'A' && $questCode === null) {
        echo "SKIP {$code}: pilot_priority != A\n";
        continue;
    }

    $articles = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $q['id'], 10) ?? [];
    if (count($articles) < 3) {
        echo "SKIP {$code}: <3 articles\n";
        continue;
    }

    $liveAt = date('c');
    echo "SET live {$code} at {$liveAt}\n";

    if (!$dryRun) {
        $supabase->update('edu_daily_quests', 'id=eq.' . $q['id'], [
            'live_at' => $liveAt,
            'expires_at' => date('c', strtotime('+7 days')),
            'updated_at' => $liveAt,
        ]);
    }
    $set++;
}

echo "\n=== Live gate: {$set} quest(s) " . ($dryRun ? 'would be' : '') . " set ===\n";
echo "Rollback: clear live_at on quest row to revert today feed\n";
