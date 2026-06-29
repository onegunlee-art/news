<?php
/**
 * Q-GIST con_line 백필 — shared_conclusion 대신 _hinge.side_b 사용
 *
 * Usage:
 *   php tools/edu_quest_fix_con_lines.php           # dry-run
 *   php tools/edu_quest_fix_con_lines.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$apply = in_array('--apply', $argv ?? [], true);

function eduQuestConLineLooksBroken(?string $con, ?string $sideB): bool
{
    $con = trim((string) $con);
    $sideB = trim((string) $sideB);
    if ($con === '' || $sideB === '') {
        return false;
    }
    if ($con === $sideB) {
        return false;
    }
    if (str_starts_with($con, ',') || str_starts_with($con, '그러나') || str_starts_with($con, '하지만')) {
        return true;
    }

    return !str_starts_with($sideB, mb_substr($con, 0, 8)) && str_contains($sideB, '본문');
}

$sb = eduSupabase();
$rows = $sb->select('edu_daily_quests', 'status=eq.approved&order=quest_code.asc', 200) ?? [];

$fixed = 0;
$skipped = 0;

echo '=== EDU Q-GIST con_line fix ===' . PHP_EOL;
echo 'mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

foreach ($rows as $q) {
    $code = (string) ($q['quest_code'] ?? '');
    if (!str_starts_with($code, 'Q-GIST-')) {
        continue;
    }

    $hints = $q['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }
    $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];
    $sideB = trim((string) ($hinge['side_b'] ?? ''));
    $con = trim((string) ($q['con_line'] ?? ''));

    if (!eduQuestConLineLooksBroken($con, $sideB)) {
        $skipped++;
        continue;
    }

    $newCon = mb_substr($sideB, 0, 140);
    echo "FIX {$code}\n  was: " . mb_substr($con, 0, 60) . "\n  new: " . mb_substr($newCon, 0, 60) . "\n";

    if ($apply) {
        $sb->update('edu_daily_quests', 'id=eq.' . ($q['id'] ?? ''), [
            'con_line' => $newCon,
            'updated_at' => date('c'),
        ]);
        $fixed++;
    } else {
        $fixed++;
    }
}

echo PHP_EOL . "fixed={$fixed} skipped={$skipped}\n";
