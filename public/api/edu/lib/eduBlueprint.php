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

/** @param list<array<string, mixed>> $dialogue */
function eduAppendDialogue(array $dialogue, string $role, string $content, ?string $agent = null): array
{
    $dialogue[] = [
        'role' => $role,
        'content' => $content,
        'agent' => $agent,
        'at' => date('c'),
    ];
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

/** @param array<string, mixed> $session */
function eduLoadDialogue(array $session): array
{
    $raw = $session['dialogue_json'] ?? [];
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (is_array($raw) && $raw !== []) {
        return $raw;
    }
    $hammer = $session['hammer_payload'] ?? [];
    if (is_string($hammer)) {
        $hammer = json_decode($hammer, true) ?: [];
    }
    $fallback = $hammer['dialogue'] ?? [];
    return is_array($fallback) ? $fallback : [];
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
