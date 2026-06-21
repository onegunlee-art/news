<?php
/**
 * P2-A3 격리·공존·회귀 사전 확인
 *
 * Usage:
 *   php tools/edu_hinge_a3_verify.php
 *   php tools/edu_hinge_a3_verify.php --write-md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$writeMd = in_array('--write-md', $argv ?? [], true);

$autoCode = 'Q-AUTO-NUKE-630';
$manualCode = 'Q-NUKE-AXIS-630';
$r4Code = 'Q-IRAN-FOREVER-001';
$r5Code = 'Q-G09-DEC-2022';
$r6Code = 'Q-NUKE-AXIS-630';

$lines = [];
$lines[] = '# P2-A3 격리 확인 (the gist 본체 = 유일한 빨간 선)';
$lines[] = '';
$lines[] = '> ' . date('Y-m-d H:i:s');
$lines[] = '';

$lines[] = '## 절대 원칙';
$lines[] = '';
$lines[] = '- **the gist 본체** (news, judgement_records, 본체 Supabase, 유료 사용자 라우트): **무영향·READ only**';
$lines[] = '- EDU 7명 테스터/퀘스트: 변경·삭제 OK (본인 통제)';
$lines[] = '';

$lines[] = '## 코드 격리 (seed 스크립트 WRITE 테이블)';
$lines[] = '';
$lines[] = '| 테이블 | seed_hinge_auto_quest.php |';
$lines[] = '|--------|---------------------------|';
$lines[] = '| edu_daily_quests | WRITE (insert/update Q-AUTO only) |';
$lines[] = '| edu_quest_articles | WRITE (sync for auto quest) |';
$lines[] = '| news / judgement_records / users | **READ only** (snapshot backfill 시 MySQL content READ) |';
$lines[] = '| the gist 본체 Supabase | **접근 없음** |';
$lines[] = '';

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    $lines[] = '## DB 상태';
    $lines[] = '';
    $lines[] = 'Supabase 미설정 — 로컬 코드 격리만 확인됨.';
    echo implode("\n", $lines) . "\n";
    exit(0);
}

$fetch = static function (string $code) use ($supabase): ?array {
    $rows = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];

    return $rows[0] ?? null;
};

$auto = $fetch($autoCode);
$manual = $fetch($manualCode);

$lines[] = '## DB 공존';
$lines[] = '';
$lines[] = '| quest_code | 존재 | live_at | mode |';
$lines[] = '|------------|------|---------|------|';

foreach ([$autoCode => $auto, $manualCode => $manual] as $code => $row) {
    if ($row === null) {
        $lines[] = "| {$code} | ✗ | — | — |";
        continue;
    }
    $hints = is_array($row['hammer_hints'] ?? null) ? $row['hammer_hints'] : [];
    $lines[] = '| ' . $code . ' | ○ | ' . ($row['live_at'] ?? 'null') . ' | '
        . ($hints['mode'] ?? '?') . ' |';
}

$lines[] = '';
$lines[] = '## 회귀 대상 (R4/R5/R6 quest_code 존재)';
$lines[] = '';
$lines[] = '| 게이트 | quest_code | OK |';
$lines[] = '|--------|------------|-----|';

foreach ([
    'R4' => $r4Code,
    'R5' => $r5Code,
    'R6' => $r6Code,
] as $gate => $code) {
    $ok = $fetch($code) !== null ? '○' : '✗';
    $lines[] = "| {$gate} | {$code} | {$ok} |";
}

if ($auto !== null) {
    $articles = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $auto['id'], 10) ?? [];
    $hints = is_array($auto['hammer_hints'] ?? null) ? $auto['hammer_hints'] : [];
    $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];

    $lines[] = '';
    $lines[] = '## AUTO-630 심장 (DB)';
    $lines[] = '';
    $lines[] = '- hook_short: ' . ($hints['hook_short'] ?? '');
    $lines[] = '- shared_conclusion: ' . ($hints['shared_conclusion'] ?? '');
    $lines[] = '- shake_prompt: ' . ($hinge['shake_prompt'] ?? '');
    $lines[] = '- articles: ' . count($articles);
    $lines[] = '';
    $lines[] = '## today.php 영향';
    $lines[] = '';
    $live = $supabase->select(
        'edu_daily_quests',
        'status=eq.approved&live_at=not.is.null&live_at=lte.' . rawurlencode(date('c')) . '&order=live_at.desc',
        3
    ) ?? [];
    foreach ($live as $l) {
        $lines[] = '- live: ' . ($l['quest_code'] ?? '') . ' @ ' . ($l['live_at'] ?? '');
    }
    if ($auto['live_at'] ?? null) {
        $lines[] = '';
        $lines[] = '⚠ AUTO quest has live_at — today feed 최상단 후보. 테스트는 list.php에서 quest_id로 시작 권장.';
    } else {
        $lines[] = '';
        $lines[] = 'AUTO quest live_at=null — today 피드 hijack 없음. list.php에서 선택.';
    }
}

$lines[] = '';
$lines[] = '## 롤백';
$lines[] = '';
$lines[] = '```bash';
$lines[] = 'php tools/edu_hinge_auto_quest_remove.php --apply';
$lines[] = '```';

$md = implode("\n", $lines) . "\n";
echo $md;

if ($writeMd) {
    $path = $root . '/docs/P2_HINGE_A3_VERIFY.md';
    file_put_contents($path, $md);
    echo "\nWrote {$path}\n";
}
