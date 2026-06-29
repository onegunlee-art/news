<?php
/**
 * 옛날 Q-AUTO 테스트 퀘스트 라이브 해제 (approved → archived)
 *
 * Usage:
 *   php tools/edu_quest_legacy_archive.php           # dry-run
 *   php tools/edu_quest_legacy_archive.php --apply   # 적용
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$apply = in_array('--apply', $argv ?? [], true);

/** @var list<string> */
const EDU_LEGACY_ARCHIVE_CODES = [
    'Q-AUTO-260612-29FD',
    'Q-AUTO-260613-9633',
    'Q-AUTO-260617-5114-V3',
    'Q-AUTO-260617-3F5E-V3',
    'Q-AUTO-260617-DF56-V3',
    'Q-AUTO-260617-5BF5',
    'Q-AUTO-260617-2DDB-V3',
    'Q-AUTO-260617-8498-V3',
    'Q-AUTO-260617-B480',
];

function eduLegacyArchiveIsPlaceholderStance(?string $pro, ?string $con): bool
{
    $bad = ['찬성 입장', '반대 입장', '찬성 입장 한 줄', '반대 입장 한 줄'];

    return in_array(trim((string) $pro), $bad, true)
        || in_array(trim((string) $con), $bad, true);
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

echo '=== EDU Legacy Quest Archive ===' . PHP_EOL;
echo 'mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

$archived = 0;
$skipped = 0;

foreach (EDU_LEGACY_ARCHIVE_CODES as $code) {
    $rows = $sb->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
    $q = $rows[0] ?? null;
    if ($q === null) {
        echo "SKIP not found: {$code}\n";
        $skipped++;
        continue;
    }

    $status = (string) ($q['status'] ?? '');
    $pro = (string) ($q['pro_line'] ?? '');
    $con = (string) ($q['con_line'] ?? '');

    if ($status !== 'approved') {
        echo "SKIP already {$status}: {$code}\n";
        $skipped++;
        continue;
    }

    if (!eduLegacyArchiveIsPlaceholderStance($pro, $con)) {
        echo "SKIP non-placeholder stance: {$code} pro={$pro} con={$con}\n";
        $skipped++;
        continue;
    }

    echo "ARCHIVE {$code} | {$q['quest_title']} | pro={$pro} con={$con}\n";

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
