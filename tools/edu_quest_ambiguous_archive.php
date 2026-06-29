<?php
/**
 * Q-LENS / TEST / 초기 시드 approved 해제 (→ archived, 복구 가능)
 *
 * Usage:
 *   php tools/edu_quest_ambiguous_archive.php           # dry-run
 *   php tools/edu_quest_ambiguous_archive.php --apply   # 적용
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$apply = in_array('--apply', $argv ?? [], true);

/** @var list<string> */
const EDU_AMBIGUOUS_ARCHIVE_CODES = [
    'Q-LENS-TRUMP-001',
    'Q-LENS-NUKE-001',
    'Q-LENS-ALLY-001',
    'Q-LENS-ECOWAR-001',
    'Q-LENS-AIYOUTH-001',
    'Q-LENS-CEASE-001',
    'Q-LENS-SUPPLY-001',
    'Q-LENS-ENDGAME-001',
    'Q-TEST-001',
    'Q-IRAN-FOREVER-001',
    'Q-IRAN-DEC-202606',
    'Q-G09-DEC-2022',
];

/** @var list<string> */
const EDU_AMBIGUOUS_PRESERVE_CODES = [
    'Q-NUKE-AXIS-630',
    'Q-AUTO-NUKE-630',
    'Q-AUTO-DC-150',
    'Q-AUTO-IRAN-196',
    'Q-AUTO-YOUTH-288',
];

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

echo '=== EDU Ambiguous Quest Archive ===' . PHP_EOL;
echo 'mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

$archived = 0;
$skipped = 0;

foreach (EDU_AMBIGUOUS_ARCHIVE_CODES as $code) {
    if (in_array($code, EDU_AMBIGUOUS_PRESERVE_CODES, true)) {
        echo "SKIP preserve list: {$code}\n";
        $skipped++;
        continue;
    }

    $rows = $sb->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
    $q = $rows[0] ?? null;
    if ($q === null) {
        echo "SKIP not found: {$code}\n";
        $skipped++;
        continue;
    }

    $status = (string) ($q['status'] ?? '');
    if ($status !== 'approved') {
        echo "SKIP already {$status}: {$code}\n";
        $skipped++;
        continue;
    }

    echo "ARCHIVE {$code} | {$q['quest_title']}\n";

    if ($apply) {
        $sb->update('edu_daily_quests', 'id=eq.' . ($q['id'] ?? ''), [
            'status' => 'archived',
            'live_at' => null,
            'updated_at' => date('c'),
        ]);
        $archived++;
    }
}

echo PHP_EOL . "Done: " . ($apply ? "archived={$archived}" : 'dry-run only') . " skipped={$skipped}\n";
