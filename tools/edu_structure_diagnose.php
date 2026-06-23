<?php
/**
 * P2-B 1단계 — 학생 글/대화 구조 진단 CLI (내부 전용)
 *
 * Usage:
 *   php tools/edu_structure_diagnose.php --session=UUID
 *   php tools/edu_structure_diagnose.php --session=UUID --live --write --save-insights
 *   php tools/edu_structure_diagnose.php --quest-code=Q-AUTO-NUKE-630 --latest=3
 *   php tools/edu_structure_diagnose.php --fixture=docs/structure_diagnoses/fixture-630-sample.json
 *   php tools/edu_structure_diagnose_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduStructureDiagnose.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$sessionId = '';
$questCode = 'Q-AUTO-NUKE-630';
$fixturePath = '';
$useLive = in_array('--live', $argv ?? [], true);
$write = in_array('--write', $argv ?? [], true);
$saveInsights = in_array('--save-insights', $argv ?? [], true);
$latest = 1;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--session=')) {
        $sessionId = trim(substr($arg, 10));
    }
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = trim(substr($arg, 13));
    }
    if (str_starts_with($arg, '--fixture=')) {
        $fixturePath = trim(substr($arg, 10));
        if (!str_starts_with($fixturePath, '/') && !preg_match('#^[A-Za-z]:#', $fixturePath)) {
            $fixturePath = $root . '/' . $fixturePath;
        }
    }
    if (str_starts_with($arg, '--latest=')) {
        $latest = max(1, (int) substr($arg, 9));
    }
}

$llm = $useLive ? eduLlm() : null;

function runDiagnose(
    string $sessionId,
    array $quest,
    array $blueprint,
    array $dialogue,
    $llm,
    string $essayText = ''
): array {
    return eduStructureDiagnoseSession($sessionId, $quest, $blueprint, $dialogue, $llm, $essayText);
}

if ($fixturePath !== '') {
    if (!is_file($fixturePath)) {
        fwrite(STDERR, "Fixture not found: {$fixturePath}\n");
        exit(1);
    }
    $fixture = json_decode((string) file_get_contents($fixturePath), true);
    if (!is_array($fixture)) {
        fwrite(STDERR, "Invalid fixture JSON\n");
        exit(1);
    }
    $sessionId = (string) ($fixture['session_id'] ?? 'fixture-session');
    $quest = $fixture['quest'] ?? ['quest_code' => 'Q-AUTO-NUKE-630', 'hammer_hints' => []];
    $blueprint = $fixture['blueprint'] ?? [];
    $dialogue = $fixture['dialogue'] ?? [];
    $essay = (string) ($fixture['essay_text'] ?? '');
    $out = runDiagnose($sessionId, $quest, $blueprint, $dialogue, $llm, $essay);
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$targets = [];

if ($sessionId !== '') {
    $row = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1)[0] ?? null;
    if ($row === null) {
        fwrite(STDERR, "Session not found: {$sessionId}\n");
        exit(1);
    }
    $targets[] = $row;
} else {
    $quests = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
    if (empty($quests[0]['id'])) {
        fwrite(STDERR, "Quest not found: {$questCode}\n");
        exit(1);
    }
    $questId = $quests[0]['id'];
    $targets = $supabase->select(
        'edu_quest_sessions',
        'quest_id=eq.' . $questId . '&order=updated_at.desc',
        $latest
    ) ?? [];
    if ($targets === []) {
        fwrite(STDERR, "No sessions for {$questCode}\n");
        exit(1);
    }
    echo "=== Latest {$latest} session(s) for {$questCode} ===\n";
}

$outDir = $root . '/docs/structure_diagnoses';
if ($write && !is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

foreach ($targets as $session) {
    $sid = (string) ($session['id'] ?? '');
    $quests = $supabase->select('edu_daily_quests', 'id=eq.' . ($session['quest_id'] ?? ''), 1);
    $quest = $quests[0] ?? ['quest_code' => $questCode];
    $blueprint = eduLoadBlueprint($session);
    $dialogue = eduLoadDialogue($session, true);

    $essayText = '';
    if (($session['stage'] ?? '') === 'completed') {
        $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sid, 1);
        $essayText = trim((string) ($drafts[0]['full_text'] ?? ''));
    }

    $diag = runDiagnose($sid, $quest, $blueprint, $dialogue, $llm, $essayText);
    echo json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

    if ($write) {
        $path = $outDir . '/' . $sid . '.json';
        file_put_contents($path, json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "Wrote {$path}\n";
    }

    if ($saveInsights) {
        require_once $root . '/public/api/edu/lib/eduStudentInsights.php';
        $saved = eduSaveStructureInsight($supabase, $session, $quest, $llm, $essayText);
        if ($saved !== null) {
            echo "Saved edu_student_insights id=" . ($saved['id'] ?? '') . "\n";
        } elseif (eduStructureInsightExists($supabase, $sid)) {
            echo "Insight already exists for session {$sid}\n";
        } else {
            echo "Insight save failed: " . $supabase->getLastError() . "\n";
        }
    }
}
