<?php
/**
 * EDU 7단계 Phase 1 — 레vel 1/4/7 구조 깊이 검증 (프로덕션 무관)
 *
 * Usage:
 *   php tools/edu_level_depth_verify.php
 *   php tools/edu_level_depth_verify.php 630
 *   php tools/edu_level_depth_verify.php 630 150 196
 *   php tools/edu_level_depth_verify.php 630 --stdout-only
 *   php tools/edu_level_depth_verify.php 630 --dry-run   (프롬프트만 출력, LLM 호출 없음)
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
$numericArgs = array_values(array_filter($argv, static fn ($a) => is_numeric($a)));
$ids = $numericArgs !== [] ? array_map('intval', $numericArgs) : [630];

if ($dryRun) {
    echo "DRY RUN — prompts only (no MySQL, no LLM)\n\n";
    foreach (eduLevelDepthVerifyLevels() as $level) {
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
    echo "LEVEL DEPTH VERIFY — news_id={$newsId}\n";
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

    foreach (eduLevelDepthVerifyLevels() as $level) {
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

    $compare = eduLevelDepthCompareSummary($byLevel);

    echo str_repeat('-', 72) . "\n";
    echo "COMPARE (자동 요약 — 사람 눈 검수 필수)\n";
    echo str_repeat('-', 72) . "\n";
    printf("%-6s %-14s %6s %6s %5s %7s\n", 'level', 'label', 'hinge', 'side_b', 'axes', 'counter');
    foreach ($compare as $row) {
        printf(
            "%-6s %-14s %6d %6s %5d %7d\n",
            (string) $row['level'],
            mb_substr((string) $row['label'], 0, 14),
            (int) $row['hinge_len'],
            ($row['has_side_b'] ?? false) ? 'Y' : 'N',
            (int) $row['axis_count'],
            (int) $row['counter_axes']
        );
    }
    echo "\n";

    echo "HUMAN CHECK:\n";
    echo "  · level 1 단순한가? (질문 한 층, 1~2축)\n";
    echo "  · level 4 중간인가? (양면, 3축)\n";
    echo "  · level 7 깊은가? (다층, counter_angle)\n";
    echo "  · 셋이 비슷하면 → 7단계 재설계\n\n";

    $payload = [
        'news_id' => $newsId,
        'title' => $article['title'],
        'verified_at' => date('c'),
        'prompt_version' => EDU_LEVEL_DEPTH_PROMPT_VERSION,
        'levels' => $byLevel,
        'compare' => $compare,
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
