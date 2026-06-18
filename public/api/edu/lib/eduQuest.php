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

/** @return list<array<string, mixed>> */
function eduArticleAxesForNews(array $quest, int $newsId): array
{
    $axes = eduQuestHammerHints($quest)['axes'] ?? [];
    if (!is_array($axes)) {
        return [];
    }
    $matches = [];
    foreach ($axes as $axis) {
        if (!is_array($axis)) {
            continue;
        }
        if ((int) ($axis['news_id'] ?? 0) === $newsId) {
            $matches[] = $axis;
        }
    }
    return $matches;
}

function eduArticleMediaPerspective(array $quest, array $article): string
{
    $newsId = (int) ($article['news_id'] ?? 0);
    $outlet = trim((string) ($article['source_outlet'] ?? ''));
    $matches = eduArticleAxesForNews($quest, $newsId);

    $axis = null;
    if (count($matches) === 1) {
        $axis = $matches[0];
    } elseif ($matches !== []) {
        if ($outlet !== '') {
            foreach ($matches as $candidate) {
                $author = trim((string) ($candidate['author'] ?? ''));
                if ($author !== ''
                    && (str_contains($outlet, $author) || str_contains($author, $outlet))) {
                    $axis = $candidate;
                    break;
                }
            }
        }
        $axis ??= $matches[0];
    }

    if ($axis !== null) {
        $name = trim((string) ($axis['author'] ?? ''));
        if ($name === '') {
            $name = $outlet !== '' ? $outlet : '이 매체';
        }
        $label = trim((string) ($axis['axis_label'] ?? ''));
        if ($label !== '') {
            return "{$name} — {$label} 때문에 본다";
        }
        $thesis = trim((string) ($axis['thesis'] ?? ''));
        if ($thesis !== '') {
            $first = preg_split('/(?<=[.!?…])\s+/u', $thesis)[0] ?? $thesis;
            return "{$name} — {$first}";
        }
    }

    $displayName = $outlet !== '' ? $outlet : trim((string) ($article['title'] ?? ''));
    $why = trim(strip_tags(html_entity_decode((string) ($article['why_important'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($displayName !== '' && $why !== '') {
        $first = preg_split('/(?<=[.!?…])\s+/u', $why)[0] ?? $why;
        $first = trim(preg_replace('/\s+/u', ' ', $first) ?? $first);
        if (mb_strlen($first) > 80) {
            $first = mb_substr($first, 0, 77) . '…';
        }
        return "{$displayName} — {$first}";
    }

    return $displayName;
}

function eduPublicArticleOutlet(array $quest, array $article): string
{
    $outlet = trim((string) ($article['source_outlet'] ?? ''));
    if ($outlet !== '' && $outlet !== 'the gist') {
        return $outlet;
    }

    $matches = eduArticleAxesForNews($quest, (int) ($article['news_id'] ?? 0));
    foreach ($matches as $axis) {
        $author = trim((string) ($axis['author'] ?? ''));
        if ($author !== '' && $author !== '기사 종합') {
            return $author;
        }
    }
    if ($matches !== []) {
        $author = trim((string) ($matches[0]['author'] ?? ''));
        if ($author !== '') {
            return $author;
        }
    }

    return $outlet;
}

/** @return array<string, mixed> */
function eduPublicArticleRow(array $quest, array $article): array
{
    return [
        'news_id' => (int) ($article['news_id'] ?? 0),
        'role' => $article['role'] ?? 'context',
        'title' => $article['title'] ?? '',
        'gist_url' => $article['gist_url'] ?? '',
        'excerpt' => $article['excerpt'] ?? '',
        'why_important' => $article['why_important'] ?? '',
        'source_outlet' => eduPublicArticleOutlet($quest, $article),
        'media_perspective' => eduArticleMediaPerspective($quest, $article),
        'published_at' => $article['published_at'] ?? null,
    ];
}

function eduPublicQuestPayload(array $quest): array
{
    require_once __DIR__ . '/eduQuestConfig.php';
    $hints = eduQuestHammerHints($quest);
    $articles = [];
    foreach ($quest['articles'] ?? [] as $a) {
        $articles[] = eduPublicArticleRow($quest, $a);
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
        'entry_mode' => eduQuestEntryMode($quest),
        'hook_short' => $hints['hook_short'] ?? null,
        'hook_full' => $hints['hook_full'] ?? null,
        'articles' => $articles,
        'fsm_stages' => $quest['fsm_stages'] ?? ['commit', 'hammer', 'reflection', 'writing', 'growth'],
    ];
}

/** @return list<string> FSM stages that allow resume (excludes completed / abandoned). */
function eduSessionStagesInProgress(): array
{
    return ['commit', 'reasoning', 'evidence', 'hammer', 'reflection', 'writing', 'compose', 'growth'];
}

/** PostgREST filter for start.php / today.php active session lookup. */
function eduSessionStageFilterResumable(): string
{
    return 'stage=in.(' . implode(',', eduSessionStagesInProgress()) . ')';
}

/** PostgREST filter for true completions (excludes pseudo-completed abandon rows). */
function eduSessionStageFilterCompleted(): string
{
    return 'stage=eq.completed&completed_at=not.is.null';
}

/** @param array<string, mixed> $session */
function eduSessionBlueprintRaw(array $session): array
{
    $raw = $session['blueprint_json'] ?? [];
    if (is_string($raw)) {
        $raw = json_decode($raw, true) ?: [];
    }

    return is_array($raw) ? $raw : [];
}

/** @param array<string, mixed> $session */
function eduIsSessionAbandoned(array $session): bool
{
    if (($session['stage'] ?? '') === 'abandoned') {
        return true;
    }
    $bp = eduSessionBlueprintRaw($session);

    return trim((string) ($bp['abandoned_at'] ?? '')) !== '';
}

/** @param array<string, mixed> $session */
function eduIsSessionResumable(array $session): bool
{
    if (eduIsSessionAbandoned($session)) {
        return false;
    }

    return in_array((string) ($session['stage'] ?? ''), eduSessionStagesInProgress(), true);
}

/** @param array<string, mixed> $session */
function eduGuardSessionAbandoned(array $session, string $sessionId): void
{
    if (!eduIsSessionAbandoned($session)) {
        return;
    }
    eduSendJson([
        'success' => true,
        'session_id' => $sessionId,
        'stage' => 'abandoned',
        'resumable' => false,
        'should_compose' => false,
        'assistant_message' => '이전 진행 내역은 저장 방식 변경으로 종료됐어요. 새 퀘스트를 시작해 주세요.',
    ]);
}

function eduActiveSession(string $studentId): ?array
{
    $supabase = eduSupabase();
    $rows = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $studentId . '&' . eduSessionStageFilterResumable() . '&order=started_at.desc',
        1
    );
    return $rows[0] ?? null;
}

function eduActiveSessionForQuest(string $studentId, string $questId): ?array
{
    $supabase = eduSupabase();
    $rows = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $studentId . '&quest_id=eq.' . $questId . '&' . eduSessionStageFilterResumable() . '&order=started_at.desc',
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

function eduIsMythBustQuest(array $quest): bool
{
    if (!function_exists('eduQuestEntryMode')) {
        require_once __DIR__ . '/eduQuestConfig.php';
    }

    return eduQuestEntryMode($quest) === 'open_response';
}

/**
 * @return ?array{axis_id: string, axis_label: string}
 */
function eduMatchStudentAxisFromText(string $text, array $quest): ?array
{
    if (!eduIsConvergentQuest($quest)) {
        return null;
    }

    $axes = eduQuestHammerHints($quest)['axes'] ?? [];
    if (!is_array($axes) || $axes === []) {
        return null;
    }

    $haystack = mb_strtolower(trim($text));
    if ($haystack === '') {
        return null;
    }

    $keywordMap = [
        'tech' => ['폭격', '미사일', '무기', '기술', '정밀', '군사력', '타격', '드론', '대만', '중국'],
        'politics' => ['민심', '국민', '여론', '정치', '반전', '국내', '사회', '정권', '트럼프', '미국', '동맹', '바이든'],
        'structure' => ['구조', '봉합', '복잡', '얽히', '원래', '불안정', '예전', '흐름', '역사', '패턴', '베트남'],
        'military' => ['군사', '드론', '미사일', '억지', '협박', '핵무장', '멸망', '재래식'],
        'norms' => ['약속', '규칙', '규범', '국제', '금지'],
        'defense' => ['방어', '방공', '기지', '회복력', '방호'],
    ];

    $rawScores = [];
    $labelsById = [];

    foreach ($axes as $axis) {
        if (!is_array($axis)) {
            continue;
        }
        $axisId = (string) ($axis['axis_id'] ?? '');
        $label = trim((string) ($axis['axis_label'] ?? ''));
        if ($axisId === '' || $label === '') {
            continue;
        }
        $labelsById[$axisId] = $label;

        $score = 0;
        $labelLower = mb_strtolower($label);
        if ($haystack === $labelLower || str_contains($haystack, $labelLower)) {
            $score += 10;
        }

        foreach (array_unique(preg_split('/[\s·\/]+/u', $labelLower) ?: []) as $token) {
            $token = trim((string) $token);
            if (mb_strlen($token) >= 2 && str_contains($haystack, $token)) {
                $score += 2;
            }
        }

        $needles = array_filter([
            (string) ($axis['contrast_prompt']['names_axis'] ?? ''),
            (string) ($axis['thesis'] ?? ''),
        ]);
        foreach ($needles as $needle) {
            $n = mb_strtolower(trim($needle));
            if ($n !== '' && mb_strlen($n) >= 4 && str_contains($haystack, mb_substr($n, 0, min(8, mb_strlen($n))))) {
                $score += 1;
            }
        }

        foreach ($keywordMap[$axisId] ?? [] as $kw) {
            if (str_contains($haystack, mb_strtolower($kw))) {
                $score += 1;
            }
        }

        $rawScores[$axisId] = $score;
    }

    if ($rawScores === []) {
        return null;
    }

    arsort($rawScores);
    $ids = array_keys($rawScores);
    $topId = $ids[0];
    $topScore = $rawScores[$topId];
    $secondScore = $rawScores[$ids[1] ?? ''] ?? 0;

    if ($topScore === 0 || $topScore === $secondScore) {
        return null;
    }

    return [
        'axis_id' => $topId,
        'axis_label' => $labelsById[$topId] ?? $topId,
    ];
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

    $fromRebuttal = eduMatchStudentAxisFromText((string) ($blueprint['rebuttal'] ?? ''), $quest);
    if ($fromRebuttal !== null) {
        return $fromRebuttal;
    }

    return eduMatchStudentAxisFromText(
        implode(' ', array_filter([
            (string) ($blueprint['reason'] ?? ''),
            (string) ($blueprint['evidence'] ?? ''),
        ])),
        $quest
    );
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
