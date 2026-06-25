<?php
/**
 * GIST EDU — EssayBlueprint harness state (session-scoped)
 */
declare(strict_types=1);

/** @return array<string, mixed> */
function eduBlueprintDefaults(): array
{
    return [
        'stance' => null,
        'reason' => '',
        'reason_depth' => 0,
        'reason_followup_count' => 0,
        'evidence' => '',
        'evidence_nudge_count' => 0,
        'counter_argument' => '',
        'rebuttal' => '',
        'counter_handled' => false,
        'stance_changed' => false,
        'final_stance' => null,
        'reflection_lines' => [],
        'reflection_confirmed' => false,
        'scqa_slots' => [
            'S' => '',
            'C' => '',
            'Q' => '',
            'A' => '',
            'conclusion' => '',
        ],
        'essay_structure' => [],
        'essay_artifact' => [],
        'phase' => 'stance',
        'exchange_count' => 0,
        'ready_for_compose' => false,
    ];
}

/** @param array<string, mixed> $session */
function eduLoadBlueprint(array $session): array
{
    $raw = $session['blueprint_json'] ?? null;
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw) || $raw === []) {
        $hammer = $session['hammer_payload'] ?? [];
        if (is_string($hammer)) {
            $hammer = json_decode($hammer, true) ?: [];
        }
        $raw = $hammer['blueprint'] ?? [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    return array_replace_recursive(eduBlueprintDefaults(), $raw);
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $patch */
function eduMergeBlueprint(array $blueprint, array $patch): array
{
    foreach ($patch as $key => $value) {
        if ($key === 'scqa_slots' && is_array($value)) {
            $blueprint['scqa_slots'] = array_merge($blueprint['scqa_slots'] ?? [], $value);
        } elseif ($key === 'reflection_lines' && is_array($value)) {
            $blueprint['reflection_lines'] = $value;
        } else {
            $blueprint[$key] = $value;
        }
    }
    return $blueprint;
}

/** @param array<string, mixed> $blueprint */
function eduBlueprintProgress(array $blueprint): int
{
    $weights = [
        'stance' => 10,
        'reason' => 20,
        'evidence' => 20,
        'counter_handled' => 25,
        'reflection_confirmed' => 15,
        'ready_for_compose' => 10,
    ];
    $score = 0;
    if (!empty($blueprint['stance'])) {
        $score += $weights['stance'];
    }
    if (trim((string) ($blueprint['reason'] ?? '')) !== '') {
        $score += $weights['reason'];
    }
    if (trim((string) ($blueprint['evidence'] ?? '')) !== '') {
        $score += $weights['evidence'];
    }
    if (!empty($blueprint['counter_handled'])) {
        $score += $weights['counter_handled'];
    }
    if (!empty($blueprint['reflection_confirmed'])) {
        $score += $weights['reflection_confirmed'];
    }
    if (!empty($blueprint['ready_for_compose'])) {
        $score += $weights['ready_for_compose'];
    }
    return min(100, $score);
}

/** @param array<string, mixed> $blueprint */
function eduBlueprintReadyForCompose(array $blueprint): bool
{
    if (!empty($blueprint['ready_for_compose'])) {
        return true;
    }
    if (empty($blueprint['stance'])) {
        return false;
    }
    if ((int) ($blueprint['exchange_count'] ?? 0) >= 12) {
        return true;
    }
    return !empty($blueprint['reflection_confirmed'])
        && !empty($blueprint['counter_handled'])
        && trim((string) ($blueprint['reason'] ?? '')) !== '';
}

/** @param array<string, mixed> $blueprint */
function eduBlueprintStage(array $blueprint): string
{
    $phase = (string) ($blueprint['phase'] ?? 'stance');
    return match ($phase) {
        'stance', 'reasoning' => 'reasoning',
        'evidence' => 'evidence',
        'hammer' => 'hammer',
        'reflection' => 'reflection',
        'compose' => 'compose',
        'completed' => 'completed',
        default => 'commit',
    };
}

/**
 * Next turn_id for append (legacy turns without id use array length as floor).
 *
 * @param list<array<string, mixed>> $dialogue
 */
function eduDialogueNextTurnId(array $dialogue): string
{
    $max = 0;
    foreach ($dialogue as $turn) {
        if (!is_array($turn)) {
            continue;
        }
        $id = (string) ($turn['turn_id'] ?? '');
        if (preg_match('/^t-(\d+)$/', $id, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    $max = max($max, count($dialogue));

    return 't-' . ($max + 1);
}

/**
 * Read-only: additive turn_id for API/display. Does not mutate input.
 *
 * @param list<array<string, mixed>> $dialogue
 * @return list<array<string, mixed>>
 */
function eduNormalizeDialogueTurns(array $dialogue): array
{
    $out = [];
    $index = 0;
    foreach ($dialogue as $turn) {
        if (!is_array($turn)) {
            continue;
        }
        $index++;
        $normalized = $turn;
        if (!isset($normalized['turn_id']) || trim((string) $normalized['turn_id']) === '') {
            $normalized['turn_id'] = 't-' . $index;
        }
        $out[] = $normalized;
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $dialogue
 * @param string|null $phase blueprint phase at append time (metadata only)
 */
function eduAppendDialogue(
    array $dialogue,
    string $role,
    string $content,
    ?string $agent = null,
    ?string $phase = null
): array {
    $turn = [
        'role' => $role,
        'content' => $content,
        'agent' => $agent,
        'at' => date('c'),
        'turn_id' => eduDialogueNextTurnId($dialogue),
    ];
    if ($phase !== null && $phase !== '') {
        $turn['phase'] = $phase;
    }
    $dialogue[] = $turn;

    return $dialogue;
}

function eduSaveBlueprint(\Agents\Services\SupabaseService $supabase, string $sessionId, array $blueprint, array $dialogue = []): void
{
    $payload = [
        'blueprint_json' => $blueprint,
        'stage' => eduBlueprintStage($blueprint),
        'updated_at' => date('c'),
    ];
    if ($dialogue !== []) {
        $payload['dialogue_json'] = $dialogue;
    }
    if (!empty($blueprint['stance'])) {
        $payload['stance'] = $blueprint['stance'];
    }

    $ok = $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, $payload);
    if ($ok === null) {
        $fallback = [
            'hammer_payload' => ['blueprint' => $blueprint, 'dialogue' => $dialogue],
            'stage' => eduBlueprintStage($blueprint),
            'updated_at' => date('c'),
        ];
        if (!empty($blueprint['stance'])) {
            $fallback['stance'] = $blueprint['stance'];
        }
        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, $fallback);
    }
}

/**
 * @param list<array{heading?: string, paragraphs?: list<string>}> $sections
 * @param list<string> $conclusionParagraphs
 */
function eduBuildEssayFullText(
    string $title,
    string $subtitle,
    array $sections,
    string $conclusionHeading,
    array $conclusionParagraphs
): string {
    $lines = [];
    if ($title !== '') {
        $lines[] = $title;
    }
    if ($subtitle !== '') {
        $lines[] = $subtitle;
    }
    if ($lines !== []) {
        $lines[] = '';
    }

    foreach ($sections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $heading = trim((string) ($sec['heading'] ?? ''));
        if ($heading !== '') {
            $lines[] = $heading;
        }
        foreach ($sec['paragraphs'] ?? [] as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $lines[] = $p;
            }
        }
        $lines[] = '';
    }

    $conclusionHeading = trim($conclusionHeading);
    if ($conclusionHeading !== '') {
        $lines[] = $conclusionHeading;
    }
    foreach ($conclusionParagraphs as $p) {
        $p = trim((string) $p);
        if ($p !== '') {
            $lines[] = $p;
        }
    }

    return trim(implode("\n", $lines));
}

/**
 * Load dialogue from session storage.
 *
 * @param bool $withTurnIds When true, apply read-only normalize (state API only).
 *                          chat/compose use default false to avoid legacy write-back.
 * @return list<array<string, mixed>>
 */
function eduLoadDialogue(array $session, bool $withTurnIds = false): array
{
    $raw = $session['dialogue_json'] ?? [];
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw) || $raw === []) {
        $hammer = $session['hammer_payload'] ?? [];
        if (is_string($hammer)) {
            $hammer = json_decode($hammer, true) ?: [];
        }
        $fallback = $hammer['dialogue'] ?? [];
        $raw = is_array($fallback) ? $fallback : [];
    }

    if ($withTurnIds) {
        return eduNormalizeDialogueTurns($raw);
    }

    return $raw;
}

/** reflection 정리 확인(맞아/네 등) — hammer 반론 답변과 구분 */
function eduIsReflectionConfirm(string $message): bool
{
    $text = mb_strtolower(trim($message));
    if ($text === '' || mb_strlen($text) > 24) {
        return false;
    }
    if (preg_match('/다르게|아니|틀렸|수정|고쳐|다시/u', $text)) {
        return false;
    }

    return (bool) preg_match(
        '/^(맞아|맞아요|맞게|맞음|맞습니다|그래|응|네|예|ㅇㅇ|yes|ok|okay)[.!?\s~]*$/u',
        $text
    );
}

/** Hammer 본문 + 탐구조 초대 suffix (counter_argument 필드는 본문만 유지) */
function eduFormatHammerDelivery(string $counterBody, string $mode = ''): string
{
    $body = trim($counterBody);
    if ($body !== '') {
        $body = preg_replace(
            '/([.!?…]["\'”’)]*)\s+(?=(?:다만|하지만|그래서|그런데|한편|반면|즉|또|실제로)\b)/u',
            "$1\n\n",
            $body
        ) ?? $body;
    }
    if ($body === '') {
        return $mode === 'convergent_meta_ask'
            ? '어느 쪽이 더 와닿아?'
            : '이런 시각도 있는데, 너는 어때?';
    }

    if (preg_match('/[?？]/u', $body) || preg_match('/(어때|느껴|생각해|와닿)\??\s*$/u', $body)) {
        return $body;
    }

    $suffix = $mode === 'convergent_meta_ask'
        ? '어느 쪽이 더 와닿아?'
        : '이런 시각도 있는데, 너는 어때?';

    return $body . "\n\n" . $suffix;
}

/** turn.php 레거시 — Hammer 초대 문구 */
function eduHammerInvitePrompt(string $mode = ''): string
{
    if ($mode === 'convergent_meta_ask') {
        return '어느 쪽이 더 와닿아? 네 생각을 한두 문장으로 말해줘.';
    }

    return '이런 시각도 있는데, 너는 어때? 네 입장이 바뀌었어, 아니면 유지해?';
}

/**
 * evidence bridge — 학생 reason에서 인용할 구절 (고정 템플릿용, LLM 미사용).
 * 너무 짧으면 null → fallback 멘트.
 */
function eduEvidenceBridgeSnippet(string $reason, int $minLen = 8, int $maxLen = 80): ?string
{
    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $reason)));
    if ($text === '' || mb_strlen($text) < $minLen) {
        return null;
    }

    $snippet = $text;
    if (mb_strlen($text) > $maxLen) {
        if (preg_match('/^(.+?[.!?？])(?:\s|$)/u', $text, $match)) {
            $snippet = trim($match[1]);
        }
        if (mb_strlen($snippet) > $maxLen) {
            $snippet = rtrim(mb_substr($snippet, 0, $maxLen)) . '…';
        }
    }

    if (mb_strlen($snippet) < $minLen) {
        return null;
    }

    return $snippet;
}

