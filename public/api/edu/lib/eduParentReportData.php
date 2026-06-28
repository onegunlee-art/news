<?php
/**
 * GIST EDU — 부모 리포트 데이터 집계 (READ edu_* only)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduTier.php';
require_once __DIR__ . '/eduCoachLevel.php';
require_once __DIR__ . '/eduStudentInsights.php';

function eduParentReportGradeLabel(string $gradeBand): string
{
    return match ($gradeBand) {
        'middle' => '중학생',
        'high' => '고등학생',
        default => '학생',
    };
}

/** @return array<string, mixed>|null */
function eduParentReportFetchStudent(\Agents\Services\SupabaseService $sb, string $studentId): ?array
{
    if ($studentId === '') {
        return null;
    }
    $rows = $sb->select('edu_students', 'id=eq.' . $studentId . '&status=eq.active', 1);

    return $rows[0] ?? null;
}

/**
 * @return list<array{session: array<string, mixed>, quest: array<string, mixed>, draft: array<string, mixed>}>
 */
function eduParentReportCompletedSessions(\Agents\Services\SupabaseService $sb, string $studentId): array
{
    $sessions = $sb->select(
        'edu_quest_sessions',
        'student_id=eq.' . $studentId . '&' . eduSessionStageFilterCompleted() . '&order=completed_at.asc',
        100
    ) ?? [];
    if ($sessions === []) {
        return [];
    }

    $questIds = [];
    $sessionIds = [];
    foreach ($sessions as $row) {
        if (!empty($row['quest_id'])) {
            $questIds[(string) $row['quest_id']] = true;
        }
        if (!empty($row['id'])) {
            $sessionIds[] = (string) $row['id'];
        }
    }

    $questMap = [];
    foreach (array_keys($questIds) as $questId) {
        $quests = $sb->select('edu_daily_quests', 'id=eq.' . $questId, 1);
        if (!empty($quests[0])) {
            $questMap[$questId] = $quests[0];
        }
    }

    $draftMap = [];
    foreach ($sessionIds as $sessionId) {
        $drafts = $sb->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
        if (!empty($drafts[0])) {
            $draftMap[$sessionId] = $drafts[0];
        }
    }

    $out = [];
    foreach ($sessions as $session) {
        $sid = (string) ($session['id'] ?? '');
        $qid = (string) ($session['quest_id'] ?? '');
        $out[] = [
            'session' => $session,
            'quest' => $questMap[$qid] ?? [],
            'draft' => $draftMap[$sid] ?? [],
        ];
    }

    return $out;
}

function eduParentReportExtractQuote(array $draft): string
{
    $hero = trim((string) ($draft['hero_sentence'] ?? ''));
    if ($hero !== '') {
        return $hero;
    }
    $full = trim((string) ($draft['full_text'] ?? ''));
    if ($full === '') {
        return '';
    }
    $lines = preg_split('/\R/u', $full) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (mb_strlen($line) >= 12) {
            return $line;
        }
    }

    return mb_substr($full, 0, 120);
}

/** @param list<array{session: array, quest: array, draft: array}> $completed */
function eduParentReportBeforeAfter(array $completed): ?array
{
    if (count($completed) < 2) {
        return null;
    }
    $first = $completed[0];
    $last = $completed[count($completed) - 1];
    $beforeText = eduParentReportExtractQuote($first['draft']);
    $afterText = eduParentReportExtractQuote($last['draft']);
    if ($beforeText === '' || $afterText === '' || $beforeText === $afterText) {
        return null;
    }

    $beforeQuest = (string) ($first['quest']['quest_title'] ?? '첫 탐구');
    $afterQuest = (string) ($last['quest']['quest_title'] ?? '최근 탐구');
    $beforeAt = substr((string) ($first['session']['completed_at'] ?? ''), 0, 10);
    $afterAt = substr((string) ($last['session']['completed_at'] ?? ''), 0, 10);

    return [
        'before_label' => $beforeAt !== '' ? $beforeAt : '처음',
        'before_quest' => $beforeQuest,
        'before_text' => $beforeText,
        'after_label' => $afterAt !== '' ? $afterAt : '최근',
        'after_quest' => $afterQuest,
        'after_text' => $afterText,
    ];
}

/** @param list<array{session: array, quest: array, draft: array}> $completed */
function eduParentReportTopicTags(array $completed): array
{
    $tags = [];
    foreach ($completed as $row) {
        $title = trim((string) ($row['quest']['quest_title'] ?? ''));
        if ($title !== '' && !in_array($title, $tags, true)) {
            $tags[] = $title;
        }
    }

    return array_slice($tags, 0, 8);
}

