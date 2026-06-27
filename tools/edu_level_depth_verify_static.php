<?php
/**
 * EDU Level Depth — static checks (no MySQL/LLM)
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
check(str_contains($lib, 'EDU_LEVEL_DEPTH_VERIFY_LEVELS = [1, 2, 3, 4, 5]'), 'lib: default levels 1-5');
check(str_contains($lib, 'level-depth-verify-v3-5step'), 'lib: 5step prompt version');
check(str_contains($lib, 'dual_intro'), 'lib: L2 hinge mode');
check(str_contains($lib, 'dual_sided'), 'lib: L3 hinge mode');
check(str_contains($lib, 'evidence_multi_layer'), 'lib: L4 hinge mode');
check(str_contains($lib, 'single_question'), 'lib: L1 hinge mode');
check(str_contains($lib, 'multi_layer'), 'lib: L5 hinge mode');
check(str_contains($lib, "'1→2', '2→3', '3→4', '4→5'"), 'lib: 5step critical pairs');
check(str_contains($lib, 'eduLevelDepthStaircaseAnalysis'), 'lib: staircase analysis');
check(str_contains($lib, 'eduLevelDepthCompareSummary'), 'lib: compare summary');
check(str_contains($lib, 'phase5'), 'lib: phase5 markdown');

check(str_contains($tool, 'edu_level_depth_verify.php'), 'tool: exists');
check(str_contains($tool, '--dry-run'), 'tool: dry-run flag');
check(str_contains($tool, '--levels='), 'tool: levels override');
check(str_contains($tool, 'STAIRCASE'), 'tool: staircase output');
check(str_contains($tool, 'phase5'), 'tool: phase5 detection');
check(str_contains($tool, 'HUMAN CHECK'), 'tool: human review prompt');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
