<?php
/**
 * GIST EDU — EduQuestFactory 수렴형 추출 격리 테스트 (Phase 2b)
 *
 * Usage:
 *   php tools/edu_quest_factory_convergent_test.php [--arc=ARC-IRAN-REGION] [--dry-run]
 *   php tools/edu_quest_factory_convergent_test.php --live
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/agents/autoload.php';
require_once $projectRoot . '/public/api/edu/lib/bootstrap.php';
require_once $projectRoot . '/public/api/edu/lib/_llm.php';
require_once $projectRoot . '/public/api/edu/lib/eduMysql.php';

use Services\Edu\EduQuestFactory;

$arc = 'ARC-IRAN-REGION';
$dryRun = true;
$live = false;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--arc=')) {
        $arc = substr($arg, 6);
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
    if ($arg === '--live') {
        $live = true;
        $dryRun = false;
    }
}

echo "=== EduQuestFactory Convergent Test (Phase 2b) ===\n";
echo "arc: {$arc}\n";
echo "mode: " . ($live ? 'LIVE persist' : 'dry-run only') . "\n\n";

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$pdo = eduMysql();
$llm = eduLlm();
$factory = new EduQuestFactory($pdo, $supabase, $llm);

$candidates = $factory->discoverCandidates(3, 120);
$target = null;
foreach ($candidates as $c) {
    if (($c['manual_arc'] ?? '') === $arc) {
        $target = $c;
        break;
    }
}

if ($target === null) {
    echo "No candidate for arc {$arc}. Found arcs: ";
    echo implode(', ', array_map(fn($c) => $c['manual_arc'] ?? '?', $candidates)) . "\n";
    exit(1);
}

$mode = $target['hammer_hints']['mode'] ?? 'adversarial';
echo "quest_code: {$target['quest_code']}\n";
echo "quest_title: {$target['quest_title']}\n";
echo "hammer_hints.mode: {$mode}\n";

if ($mode === 'convergent') {
    $hints = $target['hammer_hints'];
    echo "shared_conclusion: " . ($hints['shared_conclusion'] ?? '') . "\n";
    echo "axes: " . count($hints['axes'] ?? []) . "\n";
    foreach ($hints['axes'] ?? [] as $ax) {
        echo "  - {$ax['axis_id']}: {$ax['axis_label']} (news_id={$ax['news_id']})\n";
    }
    echo "counter_map: " . json_encode($hints['counter_map'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "WARN: convergent mode not selected — fell back to adversarial\n";
}

if ($live && $mode === 'convergent') {
    $result = $factory->persistDraft($target, false);
    echo "\npersist: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== DONE ===\n";
exit($mode === 'convergent' ? 0 : 2);
