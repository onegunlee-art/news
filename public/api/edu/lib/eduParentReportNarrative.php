<?php
/**
 * GIST EDU — 부모 리포트 코치 편지 (AI narrative)
 */
declare(strict_types=1);

require_once __DIR__ . '/_llm.php';
require_once __DIR__ . '/eduLlmJson.php';

/**
 * @param array<string, mixed> $payload eduParentReportBuildPayload output (narrative_context 사용)
 * @return array{paragraphs: list<string>, generated: bool, fallback: bool}
 */
function eduParentReportGenerateNarrative(array $payload): array
{
    $ctx = is_array($payload['narrative_context'] ?? null) ? $payload['narrative_context'] : $payload;
    $name = (string) ($payload['student_name'] ?? '학생');

    try {
        $llm = eduLlm();
    } catch (Throwable $e) {
        error_log('eduParentReportGenerateNarrative llm init: ' . $e->getMessage());

        return eduParentReportFallbackNarrative($name, $ctx);
    }

    $system = <<<'PROMPT'
당신은 gistudy EDU 코치입니다. 부모에게 보내는 "코치의 편지"를 한국어로 작성합니다.

규칙:
- 2~3문단, 각 문단 2~4문장
- 따뜻하고 존중하는 톤. "틀렸다" 대신 "자랐다", "깊어졌다"
- 학생 이름을 자연스럽게 1~2회 사용
- 점수·등급·순위·채점 표현 금지
- Phase2 진단은 서술만 (양면 사고, 근거 연결 등)
- JSON만 출력: {"paragraphs":["...","..."]}
PROMPT;

    $userPayload = [
        'student_name' => $name,
        'grade_label' => $payload['grade_label'] ?? '',
        'completed_count' => $ctx['completed_count'] ?? 0,
        'coach_label_ko' => $ctx['coach_label_ko'] ?? '',
        'streak_days' => $ctx['streak_days'] ?? 0,
        'before_after' => $ctx['before_after'] ?? null,
        'student_quote' => $ctx['student_quote'] ?? '',
        'topics' => $ctx['topic_tags'] ?? [],
        'structure_notes' => $ctx['structure_notes'] ?? [],
        'tension_samples' => $ctx['tension_samples'] ?? [],
    ];

    $messages = [
        ['role' => 'user', 'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE)],
    ];

    $response = $llm->chat($system, $messages, 900, 0.65);
    if (!empty($response['error'])) {
        error_log('eduParentReportGenerateNarrative llm error: ' . ($response['message'] ?? $response['error']));

        return eduParentReportFallbackNarrative($name, $ctx);
    }

    $parsed = eduParseLlmJson($response, ['paragraphs' => []]);
    $paragraphs = [];
    if (is_array($parsed['paragraphs'] ?? null)) {
        foreach ($parsed['paragraphs'] as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $paragraphs[] = $p;
            }
        }
    }

    if ($paragraphs === []) {
        $raw = trim((string) ($response['content'] ?? ''));
        if ($raw !== '') {
            $paragraphs = eduParentReportSplitParagraphs($raw);
        }
    }

    if ($paragraphs === []) {
        return eduParentReportFallbackNarrative($name, $ctx);
    }

    return [
        'paragraphs' => array_slice($paragraphs, 0, 3),
        'generated' => true,
        'fallback' => false,
    ];
}

/** @return list<string> */
function eduParentReportSplitParagraphs(string $text): array
{
    $parts = preg_split('/\n\s*\n/u', trim($text)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $ctx
 * @return array{paragraphs: list<string>, generated: bool, fallback: bool}
 */
function eduParentReportFallbackNarrative(string $name, array $ctx): array
{
    $count = (int) ($ctx['completed_count'] ?? 0);
    $coach = (string) ($ctx['coach_label_ko'] ?? '탐구자');
    $streak = (int) ($ctx['streak_days'] ?? 0);

    $p1 = "{$name}은(는) 뉴스를 그대로 받아들이기보다, 스스로 질문하고 답을 다듬는 시간을 보내고 있습니다.";
    if ($count > 0) {
        $p1 = "{$name}은(는) 지금까지 {$count}번의 탐구를 완주하며, 한 가지 면만 보이던 생각에 조금씩 깊이를 더해 왔습니다.";
    }

    $p2 = "처음엔 말이 짧고 단정적이었을 수 있지만, 기사를 근거로 붙이고 반대 의견을 들여다보는 연습이 늘고 있습니다. 지금의 사고력 단계는 「{$coach}」에 가깝습니다.";
    $ba = $ctx['before_after'] ?? null;
    if (is_array($ba) && !empty($ba['before_text']) && !empty($ba['after_text'])) {
        $p2 = '처음 썼던 생각과 최근의 생각을 비교해 보면, 근거와 맥락이 붙으면서 문장이 한층 단단해진 것을 느낄 수 있습니다. 이 변화는 속도보다 방향이 중요한 성장입니다.';
    }

    $p3 = '앞으로도 gistudy 코치는 정답을 대신 주기보다, 스스로 따지는 힘을 키우는 질문을 이어가겠습니다.';
    if ($streak >= 3) {
        $p3 = "최근 {$streak}일 연속으로 탐구에 참여한 기록도 있습니다. 꾸준함이 쌓일수록, {$name}의 글이 더 또렷해질 거예요.";
    }

    return [
        'paragraphs' => [$p1, $p2, $p3],
        'generated' => false,
        'fallback' => true,
    ];
}
