<?php
/**
 * Compose 자동 진단 경로 — env + LLM resolve 프로브 (EC2)
 * php tools/edu_insight_compose_probe.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

echo "=== EDU insight compose path probe ===\n\n";

$flags = [
    'EDU_STRUCTURE_DIAGNOSE_RULE_ONLY' => eduStructureDiagnoseEnv('EDU_STRUCTURE_DIAGNOSE_RULE_ONLY'),
    'EDU_STRUCTURE_DIAGNOSE_LIVE' => eduStructureDiagnoseEnv('EDU_STRUCTURE_DIAGNOSE_LIVE'),
    'OPENAI_API_KEY' => eduStructureDiagnoseEnv('OPENAI_API_KEY') ? '(set)' : '(missing)',
    'EDU_OPENAI_MODEL' => eduStructureDiagnoseEnv('EDU_OPENAI_MODEL') ?: '(default)',
];

foreach ($flags as $k => $v) {
    echo "  {$k} = " . (is_string($v) ? $v : json_encode($v)) . "\n";
}

$insightsFile = $root . '/public/api/edu/lib/eduStudentInsights.php';
$body = is_file($insightsFile) ? (string) file_get_contents($insightsFile) : '';
echo "\nDeployed insights.php:\n";
echo '  ResolveLlm: ' . (str_contains($body, 'eduStructureDiagnoseResolveLlm') ? 'yes' : 'NO') . "\n";
echo '  Phase2 default: ' . (str_contains($body, 'Legacy opt-out only') ? 'yes' : 'OLD (LIVE=1 required?)') . "\n";

$llm = eduStructureDiagnoseResolveLlm();
echo "\nResolveLlm(): " . ($llm !== null ? 'LLM client OK' : 'NULL → rule fallback') . "\n";

if ($llm !== null) {
    echo "  model: " . (method_exists($llm, 'getModel') ? $llm->getModel() : '?') . "\n";
    echo "  remaining: " . (method_exists($llm, 'getRemainingCalls') ? $llm->getRemainingCalls() : '?') . "\n";
}

echo "\nNext: new complete → insights_list should show mode=llm ver=p2-phase2-llm-v1\n";
echo "If mode=rule_fallback, check storage/logs/edu_llm.log and php-fpm error log for rule_fallback reason.\n";
