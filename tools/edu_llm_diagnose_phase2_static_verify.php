<?php
/**
 * Phase 2 LLM diagnose — static checks (no LLM/DB)
 * php tools/edu_llm_diagnose_phase2_static_verify.php
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

$diag = read('public/api/edu/lib/eduStructureDiagnose.php');
$insights = read('public/api/edu/lib/eduStudentInsights.php');
$compose = read('public/api/edu/session/compose.php');
$migration = read('database/migrations/edu_student_insights_level.sql');

check(str_contains($diag, 'p2-phase2-llm-v1'), 'diagnose: phase2 version');
check(str_contains($diag, 'exploration_depth_level'), 'diagnose: level field');
check(str_contains($diag, 'eduStructureDiagnoseRuleFallbackLevel'), 'diagnose: rule fallback level');
check(str_contains($diag, '$llm->chat('), 'diagnose: primary model chat() not haiku');
check(str_contains($diag, 'rule fallback'), 'diagnose: fallback on llm error path');
check(str_contains($diag, 'side_a·side_b'), 'diagnose: tension rubric');

check(str_contains($insights, 'eduStructureDiagnoseResolveLlm'), 'insights: resolve llm default');
check(str_contains($insights, 'EDU_STRUCTURE_DIAGNOSE_RULE_ONLY'), 'insights: rule-only rollback env');
check(str_contains($insights, 'exploration_depth_level'), 'insights: level column in row');

check(str_contains($compose, 'eduSaveStructureInsight'), 'compose: insight hook preserved');
check(str_contains($compose, 'catch (Throwable $insightErr)'), 'compose: fail-safe insight');

check(str_contains($migration, 'exploration_depth_level'), 'migration: level column');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
