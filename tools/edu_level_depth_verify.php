<?php
/**
 * EDU 7단계 Phase 3 — level 1~7 구조 깊이 검증 (프로덕션 무관)
 *
 * Usage:
 *   php tools/edu_level_depth_verify.php 630
 *   php tools/edu_level_depth_verify.php 630 150
 *   php tools/edu_level_depth_verify.php 630 --levels=1,4,7   (Phase 1 subset)
 *   php tools/edu_level_depth_verify.php 630 --stdout-only
 *   php tools/edu_level_depth_verify.php --dry-run
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduLevelDepthExtract.php';

$argv = $argv ?? [];
$stdoutOnly = in_array('--stdout-only', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$levelsOverride = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--levels=')) {
        $levelsOverride = eduLevelDepthParseLevelsArg(substr($arg, 9));
    }
}
$verifyLevels = eduLevelDepthVerifyLevels($levelsOverride);
$phase = count($verifyLevels) >= 7 ? 'phase3' : 'phase1';

$numericArgs = array_values(array_filter($argv, static fn ($a) => is_numeric($a)));
$ids = $numericArgs !== [] ? array_map('intval', $numericArgs) : [630];

if ($dryRun) {
    echo "DRY RUN — prompts only (no MySQL, no LLM)\n";
    echo 'Levels: ' . implode(',', $verifyLevels) . "\n\n";
    foreach ($verifyLevels as $level) {
        $spec = eduLevelDepthSpec($level);
        echo str_repeat('=', 72) . "\n";
        echo "coach_level={$level} — {$spec['label']}\n";
        echo str_repeat('=', 72) . "\n";
        echo eduLevelDepthSystemPrompt($level) . "\n\n";
    }
    exit(0);
}

try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL unavailable: {$e->getMessage()}\n");
    fwrite(STDERR, "Run on EC2/Ubuntu with MySQL, or use --dry-run for prompt inspection.\n");
    exit(1);
}

$llm = eduLlm();

foreach ($ids as $newsId) {
    echo str_repeat('=', 72) . "\n";
    echo "LEVEL DEPTH VERIFY ({$phase}) — news_id={$newsId}\n";
    echo 'Levels: ' . implode(',', $verifyLevels) . "\n";
    echo str_repeat('=', 72) . "\n\n";

    $article = eduHingeLoadMysqlContent($pdo, $newsId);
    if ($article === null) {
        fwrite(STDERR, "SKIP: news_id={$newsId} content missing\n\n");
        continue;
    }

    echo 'Title: ' . $article['title'] . "\n";
    echo 'Content chars: ' . mb_strlen($article['content']) . "\n\n";

    $byLevel = [];
    $errors = [];

    foreach ($verifyLevels as $level) {
        $spec = eduLevelDepthSpec($level);
        echo "--- coach_level={$level} ({$spec['label']}) ---\n";
        echo "  spec: {$spec['thinking']} · axes {$spec['axis_min']}-{$spec['axis_max']} · scaffold={$spec['scaffolding']}\n";

        $result = eduLevelDepthExtract(
            $llm,
            $newsId,
            $article['title'],
            $article['content'],
            $level
        );

        if (!$result['ok']) {
            $err = (string) ($result['error'] ?? 'unknown');
            echo "  ERROR: {$err}\n\n";
            $errors[$level] = $err;
            continue;
        }

        $ex = $result['extraction'];
        $byLevel[$level] = $ex;

        echo '  hinge: ' . mb_substr((string) ($ex['hinge'] ?? ''), 0, 80) . "\n";
        if (!empty($ex['side_a'])) {
            echo '  side_a: ' . mb_substr((string) $ex['side_a'], 0, 60) . "\n";
        }
        if (!empty($ex['side_b'])) {
            echo '  side_b: ' . mb_substr((string) $ex['side_b'], 0, 60) . "\n";
        }
        echo '  axes=' . ($ex['axis_count'] ?? 0) . ' confidence=' . ($ex['confidence'] ?? '') . "\n";
        echo '  thinking: ' . ($ex['thinking_summary'] ?? '') . "\n";

        foreach ($ex['axes'] ?? [] as $i => $ax) {
            $n = $i + 1;
            echo "    [{$n}] " . ($ax['point'] ?? '') . "\n";
            echo '        Q: ' . mb_substr((string) ($ax['core_question'] ?? ''), 0, 55) . "\n";
            if (!empty($ax['counter_angle'])) {
                echo '        counter: ' . mb_substr((string) $ax['counter_angle'], 0, 50) . "\n";
            }
        }
        echo "\n";
    }

    if ($byLevel === []) {
        continue;
    }

    $compare = eduLevelDepthCompareSummary($byLevel, $verifyLevels);
    $staircase = count($compare) >= 2 ? eduLevelDepthStaircaseAnalysis($compare) : [];

    echo str_repeat('-', 72) . "\n";
    echo "7-STEP STAIRCASE (자동 — 사람 눈 검수 필수)\n";
    echo str_repeat('-', 72) . "\n";
    printf("%-3s %-10s %6s %5s %5s %7s %12s %6s\n", 'L', 'label', 'hinge', 'side_b', 'axes', 'counter', 'scaffold', 'sc_ax');
    foreach ($compare as $row) {
        printf(
            "%-3s %-10s %6d %5s %5d %7d %12s %6d\n",
            (string) $row['level'],
            mb_substr((string) $row['label'], 0, 10),
            (int) $row['hinge_len'],
            ($row['has_side_b'] ?? false) ? 'Y' : 'N',
            (int) $row['axis_count'],
            (int) $row['counter_axes'],
            mb_substr((string) ($row['scaffolding'] ?? ''), 0, 12),
            (int) ($row['scaffold_axes'] ?? 0)
        );
    }
    echo "\n";

    if ($staircase !== []) {
        echo "STAIRCASE AUTO:\n";
        echo '  hinge_len monotonic: ' . (!empty($staircase['monotonic']['hinge_len']) ? 'OK' : 'FAIL') . "\n";
        echo '  scaffold monotonic: ' . (!empty($staircase['monotonic']['scaffolding_score']) ? 'OK' : 'FAIL') . "\n";
        echo '  staircase_ok: ' . (!empty($staircase['staircase_ok']) ? 'YES' : 'NO') . "\n";
        foreach ($staircase['adjacent'] ?? [] as $row) {
            $flag = ($row['distinct'] ?? false) ? 'OK' : 'BLUR';
            echo "  {$row['pair']}: Δhinge={$row['hinge_delta']} Δaxes={$row['axis_delta']} Δscaffold={$row['scaffold_delta']} [{$flag}]\n";
        }
        foreach ($staircase['warnings'] ?? [] as $w) {
            echo "  WARN: {$w}\n";
        }
        echo "\n";
    }

    echo "HUMAN CHECK (이원근):\n";
    if ($phase === 'phase3') {
        echo "  · 1→7 계단 매끄러운가? (hinge/축/비계)\n";
        echo "  · 2 vs 3, 5 vs 6 구분되나?\n";
        echo "  · 흐릿하면 → 7단→5단 재설계\n\n";
    } else {
        echo "  · level 1 단순 / 4 중간 / 7 깊은가?\n";
        echo "  · 셋이 비슷하면 → 재설계\n\n";
    }

    $payload = [
        'news_id' => $newsId,
        'title' => $article['title'],
        'verified_at' => date('c'),
        'phase' => $phase,
        'prompt_version' => EDU_LEVEL_DEPTH_PROMPT_VERSION,
        'level_order' => $verifyLevels,
        'levels' => $byLevel,
        'compare' => $compare,
        'staircase' => $staircase,
        'errors' => $errors,
    ];

    if (!$stdoutOnly) {
        $jsonPath = eduLevelDepthSaveVerifyResult($payload);
        echo "Wrote {$jsonPath}\n";

        $mdPath = eduLevelDepthVerifyDir() . '/' . $newsId . '.md';
        file_put_contents($mdPath, eduLevelDepthVerifyMarkdown($payload));
        echo "Wrote {$mdPath}\n\n";
    }
}

echo "Done.\n";
