<?php
/**
 * EDU approved 퀘스트 difficulty_level 백필 (L1~L5)
 *
 * ★ v2: LLM 난이도 판정 (품질 점수 아님)
 *
 * Usage:
 *   php tools/edu_quest_difficulty_backfill.php --dry-run --llm
 *   php tools/edu_quest_difficulty_backfill.php --dry-run --llm --force-llm
 *   php tools/edu_quest_difficulty_backfill.php --dry-run --llm --quantile
 *   php tools/edu_quest_difficulty_backfill.php --write --llm   # 분포 OK 후만
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficulty.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficultyLlm.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$write = in_array('--write', $argv ?? [], true);
$useLlm = in_array('--llm', $argv ?? [], true) || !in_array('--legacy-filter', $argv ?? [], true);
$forceLlm = in_array('--force-llm', $argv ?? [], true);
$cacheOnly = in_array('--cache-only', $argv ?? [], true);
$applyQuantile = in_array('--quantile', $argv ?? [], true);

echo "=== EDU quest difficulty backfill ===\n";
echo 'mode: ' . ($write ? 'WRITE' : 'DRY-RUN') . "\n";
echo 'engine: ' . ($useLlm ? 'llm-v1' : 'legacy-filter (deprecated)') . "\n";
echo 'quantile: ' . ($applyQuantile ? 'yes' : 'no') . "\n";
echo 'generated: ' . date('Y-m-d H:i:s') . "\n\n";

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$probe = $supabase->select(
    'edu_daily_quests',
    'select=difficulty_level&status=eq.approved',
    1
);
if ($probe === null) {
    $err = $supabase->getLastError() ?: 'unknown';
    fwrite(STDERR, "FAIL: difficulty_level column missing.\n");
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

$llm = null;
if ($useLlm && !$cacheOnly) {
    $llm = eduLlm();
}

$auditRows = [];
$skipped = [];
$distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$wouldWrite = 0;
$written = 0;
$failed = 0;
$llmErrors = 0;

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

    if ($useLlm) {
        $context = [
            'title' => $title,
            'news_id' => $newsId,
            'excerpt' => (string) ($article['excerpt'] ?? $meta['content_preview'] ?? ''),
            'hinge' => (string) ($extraction['hinge'] ?? $quest['conflict_summary'] ?? ''),
            'pro_line' => (string) ($quest['pro_line'] ?? ''),
            'con_line' => (string) ($quest['con_line'] ?? ''),
            'lens_label' => $lensLabel,
            'category' => (string) ($metaScores['category'] ?? ''),
            'category_label' => (string) ($metaScores['category_label'] ?? ''),
        ];

        try {
            if ($cacheOnly) {
                $cached = eduQuestDifficultyLoadRatingCache($code);
                if ($cached === null) {
                    echo "  SKIP {$code} — no cache (--cache-only)\n";
                    continue;
                }
                $derived = [
                    'level' => eduCoachLevelNormalize((int) $cached['difficulty_level']),
                    'label_ko' => (string) ($cached['label_ko'] ?? ''),
                    'label_en' => (string) ($cached['label_en'] ?? ''),
                    'difficulty_score' => (int) ($cached['difficulty_score'] ?? 50),
                    'reasons' => is_array($cached['reasons'] ?? null) ? $cached['reasons'] : [],
                    'signals' => is_array($cached['signals'] ?? null) ? $cached['signals'] : [],
                    'student_frame_ko' => (string) ($cached['student_frame_ko'] ?? ''),
                    'source' => 'cache',
                    'quantile_adjusted' => (bool) ($cached['quantile_adjusted'] ?? false),
                ];
            } else {
                $derived = eduQuestDifficultyResolveRating(
                    $llm,
                    $quest,
                    $context,
                    useCache: !$forceLlm,
                    forceLlm: $forceLlm
                );
            }
        } catch (Throwable $e) {
            echo "  FAIL {$code} — " . $e->getMessage() . "\n";
            $llmErrors++;
            continue;
        }

        $level = (int) $derived['level'];
        $reasonSummary = implode('; ', array_slice($derived['reasons'], 0, 3));
        $filterScore = null;
        $axisCount = null;
        $extractionSource = (string) ($derived['source'] ?? 'llm');
    } else {
        $derived = eduQuestDeriveDifficultyLevel($quest, $extraction, $meta);
        $level = (int) $derived['level'];
        $reasonSummary = implode('; ', array_slice($derived['reasons'], 0, 4));
        $filterScore = (int) $derived['score'];
        $axisCount = $derived['axis_count'];
        $extractionSource = (string) $derived['source'];
        $derived['difficulty_score'] = $filterScore;
        $derived['student_frame_ko'] = '';
        $derived['signals'] = [];
        $derived['quantile_adjusted'] = false;
    }

    $subtitlePreview = $lensLabel !== '' ? "쟁점: {$lensLabel}" : '';

    $auditRows[] = [
        'quest_code' => $code,
        'quest_title' => $title,
        'news_id' => $newsId > 0 ? $newsId : null,
        'difficulty_level' => $level,
        'label_ko' => $derived['label_ko'],
        'label_en' => $derived['label_en'],
        'difficulty_score' => (int) ($derived['difficulty_score'] ?? 0),
        'filter_score' => $filterScore,
        'axis_count' => $axisCount,
        'extraction_source' => $extractionSource,
        'reasons_summary' => $reasonSummary,
        'student_frame_ko' => (string) ($derived['student_frame_ko'] ?? ''),
        'signals' => $derived['signals'] ?? [],
        'quantile_adjusted' => (bool) ($derived['quantile_adjusted'] ?? false),
        'lens_label' => $lensLabel !== '' ? $lensLabel : null,
        'subtitle_preview' => $subtitlePreview !== '' ? $subtitlePreview : null,
        'existing_level' => eduQuestReadDifficultyLevel($quest),
    ];
}

// Quantile safety net
if ($applyQuantile && $auditRows !== []) {
    echo "\n--- Applying quantile adjustment ---\n";
    $quantileMap = eduQuestDifficultyQuantileLevels($auditRows);
    foreach ($auditRows as &$row) {
        $code = (string) ($row['quest_code'] ?? '');
        if (!isset($quantileMap[$code])) {
            continue;
        }
        $newLevel = $quantileMap[$code];
        $oldLevel = (int) $row['difficulty_level'];
        if ($newLevel !== $oldLevel) {
            $labels = eduQuestDifficultyLabel($newLevel);
            $row['difficulty_level'] = $newLevel;
            $row['label_ko'] = $labels['ko'];
            $row['label_en'] = $labels['en'];
            $row['quantile_adjusted'] = true;
            $row['reasons_summary'] .= '; quantile→L' . $newLevel;

            $cached = eduQuestDifficultyLoadRatingCache($code);
            if ($cached !== null) {
                $cached['difficulty_level'] = $newLevel;
                $cached['label_ko'] = $labels['ko'];
                $cached['label_en'] = $labels['en'];
                $cached['quantile_adjusted'] = true;
                $cached['quantile_from_level'] = $oldLevel;
                eduQuestDifficultySaveRatingCache($cached);
            }
        }
    }
    unset($row);
}

$distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($auditRows as $row) {
    $lv = (int) ($row['difficulty_level'] ?? 0);
    if ($lv >= 1 && $lv <= 5) {
        $distribution[$lv]++;
    }
}

foreach ($auditRows as $row) {
    $code = (string) ($row['quest_code'] ?? '');
    $level = (int) $row['difficulty_level'];
    $existing = $row['existing_level'] ?? null;
    $changed = $existing === null || $existing !== $level;

    echo sprintf(
        "  %s → L%d (%s) diff_score=%d src=%s%s%s\n",
        $code,
        $level,
        $row['label_ko'],
        (int) ($row['difficulty_score'] ?? 0),
        $row['extraction_source'],
        ($row['quantile_adjusted'] ?? false) ? ' [quantile]' : '',
        $changed ? '' : ' [unchanged]'
    );

    if (!$changed) {
        continue;
    }

    $wouldWrite++;
    if ($write) {
        $questId = '';
        foreach ($quests as $q) {
            if ((string) ($q['quest_code'] ?? '') === $code) {
                $questId = (string) ($q['id'] ?? '');
                break;
            }
        }
        if ($questId !== '') {
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

$gate = eduQuestDifficultyDistributionGate($distribution, $total);
$anchors = eduQuestDifficultyAnchorChecks($auditRows);

$mdPath = $root . '/docs/edu_quest_difficulty_audit.md';
$jsonPath = $root . '/docs/edu_quest_difficulty_audit.json';

$md = [];
$md[] = '# EDU 퀘스트 난이도 판정 (L1~L5) — LLM v2';
$md[] = '';
$md[] = 'Generated: ' . date('c');
$md[] = 'Mode: ' . ($write ? 'WRITE' : 'DRY-RUN');
$md[] = 'Engine: ' . ($useLlm ? 'llm-v1' : 'legacy-filter');
$md[] = 'Quantile: ' . ($applyQuantile ? 'applied' : 'no');
$md[] = 'Approved quests tagged: ' . $total;
$md[] = 'Skipped: ' . count($skipped);
$md[] = '';
$md[] = '## 레벨 분포 (★ write 게이트)';
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
$md[] = '**게이트:** L1≥8 · L3≤35% · 앵커 상식 (전기세 L1-2, RSI/양자 L4-5)';
$md[] = '';
$md[] = '### 분포 게이트';
foreach ($gate['checks'] as $c) {
    $md[] = '- ' . $c;
}
$md[] = '';
$md[] = '### 앵커 샘플';
foreach ($anchors as $c) {
    $md[] = '- ' . $c;
}
$md[] = '';
$md[] = '**L1 긍정 프레임:** L1 = 질 낮음 아님 · "관찰자 단계 · 시작하기 좋은 글 (친숙한 주제)"';
$md[] = '';
$md[] = '## 퀘스트별 판정';
$md[] = '';
$md[] = '| quest_code | title | L | 라벨 | diff_score | student_frame | 근거 |';
$md[] = '|------------|-------|---|------|------------|---------------|------|';

foreach ($auditRows as $r) {
    $frame = mb_substr((string) ($r['student_frame_ko'] ?? ''), 0, 28);
    if ($frame === '') {
        $frame = '—';
    }
    $md[] = sprintf(
        '| %s | %s | L%d | %s | %d | %s | %s |',
        $r['quest_code'],
        str_replace('|', '/', mb_substr($r['quest_title'], 0, 36)),
        $r['difficulty_level'],
        $r['label_ko'],
        (int) ($r['difficulty_score'] ?? 0),
        str_replace('|', '/', $frame),
        str_replace('|', '/', mb_substr($r['reasons_summary'], 0, 40))
    );
}

file_put_contents($mdPath, implode("\n", $md) . "\n");

$jsonPayload = [
    'generated_at' => date('c'),
    'mode' => $write ? 'write' : 'dry-run',
    'engine' => $useLlm ? 'llm-v1' : 'legacy-filter',
    'quantile_applied' => $applyQuantile,
    'total_tagged' => $total,
    'skipped' => $skipped,
    'distribution' => $distribution,
    'distribution_pct' => array_map(
        static fn ($n) => $total > 0 ? round(100 * $n / $total, 1) : 0,
        $distribution
    ),
    'gate' => $gate,
    'anchor_checks' => $anchors,
    'rows' => $auditRows,
];
file_put_contents($jsonPath, json_encode($jsonPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

echo "\n=== Distribution ===\n";
foreach ([1, 2, 3, 4, 5] as $lv) {
    echo sprintf("  L%d: %d (%s)\n", $lv, $distribution[$lv], $pct($distribution[$lv]));
}
echo "\n=== Gate ===\n";
foreach ($gate['checks'] as $c) {
    echo "  {$c}\n";
}
echo "\n=== Anchors ===\n";
foreach ($anchors as $c) {
    echo "  {$c}\n";
}
echo "\nWrote {$mdPath}\n";
echo "Wrote {$jsonPath}\n";
echo "\nTotal: {$total} tagged, {$wouldWrite} " . ($write ? "updated {$written}, failed {$failed}" : 'would update');
echo $llmErrors > 0 ? ", llm_errors {$llmErrors}" : '';
echo "\n";

if ($write && !$gate['pass']) {
    fwrite(STDERR, "\nWARN: write completed but distribution gate FAILED — review audit before UI deploy\n");
}

if (!$write && !$gate['pass']) {
    echo "\n→ Gate FAIL: adjust prompt and re-run --dry-run --llm --force-llm, or add --quantile\n";
}

exit(($write && $failed > 0) || $llmErrors > 0 ? 1 : 0);
