<?php
/**
 * B-1 — coach level badge labels + debug gate (static)
 *
 * Usage: php tools/edu_coach_level_badge_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduCoachLevel.php';
require_once $root . '/public/api/edu/lib/eduConfig.php';

$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

$l1 = eduCoachLevelProfilePayload(['coach_level' => 1]);
assertTrue('L1 label ko 관찰자', $l1['label_ko'] === '관찰자');
assertTrue('L5 label ko 칼럼니스트', eduCoachLevelProfilePayload(['coach_level' => 5])['label_ko'] === '칼럼니스트');
assertTrue('legacy 7 → L5', eduCoachLevelProfilePayload(['coach_level' => 7])['coach_level'] === 5);
assertTrue('default missing → L1', eduCoachLevelProfilePayload([])['coach_level'] === 1);

assertTrue('debug off without student', eduLevelDebugAllowed(null) === false);
assertTrue('debug off random student', eduLevelDebugAllowed(['id' => '00000000-0000-0000-0000-000000000099']) === false);

putenv('EDU_LEVEL_DEBUG=1');
assertTrue('EDU_LEVEL_DEBUG=1 allows', eduLevelDebugAllowed(null) === true);
putenv('EDU_LEVEL_DEBUG');

$labels = array_map(
    static fn (int $n) => eduCoachLevelLabels($n)['ko'],
    range(1, 5)
);
assertTrue('five distinct ko labels', count(array_unique($labels)) === 5);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
