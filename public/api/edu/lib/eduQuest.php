<?php
/**
 * GIST EDU — quest selection & payload
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function eduTodayQuestCode(array $student): string
{
    $rotation = ['Q-G01', 'Q-G05', 'Q-G14'];
    if (!empty($student['cohort_id'])) {
        $supabase = eduSupabase();
        $cohorts = $supabase->select('edu_pilot_cohorts', 'id=eq.' . $student['cohort_id'], 1);
        if (!empty($cohorts[0]['rotation_codes']) && is_array($cohorts[0]['rotation_codes'])) {
            $rotation = $cohorts[0]['rotation_codes'];
        }
    }
    $idx = (int) date('W') % count($rotation);
    return $rotation[$idx];
}

function eduLoadQuestByCode(string $code): ?array
{
    $supabase = eduSupabase();
    $quests = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code) . '&status=eq.approved', 1);
    if (empty($quests[0])) {
        return null;
    }
    $quest = $quests[0];
    $articles = $supabase->select(
        'edu_quest_articles',
        'quest_id=eq.' . $quest['id'] . '&order=sort_order.asc',
        20
    ) ?? [];
    $quest['articles'] = $articles;
    return $quest;
}

/** today.php와 동일: 라이브 퀘스트 우선, 없으면 rotation fallback */
function eduLoadTodayQuest(?array $student = null): ?array
{
    $supabase = eduSupabase();
    $quests = $supabase->select(
        'edu_daily_quests',
        'status=eq.approved&live_at=not.is.null&live_at=lte.' . rawurlencode(date('c')) . '&order=live_at.desc',
        1
    );

    if (empty($quests[0]) && $student !== null) {
        $code = eduTodayQuestCode($student);
        return eduLoadQuestByCode($code);
    }

    if (empty($quests[0])) {
        return null;
    }

    $quest = $quests[0];
    $articles = $supabase->select(
        'edu_quest_articles',
        'quest_id=eq.' . $quest['id'] . '&order=sort_order.asc',
        20
    ) ?? [];
    $quest['articles'] = $articles;
    return $quest;
}

function eduPublicQuestPayload(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $articles = [];
    foreach ($quest['articles'] ?? [] as $a) {
        $articles[] = [
            'news_id' => (int) $a['news_id'],
            'role' => $a['role'],
            'title' => $a['title'] ?? '',
            'gist_url' => $a['gist_url'] ?? '',
            'excerpt' => $a['excerpt'] ?? '',
            'why_important' => $a['why_important'] ?? '',
            'source_outlet' => $a['source_outlet'] ?? '',
            'published_at' => $a['published_at'] ?? null,
        ];
    }

    return [
        'quest_id' => $quest['id'],
        'quest_code' => $quest['quest_code'],
        'quest_title' => $quest['quest_title'],
        'pro_line' => $quest['pro_line'],
        'con_line' => $quest['con_line'],
        'alignment_summary' => $quest['alignment_summary'] ?? '',
        'conflict_summary' => $quest['conflict_summary'],
        'time_anchor' => $hints['time_anchor'] ?? null,
        'quest_frame' => $hints['quest_frame'] ?? null,
        'articles' => $articles,
        'fsm_stages' => $quest['fsm_stages'] ?? ['commit', 'hammer', 'reflection', 'writing', 'growth'],
    ];
}

function eduActiveSession(string $studentId): ?array
{
    $supabase = eduSupabase();
    $rows = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $studentId . '&stage=neq.completed&order=started_at.desc',
        1
    );
    return $rows[0] ?? null;
}

function eduActiveSessionForQuest(string $studentId, string $questId): ?array
{
    $supabase = eduSupabase();
    $rows = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $studentId . '&quest_id=eq.' . $questId . '&stage=neq.completed&order=started_at.desc',
        1
    );
    return $rows[0] ?? null;
}

function eduHammerPayload(array $quest, string $stance): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }

    $mode = $hints['mode'] ?? 'adversarial';
    if ($mode === 'convergent') {
        $shared = (string) ($hints['shared_conclusion'] ?? '');
        $isDecisionInquiry = ($hints['quest_frame'] ?? '') === 'decision_inquiry';
        if ($isDecisionInquiry) {
            $reflectionQuestion = '네가 본 관점을 한 줄로 정리해볼래? 그 선택, 너는 어떻게 봐?';
        } elseif ($shared !== '') {
            $reflectionQuestion = "네가 고른 근거 층위를 한 줄로 정리해볼래? 그래도 \"{$shared}\"에 동의해?";
        } else {
            $reflectionQuestion = '네 근거 층위를 한 줄로 정리해볼래?';
        }
        return [
            'mode' => 'convergent',
            'stance' => $stance,
            'shared_conclusion' => $shared,
            'axes' => $hints['axes'] ?? [],
            'reflection_question' => $reflectionQuestion,
        ];
    }

    $counterKey = $stance === 'pro' ? 'con' : 'pro';
    $counterLine = $stance === 'pro' ? ($quest['con_line'] ?? '') : ($quest['pro_line'] ?? '');
    $hint = $hints[$counterKey] ?? '';

    $reflectionQuestion = $stance === 'pro'
        ? '반대 입장을 한 줄로 요약하면? 그래도 찬성인가요, 수정할 부분이 있나요?'
        : '찬성 쪽 근거를 한 줄로 요약하면? 그래도 반대인가요, 수정할 부분이 있나요?';

    return [
        'stance' => $stance,
        'counter_line' => $counterLine,
        'hammer_hint' => $hint,
        'conflict_summary' => $quest['conflict_summary'] ?? '',
        'reflection_question' => $reflectionQuestion,
    ];
}

