<?php
/**
 * P2-B 2단계 — 구조 진단 결과 edu_student_insights 저장 (내부 전용)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduBlueprint.php';
require_once __DIR__ . '/eduStructureDiagnose.php';

/**
 * compose/백필에서 LLM 보강 여부 (기본 OFF — rule_fallback, 속도 우선)
 */
function eduStructureDiagnoseOptionalLlm()
{
    $live = getenv('EDU_STRUCTURE_DIAGNOSE_LIVE');
    if ($live === '1' || $live === 'true') {
        require_once __DIR__ . '/_llm.php';
        return eduLlm();
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $axesCovered
 * @return array{engaged: int, total: int}
 */
function eduStructureInsightAxisCounts(array $axesCovered): array
{
    $total = count($axesCovered);
    $engaged = 0;
    foreach ($axesCovered as $axis) {
        if (!empty($axis['covered'])) {
            $engaged++;
        }
    }

    return ['engaged' => $engaged, 'total' => $total];
}

/**
 * @param array<string, mixed> $diag
 * @return array<string, mixed>
 */
function eduStructureInsightRowFromDiagnose(string $studentId, array $diag): array
{
    $axesCovered = is_array($diag['axes_covered'] ?? null) ? $diag['axes_covered'] : [];
    $counts = eduStructureInsightAxisCounts($axesCovered);
    $sessionId = (string) ($diag['session_id'] ?? '');

    return [
        'student_id' => $studentId,
        'session_id' => $sessionId,
        'quest_code' => (string) ($diag['quest_code'] ?? ''),
        'axes_engaged_count' => $counts['engaged'],
        'axes_total' => $counts['total'],
        'tension_engaged' => (string) ($diag['tension_engaged'] ?? ''),
        'conclusion_clarity' => (string) ($diag['conclusion_clarity'] ?? ''),
        'evidence_linked' => (string) ($diag['evidence_linked'] ?? ''),
        'structure_note' => (string) ($diag['structure_note'] ?? ''),
        'diagnose_version' => (string) ($diag['diagnose_version'] ?? EDU_STRUCTURE_DIAGNOSE_VERSION),
        'diagnose_mode' => (string) ($diag['diagnose_mode'] ?? 'rule_fallback'),
        'diagnose_json' => $diag,
        'internal_only' => true,
    ];
}

function eduStructureInsightExists(\Agents\Services\SupabaseService $sb, string $sessionId): bool
{
    if ($sessionId === '') {
        return false;
    }
    $rows = $sb->select('edu_student_insights', 'session_id=eq.' . $sessionId . '&select=id', 1);

    return !empty($rows[0]['id']);
}

/**
 * @param array<string, mixed> $session edu_quest_sessions row (blueprint_json/dialogue_json 포함 가능)
 * @param array<string, mixed> $quest edu_daily_quests row (quest_code 포함)
 */
function eduStructureInsightEssayText(\Agents\Services\SupabaseService $sb, array $session, string $essayTextOverride = ''): string
{
    $essayText = trim($essayTextOverride);
    if ($essayText !== '') {
        return $essayText;
    }
    if (($session['stage'] ?? '') !== 'completed') {
        return '';
    }
    $sid = (string) ($session['id'] ?? '');
    if ($sid === '') {
        return '';
    }
    $drafts = $sb->select('edu_writing_drafts', 'session_id=eq.' . $sid, 1);

    return trim((string) ($drafts[0]['full_text'] ?? ''));
}

/**
 * @param array<string, mixed> $session
 * @param array<string, mixed> $quest
 * @return array<string, mixed>|null inserted row or null on skip/failure
 */
function eduSaveStructureInsight(
    \Agents\Services\SupabaseService $sb,
    array $session,
    array $quest,
    $llm = null,
    string $essayTextOverride = ''
): ?array {
    $sessionId = (string) ($session['id'] ?? '');
    $studentId = (string) ($session['student_id'] ?? '');
    if ($sessionId === '' || $studentId === '') {
        return null;
    }
    if (eduStructureInsightExists($sb, $sessionId)) {
        return null;
    }

    $blueprint = eduLoadBlueprint($session);
    $dialogue = eduLoadDialogue($session, true);
    $essayText = eduStructureInsightEssayText($sb, $session, $essayTextOverride);

    $diag = eduStructureDiagnoseSession($sessionId, $quest, $blueprint, $dialogue, $llm, $essayText);
    $row = eduStructureInsightRowFromDiagnose($studentId, $diag);

    $completedAt = trim((string) ($session['completed_at'] ?? ''));
    if ($completedAt !== '') {
        $row['diagnosed_at'] = $completedAt;
    }

    $inserted = $sb->insert('edu_student_insights', $row);
    if ($inserted === null || empty($inserted[0])) {
        error_log('edu_student_insights insert failed: ' . $sb->getLastError());

        return null;
    }

    return $inserted[0];
}

/**
 * @return list<array<string, mixed>>
 */
function eduListStudentInsights(\Agents\Services\SupabaseService $sb, string $studentId, int $limit = 50): array
{
    if ($studentId === '') {
        return [];
    }
    $rows = $sb->select(
        'edu_student_insights',
        'student_id=eq.' . $studentId . '&order=diagnosed_at.asc',
        max(1, min($limit, 200))
    );

    return is_array($rows) ? $rows : [];
}
