<?php
/**
 * 인입 draft Q-GIST 샘플 입장 품질 확인 (빈 입장 탐지)
 *
 * Usage: php tools/edu_quest_stance_sample.php
 *        php tools/edu_quest_stance_sample.php --codes=Q-GIST-622,Q-GIST-608
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$codes = [];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--codes=')) {
        $codes = array_filter(array_map('trim', explode(',', substr($arg, 8))));
    }
}

$bad = ['찬성 입장', '반대 입장', '찬성 입장 한 줄', '반대 입장 한 줄'];

function eduStanceSampleBad(?string $pro, ?string $con): bool
{
    global $bad;
    $pro = trim((string) $pro);
    $con = trim((string) $con);
    if ($pro === '' || $con === '') {
        return true;
    }
    foreach ($bad as $b) {
        if ($pro === $b || $con === $b) {
            return true;
        }
    }
    if (mb_strlen($pro) < 8 || mb_strlen($con) < 8) {
        return true;
    }

    return false;
}

$sb = eduSupabase();
if ($codes === []) {
    $rows = $sb->select('edu_daily_quests', 'status=eq.draft&order=quest_code.asc', 200) ?? [];
    foreach ($rows as $r) {
        $c = (string) ($r['quest_code'] ?? '');
        if (str_starts_with($c, 'Q-GIST-')) {
            $codes[] = $c;
        }
    }
}

$badCount = 0;
$okCount = 0;
foreach ($codes as $code) {
    $rows = $sb->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
    $q = $rows[0] ?? null;
    if ($q === null) {
        continue;
    }
    $isBad = eduStanceSampleBad($q['pro_line'] ?? '', $q['con_line'] ?? '');
    if ($isBad) {
        $badCount++;
        echo "BAD  {$code} | pro={$q['pro_line']} | con={$q['con_line']}\n";
    } else {
        $okCount++;
    }
}

echo "\nSampled: " . count($codes) . " OK={$okCount} BAD={$badCount}\n";
if ($badCount > 0) {
    exit(1);
}