/**
 * hammer_hints.mode에 따라 mixup 컨텍스트 결정
 * convergent: Hammer가 hammer_hints.axes로 자체 처리 — RAG 스킵
 *
 * @param array<string, mixed> $quest
 * @param object|null $rag EduRagService 인스턴스 (adversarial + RAG 활성 시)
 * @return array{mixup_context: string, mixup_sources: list<mixed>}
 */
function eduBuildMixupContext(array $quest, ?object $rag = null): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }

    $mode = $hints['mode'] ?? 'adversarial';
    if ($mode === 'convergent') {
        return ['mixup_context' => '', 'mixup_sources' => []];
    }

    if (!eduMixupRagEnabled() || $rag === null) {
        return ['mixup_context' => '', 'mixup_sources' => []];
    }

    $topic = (string) ($quest['conflict_summary'] ?? $quest['quest_title'] ?? '');
    $pairs = $rag->findMixUpPairs($topic, '', 3);

    return [
        'mixup_context' => $rag->formatMixUpContext($pairs),
        'mixup_sources' => $pairs,
    ];
}

/** @return array<string, mixed> */
function eduQuestHammerHints(array $quest): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }

    return is_array($hints) ? $hints : [];
}

function eduIsConvergentQuest(array $quest): bool
{
    return (eduQuestHammerHints($quest)['mode'] ?? '') === 'convergent';
}

function eduIsDecisionInquiryQuest(array $quest): bool
{
    return (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'decision_inquiry';
}

/**
 * @return ?array{axis_id: string, axis_label: string}
 */
function eduResolveStudentAxis(array $blueprint, array $quest): ?array
{
    if (!eduIsConvergentQuest($quest)) {
        return null;
    }

    $axes = eduQuestHammerHints($quest)['axes'] ?? [];
    if (!is_array($axes) || $axes === []) {
        return null;
    }

    $axisId = trim((string) ($blueprint['student_axis'] ?? ''));
    if ($axisId !== '') {
        foreach ($axes as $axis) {
            if (!is_array($axis)) {
                continue;
            }
            if (($axis['axis_id'] ?? '') === $axisId) {
                $label = trim((string) ($axis['axis_label'] ?? ''));
                if ($label !== '') {
                    return ['axis_id' => $axisId, 'axis_label' => $label];
                }
            }
        }
    }

    $haystack = mb_strtolower(implode(' ', array_filter([
        (string) ($blueprint['reason'] ?? ''),
        (string) ($blueprint['evidence'] ?? ''),
        (string) ($blueprint['rebuttal'] ?? ''),
    ])));

    $scores = [
        'politics' => 0,
        'tech' => 0,
        'structure' => 0,
    ];
    $keywords = [
        'politics' => ['민심', '국민', '여론', '정치', '반전', '히피', '국내', '사회'],
        'tech' => ['폭격', '미사일', '무기', '기술', '정밀', '군사력', '타격'],
        'structure' => ['구조', '봉합', '복잡', '얽히', '원래', '전쟁 자체', '불안정'],
    ];
    foreach ($keywords as $axisKey => $words) {
        foreach ($words as $word) {
            if ($word !== '' && str_contains($haystack, mb_strtolower($word))) {
                $scores[$axisKey]++;
            }
        }
    }

    arsort($scores);
    $topId = (string) array_key_first($scores);
    if ($topId === '' || ($scores[$topId] ?? 0) === 0) {
        return null;
    }

    foreach ($axes as $axis) {
        if (!is_array($axis)) {
            continue;
        }
        if (($axis['axis_id'] ?? '') === $topId) {
            $label = trim((string) ($axis['axis_label'] ?? ''));
            if ($label !== '') {
                return ['axis_id' => $topId, 'axis_label' => $label];
            }
        }
    }

    return null;
}

/**
 * convergent 퀘스트용 학생 관점 라벨 (찬성/반대 대신 axis_label)
 */
function eduDecisionStanceLabel(string $stance, array $quest): string
{
    $line = trim($stance === 'pro'
        ? (string) ($quest['pro_line'] ?? '')
        : (string) ($quest['con_line'] ?? ''));

    if ($line === '') {
        return $stance === 'pro'
            ? '그 결정이 필요하다고 본 입장'
            : '그 결정이 과하다고 본 입장';
    }

    $normalized = preg_replace('/(?:다|했다)고 본다\.?$/u', '다고 본 입장', $line);
    if ($normalized === null || $normalized === $line) {
        if (!str_contains($line, '입장')) {
            return rtrim($line, '.') . ' 입장';
        }
    }

    return $normalized ?? $line;
}

/**
 * UI·compose용 입장 라벨 — decision_inquiry만 pro_line/con_line, adversarial은 찬성/반대
 */
function eduStudentStanceLabel(string $stance, array $quest): string
{
    if (eduIsDecisionInquiryQuest($quest)) {
        return eduDecisionStanceLabel($stance, $quest);
    }

    return $stance === 'pro' ? '찬성' : '반대';
}

function eduStudentPerspectiveLabel(array $blueprint, array $quest): string
{
    $axis = eduResolveStudentAxis($blueprint, $quest);
    if ($axis !== null) {
        return $axis['axis_label'];
    }

    $stance = (string) ($blueprint['final_stance'] ?? $blueprint['stance'] ?? 'pro');

    return eduStudentStanceLabel($stance, $quest);
}