/** @return list<array{level: int, label_ko: string, current: bool, done: bool}> */
function eduParentReportGrowthPath(int $coachLevel): array
{
    $coachLevel = eduCoachLevelNormalize($coachLevel);
    $path = [];
    for ($lv = EDU_COACH_LEVEL_MIN; $lv <= EDU_COACH_LEVEL_MAX; $lv++) {
        $labels = eduCoachLevelLabels($lv);
        $path[] = [
            'level' => $lv,
            'label_ko' => $labels['ko'],
            'current' => $lv === $coachLevel,
            'done' => $lv < $coachLevel,
        ];
    }

    return $path;
}

/** @param list<array<string, mixed>> $insights */
function eduParentReportInsightsSummary(array $insights): array
{
    $notes = [];
    $tensions = [];
    foreach ($insights as $row) {
        $note = trim((string) ($row['structure_note'] ?? ''));
        if ($note !== '') {
            $notes[] = $note;
        }
        $tension = trim((string) ($row['tension_engaged'] ?? ''));
        if ($tension !== '') {
            $tensions[] = $tension;
        }
    }

    return [
        'structure_notes' => array_slice($notes, -5),
        'tension_samples' => array_values(array_unique($tensions)),
        'insight_count' => count($insights),
    ];
}

/**
 * @return array<string, mixed>
 */
function eduParentReportBuildPayload(
    \Agents\Services\SupabaseService $sb,
    string $studentId,
    bool $generateNarrative = true
): array {
    $student = eduParentReportFetchStudent($sb, $studentId);
    if ($student === null) {
        throw new InvalidArgumentException('Student not found');
    }

    $completed = eduParentReportCompletedSessions($sb, $studentId);
    $insights = eduListStudentInsights($sb, $studentId, 50);
    $coachLevel = eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1));
    $coachPayload = eduCoachLevelProfilePayload($student);
    $tierRow = eduFetchTierRow($studentId);
    $tier = eduTierProgressPayload($tierRow, $coachLevel);

    $completedCount = count($completed);
    $firstCompleted = $completed[0]['session']['completed_at'] ?? null;
    $lastCompleted = $completedCount > 0
        ? ($completed[$completedCount - 1]['session']['completed_at'] ?? null)
        : null;

    $periodLabel = 'gistudy 탐구 리포트';
    if ($firstCompleted && $lastCompleted) {
        $periodLabel = substr((string) $firstCompleted, 0, 7) . ' ~ ' . substr((string) $lastCompleted, 0, 7);
    }

    $latestDraft = $completedCount > 0 ? ($completed[$completedCount - 1]['draft'] ?? []) : [];
    $quote = eduParentReportExtractQuote($latestDraft);
    if ($quote === '' && $completedCount > 0) {
        foreach (array_reverse($completed) as $row) {
            $quote = eduParentReportExtractQuote($row['draft']);
            if ($quote !== '') {
                break;
            }
        }
    }

    $payload = [
        'student_id' => $studentId,
        'student_name' => (string) ($student['display_name'] ?? '학생'),
        'grade_label' => eduParentReportGradeLabel((string) ($student['grade_band'] ?? '')),
        'period_label' => $periodLabel,
        'cover' => [
            'headline_count' => $completedCount,
            'headline' => $completedCount > 0
                ? "{$completedCount}개 세상을 스스로 따졌어요"
                : '세상을 스스로 따지기 시작했어요',
        ],
        'coach_letter' => [
            'paragraphs' => [],
            'generated' => false,
        ],
        'before_after' => eduParentReportBeforeAfter($completed),
        'student_quote' => $quote,
        'growth_path' => eduParentReportGrowthPath($coachLevel),
        'topic_tags' => eduParentReportTopicTags($completed),
        'stats' => [
            'completed_count' => $completedCount,
            'streak_days' => (int) ($tier['streak_days'] ?? 0),
            'coach_level' => $coachLevel,
            'coach_label_ko' => $coachPayload['label_ko'],
        ],
        'insights_summary' => eduParentReportInsightsSummary($insights),
        'narrative_context' => [
            'completed_count' => $completedCount,
            'coach_label_ko' => $coachPayload['label_ko'],
            'streak_days' => (int) ($tier['streak_days'] ?? 0),
            'before_after' => eduParentReportBeforeAfter($completed),
            'student_quote' => $quote,
            'topic_tags' => eduParentReportTopicTags($completed),
            'structure_notes' => eduParentReportInsightsSummary($insights)['structure_notes'],
            'tension_samples' => eduParentReportInsightsSummary($insights)['tension_samples'],
        ],
    ];

    if ($generateNarrative) {
        require_once __DIR__ . '/eduParentReportNarrative.php';
        $letter = eduParentReportGenerateNarrative($payload);
        $payload['coach_letter'] = $letter;
    }

    return $payload;
}
