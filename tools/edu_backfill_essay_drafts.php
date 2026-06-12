<?php
/**
 * GIST EDU — backfill edu_writing_drafts.full_text from blueprint essay_artifact
 *
 * Usage:
 *   php tools/edu_backfill_essay_drafts.php --dry-run
 *   php tools/edu_backfill_essay_drafts.php
 *   php tools/edu_backfill_essay_drafts.php --session-id=UUID
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduDraftStorage.php';

$dryRun = in_array('--dry-run', $argv, true);
$sessionFilter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--session-id=')) {
        $sessionFilter = trim(substr($arg, strlen('--session-id=')));
    }
}

$supabase = eduSupabase();
if (!$dryRun && !$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

$schema = eduProbeDraftStorageSchema($supabase);
if (!$schema['drafts_full_text']) {
    fwrite(STDERR, "full_text column missing. Run database/migrations/edu_essay_artifact.sql first.\n");
    exit(2);
}

$query = 'stage=eq.completed&select=id,student_id,blueprint_json,hammer_payload';
if ($sessionFilter !== null && $sessionFilter !== '') {
    $query .= '&id=eq.' . $sessionFilter;
}

$sessions = $supabase->select('edu_quest_sessions', $query, 500);
if ($sessions === null) {
    fwrite(STDERR, 'Failed to load sessions: ' . $supabase->getLastError() . "\n");
    exit(1);
}

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($sessions as $session) {
    $sessionId = (string) ($session['id'] ?? '');
    $studentId = (string) ($session['student_id'] ?? '');
    if ($sessionId === '' || $studentId === '') {
        $skipped++;
        continue;
    }

    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $existing = $drafts[0] ?? null;
    $existingFull = trim((string) ($existing['full_text'] ?? ''));
    if ($existingFull !== '') {
        $skipped++;
        continue;
    }

    $blueprint = eduLoadBlueprint($session);
    $artifact = $blueprint['essay_artifact'] ?? [];
    if (!is_array($artifact)) {
        $artifact = [];
    }

    $title = trim((string) ($artifact['title'] ?? ''));
    $subtitle = trim((string) ($artifact['subtitle'] ?? ''));
    $sections = is_array($artifact['sections'] ?? null) ? $artifact['sections'] : [];
    $conclusionHeading = trim((string) ($artifact['conclusion_heading'] ?? '결론'));
    $conclusionParagraphs = is_array($artifact['conclusion_paragraphs'] ?? null)
        ? $artifact['conclusion_paragraphs']
        : [];

    $fullText = trim((string) ($artifact['full_text'] ?? ''));
    if ($fullText === '' && $sections !== []) {
        $fullText = eduBuildEssayFullText($title, $subtitle, $sections, $conclusionHeading, $conclusionParagraphs);
    }

    // Legacy: 5문장 SCQA (v2_sentences / writing_versions)
    if ($fullText === '') {
        $v2 = $existing['v2_sentences'] ?? [];
        if (is_string($v2)) {
            $v2 = json_decode($v2, true) ?: [];
        }
        if (is_array($v2) && $v2 !== []) {
            $fullText = implode("\n\n", array_filter(array_map('strval', $v2)));
        }
    }
    if ($fullText === '') {
        $versions = $supabase->select(
            'edu_writing_versions',
            'session_id=eq.' . $sessionId . '&order=version.desc',
            1
        );
        $v = $versions[0] ?? [];
        if ($v !== []) {
            $parts = array_filter([
                $v['scqa_situation'] ?? '',
                $v['scqa_complication'] ?? '',
                $v['scqa_question'] ?? '',
                $v['scqa_answer'] ?? '',
                $v['conclusion'] ?? '',
            ], static fn ($p) => trim((string) $p) !== '');
            if ($parts !== []) {
                $fullText = implode("\n\n", $parts);
            }
        }
    }

    if ($fullText === '' && $sections === []) {
        $skipped++;
        fwrite(STDERR, "skip session={$sessionId} (no recoverable essay text)\n");
        continue;
    }

    $structureDiagram = $blueprint['essay_structure'] ?? [];
    if (!is_array($structureDiagram)) {
        $structureDiagram = [];
    }

    $essayStructure = $existing['essay_structure'] ?? [];
    if (is_string($essayStructure)) {
        $essayStructure = json_decode($essayStructure, true) ?: [];
    }
    if (!is_array($essayStructure)) {
        $essayStructure = [];
    }
    if (empty($essayStructure['structure']) && $structureDiagram !== []) {
        $essayStructure['structure'] = $structureDiagram;
    }

    $hero = trim((string) ($artifact['hero_sentence'] ?? ($existing['hero_sentence'] ?? '')));
    if ($hero === '' && $fullText !== '') {
        $hero = mb_substr($fullText, 0, 80);
    }

    $payload = [
        'full_text' => $fullText,
        'essay_structure' => [
            'title' => $title,
            'subtitle' => $subtitle,
            'structure' => $essayStructure['structure'] ?? $structureDiagram,
            'sections' => $sections,
            'conclusion_heading' => $conclusionHeading !== '' ? $conclusionHeading : '결론',
            'conclusion_paragraphs' => $conclusionParagraphs,
        ],
        'hero_sentence' => $hero,
        'updated_at' => date('c'),
    ];

    if ($existing === null && $fullText !== '') {
        $payload['v1_sentences'] = $payload['v1_sentences'] ?? [$fullText];
        $payload['v2_sentences'] = $payload['v2_sentences'] ?? [$fullText];
    }

    if ($dryRun) {
        echo "[dry-run] backfill session={$sessionId} chars=" . mb_strlen($fullText) . "\n";
        $updated++;
        continue;
    }

    $result = eduSaveWritingDraft($supabase, $sessionId, $studentId, $payload, $existing, 'backfill');
    if ($result['ok']) {
        echo "backfilled session={$sessionId}" . ($result['used_fallback'] ? ' (fallback)' : '') . "\n";
        $updated++;
    } else {
        fwrite(STDERR, "failed session={$sessionId} error={$result['error']}\n");
        $errors++;
    }
}

echo "Done: updated={$updated} skipped={$skipped} errors={$errors}" . ($dryRun ? ' (dry-run)' : '') . "\n";
exit($errors > 0 ? 1 : 0);
