<?php
/**
 * EDU approved 퀘스트 difficulty_level 백필 (L1~L5)
 *
 * 배포 순서:
 *   1) database/migrations/add_edu_difficulty_level.sql — Supabase SQL Editor
 *   2) php tools/edu_quest_difficulty_backfill.php --dry-run
 *   3) php tools/edu_quest_difficulty_backfill.php --write
 *   4) docs/edu_quest_difficulty_audit.md 레벨 분포 확인 → 구간 조정 여부 결정
 *
 * Usage:
 *   php tools/edu_quest_difficulty_backfill.php --dry-run
 *   php tools/edu_quest_difficulty_backfill.php --write
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficulty.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

$write = in_array('--write', $argv ?? [], true);
$dryRun = !$write;

echo "=== EDU quest difficulty backfill ===\n";
echo 'mode: ' . ($write ? 'WRITE' : 'DRY-RUN') . "\n";
echo 'generated: ' . date('Y-m-d H:i:s') . "\n\n";

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

// 컬럼 존재 확인 (마이그레이션 전 백필 방지)
$probe = $supabase->select(
    'edu_daily_quests',
    'select=difficulty_level&status=eq.approved',
    1
);
if ($probe === null) {
    $err = $supabase->getLastError() ?: 'unknown';
    fwrite(STDERR, "FAIL: difficulty_level column missing or unreadable.\n");
    fwrite(STDERR, "Run database/migrations/add_edu_difficulty_level.sql in Supabase first.\n");
    fwrite(STDERR, "Supabase error: {$err}\n");
    exit(1);
}

$quests = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&order=live_at.desc.nullslast',
    200
) ?? [];

if ($quests === []) {
    fwrite(STDERR, "No approved quests found\n");
    exit(1);
}

$questIds = array_values(array_filter(array_map(
    static fn ($q) => (string) ($q['id'] ?? ''),
    $quests
)));

$articlesByQuest = [];
foreach (array_chunk($questIds, 40) as $chunk) {
    $filter = 'quest_id=in.(' . implode(',', array_map('rawurlencode', $chunk)) . ')&role=eq.primary';
    $rows = $supabase->select('edu_quest_articles', $filter, 200) ?? [];
    foreach ($rows as $row) {
        $qid = (string) ($row['quest_id'] ?? '');
        if ($qid !== '') {
            $articlesByQuest[$qid] = $row;
        }
    }
}

$auditRows = [];
$skipped = [];
$distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$wouldWrite = 0;
$written = 0;
$failed = 0;

foreach ($quests as $quest) {
    $code = (string) ($quest['quest_code'] ?? '');
    $questId = (string) ($quest['id'] ?? '');
    $title = (string) ($quest['quest_title'] ?? '');
    $metaScores = eduQuestListCategoryMeta($quest);
    $lensLabel = (string) ($metaScores['lens_label'] ?? '');

    $article = $articlesByQuest[$questId] ?? null;
    $newsId = (int) ($article['news_id'] ?? 0);

    $meta = eduQuestDifficultyMetaForQuest($quest, $newsId);
    if ($newsId > 0) {
        $meta['content_preview'] = mb_substr(
            (string) ($article['excerpt'] ?? $meta['content_preview'] ?? ''),
            0,
            400
        );
    }

    $decl = eduQuestFilterDeclarationCheck($meta, null);
    if ($decl['is_declaration'] ?? false) {
        $skipped[] = ['quest_code' => $code, 'reason' => 'declaration blocklist'];
        echo "  SKIP {$code} — declaration\n";
        continue;
    }

    $extraction = $newsId > 0 ? eduHingeLoadExtraction($newsId) : null;
    $derived = eduQuestDeriveDifficultyLevel($quest, $extraction, $meta);

    $level = (int) $derived['level'];
    $distribution[$level] = ($distribution[$level] ?? 0) + 1;

    $reasonSummary = implode('; ', array_slice($derived['reasons'], 0, 4));
    $subtitlePreview = $lensLabel !== '' ? "쟁점: {$lensLabel}" : '';

    $auditRows[] = [
        'quest_code' => $code,
        'quest_title' => $title,
        'news_id' => $newsId > 0 ? $newsId : null,
        'difficulty_level' => $level,
        'label_ko' => $derived['label_ko'],
        'label_en' => $derived['label_en'],
        'filter_score' => $derived['score'],
        'axis_count' => $derived['axis_count'],
        'extraction_source' => $derived['source'],
        'reasons_summary' => $reasonSummary,
        'lens_label' => $lensLabel !== '' ? $lensLabel : null,
        'subtitle_preview' => $subtitlePreview !== '' ? $subtitlePreview : null,
        'existing_level' => eduQuestReadDifficultyLevel($quest),
    ];

    $existing = eduQuestReadDifficultyLevel($quest);
    $changed = $existing === null || $existing !== $level;
    echo sprintf(
        "  %s → L%d (%s) score=%d axis=%s src=%s%s\n",
        $code,
        $level,
        $derived['label_ko'],
        $derived['score'],
        $derived['axis_count'] === null ? '—' : (string) $derived['axis_count'],
        $derived['source'],
        $changed ? '' : ' [unchanged]'
    );

    if (!$changed) {
        continue;
    }

    $wouldWrite++;
    if ($write && $questId !== '') {
        $result = $supabase->update('edu_daily_quests', 'id=eq.' . rawurlencode($questId), [
            'difficulty_level' => $level,
        ]);
        if ($result === null) {
            echo "    FAIL: " . ($supabase->getLastError() ?: 'unknown') . "\n";
            $failed++;
        } else {
            $written++;
        }
    }
}

$total = count($auditRows);
$pct = static function (int $n) use ($total): string {
    if ($total === 0) {
        return '0%';
    }

    return round(100 * $n / $total, 1) . '%';
};

$labels = [
    1 => 'L1 관찰자',
    2 => 'L2 질문자',
    3 => 'L3 논객',
    4 => 'L4 분석가',
    5 => 'L5 칼럼니스트',
];

$mdPath = $root . '/docs/edu_quest_difficulty_audit.md';
$jsonPath = $root . '/docs/edu_quest_difficulty_audit.json';

$md = [];
$md[] = '# EDU 퀘스트 난이도 판정 (L1~L5)';
$md[] = '';
$md[] = 'Generated: ' . date('c');
$md[] = 'Mode: ' . ($write ? 'WRITE' : 'DRY-RUN');
$md[] = 'Approved quests tagged: ' . $total;
$md[] = 'Skipped: ' . count($skipped);
$md[] = '';
$md[] = '## 레벨 분포 (★ 구간 조정 판단용)';
$md[] = '';
$md[] = '| Level | 라벨 | 개수 | 비율 |';
$md[] = '|-------|------|------|------|';
foreach ([1, 2, 3, 4, 5] as $lv) {
    $md[] = sprintf(
        '| L%d | %s | %d | %s |',
        $lv,
        str_replace('L' . $lv . ' ', '', $labels[$lv]),
        $distribution[$lv],
        $pct($distribution[$lv])
    );
}
$md[] = '';
$md[] = '**판정 구간 (현재):** score 40–54→L1, 55–64→L2, 65–74→L3, 75–84→L4, 85+→L5';
$md[] = '**보정:** weak tension 상한 L2 · axis≥2 min L4 · axis≥3+high→L5';
$md[] = '';
$md[] = '한쪽(L3 등)에 몰리면 위 구간을 조정한 뒤 `--dry-run` 재실행.';
$md[] = '';
$md[] = '## 퀘스트별 판정';
$md[] = '';
$md[] = '| quest_code | title | L | 라벨 | score | axis | lens / 쟁점 미리보기 | 근거 |';
$md[] = '|------------|-------|---|------|-------|------|---------------------|------|';

foreach ($auditRows as $r) {
    $lensCol = $r['subtitle_preview'] ?? ($r['lens_label'] ?? '—');
    $md[] = sprintf(
        '| %s | %s | L%d | %s | %d | %s | %s | %s |',
        $r['quest_code'],
        str_replace('|', '/', mb_substr($r['quest_title'], 0, 40)),
        $r['difficulty_level'],
        $r['label_ko'],
        $r['filter_score'],
        $r['axis_count'] === null ? '—' : (string) $r['axis_count'],
        str_replace('|', '/', mb_substr((string) $lensCol, 0, 36)),
        str_replace('|', '/', mb_substr($r['reasons_summary'], 0, 48))
    );
}

$md[] = '';
$md[] = '## 쟁점 접두 검토';
$md[] = '';
$md[] = '`subtitle_preview` 열의 "쟁점: {lens_label}" — diff에서 자연스러우면 유지, 어색하면 Explore/Home에서 접두 제거.';
$md[] = '';

file_put_contents($mdPath, implode("\n", $md) . "\n");

$jsonPayload = [
    'generated_at' => date('c'),
    'mode' => $write ? 'write' : 'dry-run',
    'total_tagged' => $total,
    'skipped' => $skipped,
    'distribution' => $distribution,
    'distribution_pct' => array_map(
        static fn ($n) => $total > 0 ? round(100 * $n / $total, 1) : 0,
        $distribution
    ),
    'thresholds' => [
        'L1' => '40-54',
        'L2' => '55-64',
        'L3' => '65-74',
        'L4' => '75-84',
        'L5' => '85+',
    ],
    'rows' => $auditRows,
];
file_put_contents($jsonPath, json_encode($jsonPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

echo "\n=== Distribution ===\n";
foreach ([1, 2, 3, 4, 5] as $lv) {
    echo sprintf("  L%d: %d (%s)\n", $lv, $distribution[$lv], $pct($distribution[$lv]));
}
echo "\nWrote {$mdPath}\n";
echo "Wrote {$jsonPath}\n";
echo "\nTotal: {$total} tagged, {$wouldWrite} " . ($write ? "updated {$written}, failed {$failed}" : 'would update') . "\n";

exit($write && $failed > 0 ? 1 : 0);