/**
 * reasoning → evidence 전환 bridge (고정 템플릿, LLM 미호출).
 *
 * @param array<string, mixed> $blueprint
 */
function eduBuildEvidenceBridgeMessage(array $blueprint): string
{
    $reason = (string) ($blueprint['reason'] ?? '');
    $snippet = eduEvidenceBridgeSnippet($reason);
    if ($snippet !== null) {
        return "방금 '{$snippet}'이라고 했지? 그 생각을 기사에서 같이 찾아볼까?";
    }

    return '방금 말한 생각, 기사에서 같이 찾아볼까?';
}

/**
 * evidence nudge follow-up — quest-aware examples from articles/axes (no global nuke hardcode).
 *
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $blueprint
 */
function eduBuildEvidenceNudgeMessage(array $quest, array $blueprint = []): string
{
    $examples = eduEvidenceNudgeExamples($quest);
    $base = '기사에서 본 구체적인 사실 하나를 더 적어줘.';
    if ($examples !== '') {
        return "{$base} 예를 들면 {$examples} 같은 내용이면 좋아.";
    }

    return $base . ' 기사 제목이나 본문에 나온 구체적 표현을 넣어보면 좋아.';
}

/**
 * @param array<string, mixed> $quest
 */
function eduEvidenceNudgeExamples(array $quest): string
{
    if (!function_exists('eduQuestHammerHints')) {
        require_once __DIR__ . '/eduQuest.php';
    }

    $parts = [];
    foreach ($quest['articles'] ?? [] as $article) {
        if (!is_array($article)) {
            continue;
        }
        $title = trim((string) ($article['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        if (mb_strlen($title) > 22) {
            $title = mb_substr($title, 0, 20) . '…';
        }
        $parts[] = $title;
        if (count($parts) >= 3) {
            break;
        }
    }

    if ($parts === []) {
        $hints = eduQuestHammerHints($quest);
        foreach ($hints['axes'] ?? [] as $axis) {
            if (!is_array($axis)) {
                continue;
            }
            $label = trim((string) ($axis['axis_label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $parts[] = $label;
            if (count($parts) >= 3) {
                break;
            }
        }
    }

    return implode(', ', $parts);
}
