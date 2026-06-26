<?php
/**
 * EDU Level Depth Phase 1 — static checks (no MySQL/LLM)
 * php tools/edu_level_depth_verify_static.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function check(bool $ok, string $label): void
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

function read(string $rel): string
{
    global $root;
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string) file_get_contents($path) : '';
}

$lib = read('public/api/edu/lib/eduLevelDepthExtract.php');
$tool = read('tools/edu_level_depth_verify.php');

check(str_contains($lib, 'eduLevelDepthExtract'), 'lib: extract function');
check(str_contains($lib, 'EDU_LEVEL_DEPTH_VERIFY_LEVELS'), 'lib: levels 1/4/7');
check(str_contains($lib, 'single_question'), 'lib: level 1 hinge mode');
check(str_contains($lib, 'dual_sided'), 'lib: level 4 hinge mode');
check(str_contains($lib, 'multi_layer'), 'lib: level 7 hinge mode');
check(str_contains($lib, 'counter_angle'), 'lib: counter_angle field');
check(str_contains($lib, 'eduLevelDepthCompareSummary'), 'lib: compare summary');

check(str_contains($tool, 'edu_level_depth_verify.php'), 'tool: exists');
check(str_contains($tool, '--dry-run'), 'tool: dry-run flag');
check(str_contains($tool, 'eduLevelDepthVerifyLevels'), 'tool: uses verify levels');
check(str_contains($tool, 'HUMAN CHECK'), 'tool: human review prompt');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
