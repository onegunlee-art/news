<?php
/**
 * evidence nudge — quest-aware examples (no global nuke hardcode)
 *
 * Usage: php tools/edu_evidence_nudge_examples_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}" . ($detail !== '' ? "\n  → {$detail}\n" : "\n");
        $pass++;
        return;
    }
    echo "FAIL {$label}\n  → {$detail}\n";
    $fail++;
}

$semi = [
    'quest_code' => 'Q-SEMI-TEST',
    'hammer_hints' => ['mode' => 'convergent', 'quest_frame' => 'decision_inquiry'],
    'articles' => [
        ['title' => '삼성전자 호황'],
        ['title' => '유가 급등과 공급망 충격'],
        ['title' => '동북아 반도체 집중 정책'],
    ],
];

$nuke = eduNuke630QuestFixture();
$nuke['articles'] = eduNuke630QuestArticles();

echo "=== evidence nudge examples (regression) ===\n\n";

$semiMsg = eduBuildEvidenceNudgeMessage($semi);
ok('semi uses article titles', str_contains($semiMsg, '삼성전자'), $semiMsg);
ok('semi not nuke hardcode', !str_contains($semiMsg, '드론 공격') && !str_contains($semiMsg, '한국 핵무장'), $semiMsg);

$nukeMsg = eduBuildEvidenceNudgeMessage($nuke);
ok('nuke not global hardcode', !str_contains($nukeMsg, '인도·파키스탄'), $nukeMsg);
ok('nuke has quest-specific hint', str_contains($nukeMsg, '핵') || str_contains($nukeMsg, '군사'), $nukeMsg);

$empty = ['quest_code' => 'Q-EMPTY', 'hammer_hints' => [], 'articles' => []];
$emptyMsg = eduBuildEvidenceNudgeMessage($empty);
ok('empty quest generic fallback', str_contains($emptyMsg, '기사 제목'), $emptyMsg);
ok('empty not nuke hardcode', !str_contains($emptyMsg, '드론 공격'), $emptyMsg);

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
