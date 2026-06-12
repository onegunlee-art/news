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

function eduPublicQuestPayload(array $quest): array
{
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

function eduHammerPayload(array $quest, string $stance): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
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
