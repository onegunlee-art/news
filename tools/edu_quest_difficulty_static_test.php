<?php
/**
 * eduQuestDifficulty — L1~L5 판정 정적 회귀
 *
 * Usage: php tools/edu_quest_difficulty_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficulty.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficultyLlm.php';
require_once $root . '/public/api/edu/lib/eduCoachLevel.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

echo "=== eduQuestDifficulty static test ===\n\n";

$labels = eduQuestDifficultyLabel(EDU_COACH_LEVEL_L3);
ok('L3 label matches coach', ($labels['ko'] ?? '') === '논객');

$sampleRows = [];
for ($i = 1; $i <= 10; $i++) {
    $sampleRows[] = ['quest_code' => 'Q-TEST-' . $i, 'difficulty_score' => $i * 10];
}
$qMap = eduQuestDifficultyQuantileLevels($sampleRows);
ok('quantile maps 10 items to 5 levels', count(array_unique(array_values($qMap))) >= 4);

$gatePass = eduQuestDifficultyDistributionGate([1 => 10, 2 => 9, 3 => 8, 4 => 9, 5 => 10], 46);
ok('gate pass when L1=10 L3=8/46', ($gatePass['pass'] ?? false) === true);

$gateFail = eduQuestDifficultyDistributionGate([1 => 0, 2 => 4, 3 => 32, 4 => 5, 5 => 5], 46);
ok('gate fail when L1=0 L3=70%', ($gateFail['pass'] ?? false) === false);

$anchors = eduQuestDifficultyAnchorChecks([
    ['quest_code' => 'Q-AUTO-DC-150', 'quest_title' => '전기세', 'difficulty_level' => 2],
    ['quest_code' => 'Q-X', 'quest_title' => '재귀적 자기개선(RSI)', 'difficulty_level' => 5],
]);
ok('anchor checks include 전기세 pass', str_contains(implode(' ', $anchors), 'PASS 전기세'));
ok('anchor checks include RSI pass', str_contains(implode(' ', $anchors), 'PASS RSI'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
