<?php
/**
 * GIST EDU — verify Supabase schema + count completed sessions missing full_text
 *
 * Usage:
 *   php tools/edu_verify_schema.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduDraftStorage.php';

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

$schema = eduProbeDraftStorageSchema($supabase);

echo "=== EDU schema probe ===\n";
foreach ($schema as $key => $value) {
    $label = is_bool($value) ? ($value ? 'ok' : 'MISSING') : (string) $value;
    echo "  {$key}: {$label}\n";
}

$sessions = $supabase->select('edu_quest_sessions', 'stage=eq.completed&select=id,student_id,blueprint_json', 500);
if ($sessions === null) {
    fwrite(STDERR, "Failed to load completed sessions: " . $supabase->getLastError() . "\n");
    exit(1);
}

$missingFullText = 0;
$backfillCandidates = 0;

foreach ($sessions as $session) {
    $sessionId = (string) ($session['id'] ?? '');
    if ($sessionId === '') {
        continue;
    }

    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId . '&select=full_text,essay_structure', 1);
    $draft = $drafts[0] ?? [];
    $fullText = trim((string) ($draft['full_text'] ?? ''));

    if ($fullText !== '') {
        continue;
    }

    $missingFullText++;

    $blueprint = $session['blueprint_json'] ?? [];
    if (is_string($blueprint)) {
        $blueprint = json_decode($blueprint, true) ?: [];
    }
    $artifact = $blueprint['essay_artifact'] ?? [];
    if (!is_array($artifact)) {
        $artifact = [];
    }

    $artifactText = trim((string) ($artifact['full_text'] ?? ''));
    $hasSections = is_array($artifact['sections'] ?? null) && ($artifact['sections'] ?? []) !== [];

    if ($artifactText !== '' || $hasSections) {
        $backfillCandidates++;
    }
}

echo "\n=== Completed sessions ===\n";
echo "  total_completed: " . count($sessions) . "\n";
echo "  missing_full_text: {$missingFullText}\n";
echo "  backfill_candidates: {$backfillCandidates}\n";

if (!$schema['drafts_full_text'] || !$schema['sessions_blueprint_json']) {
    echo "\nACTION: Run migrations in Supabase SQL Editor (in order):\n";
    echo "  1. database/migrations/add_edu_tables.sql\n";
    echo "  2. database/migrations/edu_pilot_001.sql\n";
    echo "  3. database/migrations/edu_chat_engine.sql\n";
    echo "  4. database/migrations/edu_essay_artifact.sql\n";
    echo "  5. database/migrations/edu_storage_hardening.sql\n";
    exit(2);
}

if ($backfillCandidates > 0) {
    echo "\nACTION: php tools/edu_backfill_essay_drafts.php --dry-run\n";
    echo "         php tools/edu_backfill_essay_drafts.php\n";
    exit(3);
}

echo "\nOK: schema ready, no backfill candidates.\n";
exit(0);
