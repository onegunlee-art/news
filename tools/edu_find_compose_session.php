<?php
/**
 * Find recent EDU sessions matching compose failure pattern + dump blueprint
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$needle = $argv[1] ?? '여론';
$limit = 30;

$sessions = $supabase->select(
    'edu_quest_sessions',
    'order=started_at.desc',
    $limit
) ?? [];

echo "=== Recent sessions (needle={$needle}) ===\n\n";

foreach ($sessions as $s) {
    $bp = $s['blueprint_json'] ?? [];
    if (is_string($bp)) {
        $bp = json_decode($bp, true) ?: [];
    }
    $stance = (string) ($bp['stance'] ?? $bp['final_stance'] ?? '');
    $evidence = (string) ($bp['evidence'] ?? '');
    $reason = (string) ($bp['reason'] ?? '');
    $phase = (string) ($bp['phase'] ?? '');
    $essayStruct = $bp['essay_structure'] ?? null;
    $ready = !empty($bp['ready_for_compose']);
    $hasStruct = is_array($essayStruct) && !empty($essayStruct['sections']);

    $hay = $evidence . $reason . json_encode($bp, JSON_UNESCAPED_UNICODE);
    if ($needle !== '' && !str_contains($hay, $needle) && $needle !== '*') {
        continue;
    }

    echo "session_id: " . ($s['id'] ?? '') . "\n";
    echo "  started: " . ($s['started_at'] ?? '') . " stage=" . ($s['stage'] ?? '') . "\n";
    echo "  stance={$stance} phase={$phase} ready_for_compose=" . ($ready ? 'Y' : 'N') . " essay_structure=" . ($hasStruct ? 'Y' : 'N') . "\n";
    echo "  reason: " . mb_substr($reason, 0, 80) . "\n";
    echo "  evidence: " . mb_substr($evidence, 0, 120) . "\n";
    if ($hasStruct) {
        echo "  structure title: " . ($essayStruct['title'] ?? '') . "\n";
    } elseif ($ready && !$hasStruct) {
        echo "  >>> COMPOSE STRUCTURE MISSING (Step1 likely failed at confirm_reflection)\n";
    }
    echo "\n";
}
