<?php
/**
 * P2-A2 — 경첩 JSON → 최소 퀘스트 데이터 + (630) 수동 대조
 *
 * Usage:
 *   php tools/edu_hinge_quest_map.php 630
 *   php tools/edu_hinge_quest_map.php docs/hinge_extractions/630.json
 *   php tools/edu_hinge_quest_map.php 630 --write
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';
require_once $root . '/public/api/edu/lib/eduHingeQuestMap.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

$write = in_array('--write', $argv ?? [], true);
$arg = $argv[1] ?? '630';

if (str_ends_with($arg, '.json')) {
    $path = str_starts_with($arg, '/') || preg_match('#^[A-Za-z]:#', $arg) ? $arg : $root . '/' . $arg;
    $hinge = json_decode((string) file_get_contents($path), true);
} else {
    $newsId = (int) $arg;
    $hinge = eduHingeLoadExtraction($newsId);
    $path = eduHingeExtractionPath($newsId);
}

if (!is_array($hinge) || empty($hinge['news_id'])) {
    fwrite(STDERR, "Hinge JSON not found or invalid: {$arg}\n");
    exit(1);
}

$auto = eduHingeMapToMinQuest($hinge);
$newsId = (int) $auto['quest_code'] ? (int) ($hinge['news_id']) : 0;

echo "=== P2-A2 Hinge → Min Quest ===\n";
echo "Source: {$path}\n";
echo "Quest: {$auto['quest_code']}\n\n";
echo json_encode($auto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

$compare = null;
if ((int) ($hinge['news_id'] ?? 0) === 630) {
    $manual = eduNuke630QuestFixture();
    $compare = eduHingeCompare630Heart($auto, $manual);

    echo "=== 630 Heart Compare (vs Q-NUKE-AXIS-630) ===\n\n";
    echo "| 필드 | 판정 | 수동 | 자동 |\n";
    echo "|------|------|------|------|\n";
    foreach ($compare['rows'] as $row) {
        $m = str_replace('|', '\\|', mb_substr($row['manual'], 0, 60));
        $a = str_replace('|', '\\|', mb_substr($row['auto'], 0, 60));
        echo "| {$row['field']} | **{$row['verdict']}** | {$m} | {$a} |\n";
    }
    echo "\n**A2 심장 통과:** " . ($compare['pass'] ? 'YES ○' : 'NO ✗') . "\n\n";
}

if ($write) {
    eduHingeEnsureDirs();
    $outDir = eduHingeProjectRoot() . '/docs/hinge_quest_drafts';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }
    $outJson = $outDir . '/AUTO-' . (int) $hinge['news_id'] . '-min.json';
    file_put_contents($outJson, json_encode($auto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    echo "Wrote {$outJson}\n";

    if ($compare !== null) {
        $md = eduHingeA2CompareMarkdown($hinge, $auto, $manual, $compare);
        $mdPath = eduHingeProjectRoot() . '/docs/P2_HINGE_A2_630_COMPARE.md';
        file_put_contents($mdPath, $md);
        echo "Wrote {$mdPath}\n";
    }
}

/**
 * @param array<string, mixed> $hinge
 * @param array<string, mixed> $auto
 * @param array<string, mixed> $manual
 * @param array{pass: bool, rows: list<array<string, string>>, notes: list<string>} $compare
 */
function eduHingeA2CompareMarkdown(array $hinge, array $auto, array $manual, array $compare): string
{
    $md = "# P2-A2 — 630 자동 vs 수동 대조\n\n";
    $md .= '> ' . date('Y-m-d H:i:s') . " · mapper p2-a2-v1\n\n";
    $md .= "## A2 판정: **" . ($compare['pass'] ? '통과 ○' : '미통과 ✗') . "** (심장: hook + shared + shake)\n\n";

    $md .= "### 자동 생성 (`AUTO-630-min`)\n\n";
    $hints = $auto['hammer_hints'];
    $md .= "- hook_short: " . ($hints['hook_short'] ?? '') . "\n";
    $md .= "- shared_conclusion: " . ($hints['shared_conclusion'] ?? '') . "\n";
    $md .= "- shake: " . ($hints['_hinge']['shake_prompt'] ?? '') . "\n";
    $md .= "- mode: " . ($hints['mode'] ?? '') . " · quest_frame: " . ($hints['quest_frame'] ?? '') . "\n\n";

    $md .= "### 수동 (`Q-NUKE-AXIS-630`)\n\n";
    $mh = $manual['hammer_hints'];
    $md .= "- hook_short: " . ($mh['hook_short'] ?? '') . "\n";
    $md .= "- shared_conclusion: " . ($mh['shared_conclusion'] ?? '') . "\n";
    $md .= "- mode: convergent · axes: 3\n\n";

    $md .= "### 대조표\n\n";
    $md .= "| 필드 | 판정 | 수동 | 자동 |\n|------|------|------|------|\n";
    foreach ($compare['rows'] as $row) {
        $md .= '| ' . $row['field'] . ' | ' . $row['verdict'] . ' | '
            . str_replace('|', '\\|', $row['manual']) . ' | '
            . str_replace('|', '\\|', $row['auto']) . " |\n";
    }

    $md .= "\n### 의도된 차이 (1단계)\n\n";
    $md .= "- axes / counter_map / convergent — 2단계\n";
    $md .= "- quest_title 한국화, pro/con_line, context 기사 — 수동/2단계\n";
    $md .= "- hook_full 서사 확장 — 수동이 더 김\n\n";

    return $md;
}
