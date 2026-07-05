<?php
/**
 * narrative v2 — 학생 자유입력 성의 검증 (규칙 + 관대한 LLM)
 * 벌이 아니라 부드러운 재초대. 진심·서툰 답은 통과 우선.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';
require_once __DIR__ . '/eduNarrativeV2Generate.php';
require_once __DIR__ . '/eduLlmJson.php';
require_once __DIR__ . '/_llm.php';

/** @return list<string> */
function eduNarrativeV2InputProfanityTerms(): array
{
    return [
        '시발', '씨발', 'ㅅㅂ', 'ㅆㅂ', '병신', '븅신', '지랄', 'ㅈㄹ', '개새', '썅', '좆', 'ㅈ같', 'fuck', 'shit', 'damn',
    ];
}

/** @param array<string, mixed> $ctx */
function eduNarrativeV2InputQualityTopicNeedles(array $ctx): array
{
    $needles = [];
    foreach (['quest_title', 'hook_student', 'hinge', 'side_a', 'side_b'] as $key) {
        $v = trim((string) ($ctx[$key] ?? ''));
        if ($v === '') {
            continue;
        }
        $needles[] = $v;
        foreach (preg_split('/[\s,·—\-|:：()（）「」\[\]]+/u', $v) ?: [] as $token) {
            $token = trim($token);
            if (mb_strlen($token) >= 2) {
                $needles[] = $token;
            }
        }
    }

    return array_values(array_unique(array_filter($needles)));
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2InputQualityTopicLabel(array $quest): string
{
    $ctx = eduNarrativeV2GenerateContextFromQuest($quest);
    $hook = trim((string) ($ctx['hook_student'] ?? ''));
    if ($hook !== '' && mb_strlen($hook) <= 28) {
        return $hook;
    }
    $title = trim((string) ($ctx['quest_title'] ?? ''));
    if ($title !== '') {
        $short = preg_replace('/\s*[—\-|:|].*$/u', '', $title) ?? $title;
        if (mb_strlen($short) > 22) {
            $short = mb_substr($short, 0, 20) . '…';
        }

        return $short;
    }

    return '이 문제';
}

function eduNarrativeV2InputIsJamoOnly(string $text): bool
{
    $t = preg_replace('/\s/u', '', trim($text));
    if ($t === '') {
        return true;
    }
    if (preg_match('/[가-힣]/u', $t)) {
        return false;
    }

    return (bool) preg_match('/^[\x{3131}-\x{318E}ㄱ-ㅎㅏ-ㅣ]+$/u', $t);
}

function eduNarrativeV2InputIsRepeatSpam(string $text): bool
{
    $t = preg_replace('/\s/u', '', trim($text));
    if (mb_strlen($t) < 4) {
        return false;
    }
    if (preg_match('/(.)\1{3,}/us', $t)) {
        return true;
    }

    return (bool) preg_match('/^[ㅋㅎㅠㅜaAhHkK]+$/u', $t);
}

function eduNarrativeV2InputIsProfanity(string $text): bool
{
    $lower = mb_strtolower(trim($text));
    foreach (eduNarrativeV2InputProfanityTerms() as $term) {
        if ($term !== '' && mb_strpos($lower, mb_strtolower($term)) !== false) {
            return true;
        }
    }

    return false;
}

function eduNarrativeV2InputIsTooShortAck(string $text): bool
{
    if (eduCoachHasSubstantiveAnswer($text)) {
        return false;
    }

    return mb_strlen(trim($text)) <= 2;
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2InputLooksOffTopic(string $text, array $quest): bool
{
    if (!eduCoachHasSubstantiveAnswer($text)) {
        return false;
    }

    $ctx = eduNarrativeV2GenerateContextFromQuest($quest);
    $lower = mb_strtolower(trim($text));
    foreach (eduNarrativeV2InputQualityTopicNeedles($ctx) as $needle) {
        $n = mb_strtolower(trim($needle));
        if ($n !== '' && mb_strlen($n) >= 2 && mb_strpos($lower, $n) !== false) {
            return false;
        }
    }

    if (preg_match('/(점심|저녁|아침|밥\s*먹|뭐\s*먹|라면|치킨|피자|배\s*고|숙제\s*많|시험\s*말고\s*놀)/u', $lower)) {
        return true;
    }

    return false;
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2InputQualityLlmRelevant(string $text, array $quest, string $coachQuestion): bool
{
    try {
        $ctx = eduNarrativeV2GenerateContextFromQuest($quest);
        $topic = eduNarrativeV2InputQualityTopicLabel($quest);
        $llm = eduLlm();
        $system = <<<'PROMPT'
당신은 the gist EDU 코치의 입력 판정 보조입니다.
학생 자유입력이 **탐구 주제와 관련 있는지**만 판정합니다.

★ 관대함 원칙 (필수):
- 애매하면 relevant=true (학생 편)
- 서툴고 짧아도 관련 있으면 relevant=true
- "잘 모르겠지만…", 비유·은유("게임 같아") → relevant=true
- 확실히 딴얘기(음식·놀이만, 주제 무관)일 때만 relevant=false

JSON만: {"relevant": true} 또는 {"relevant": false}
PROMPT;
        $user = json_encode([
            'quest_title' => $ctx['quest_title'] ?? '',
            'topic_label' => $topic,
            'coach_question' => eduNarrativeV2TrimPhrase($coachQuestion, 400),
            'student_message' => $text,
        ], JSON_UNESCAPED_UNICODE);

        $response = $llm->chat($system, [['role' => 'user', 'content' => $user]], 120, 0.2);
        $parsed = eduParseLlmJson($response, ['relevant' => true]);
        if (!is_array($parsed)) {
            return true;
        }

        return !empty($parsed['relevant']);
    } catch (Throwable) {
        return true;
    }
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $node
 * @param array<string, mixed> $quest
 * @return array{pass: bool, category: string|null, strikes: int}
 */
function eduNarrativeV2InputQualityEvaluate(
    string $text,
    array $quest,
    array $node,
    array $blueprint,
    string $coachQuestion
): array {
    if (($node['input_mode'] ?? '') !== 'text') {
        return ['pass' => true, 'category' => null, 'strikes' => 0];
    }

    $strikes = (int) (($blueprint['narrative_v2_input_quality']['strikes'] ?? 0));

    if (eduNarrativeV2InputIsProfanity($text)) {
        return ['pass' => false, 'category' => 'profanity', 'strikes' => $strikes + 1];
    }
    if (eduNarrativeV2InputIsJamoOnly($text) || eduNarrativeV2InputIsRepeatSpam($text)) {
        return ['pass' => false, 'category' => 'meaningless', 'strikes' => $strikes + 1];
    }
    if (eduNarrativeV2InputIsTooShortAck($text)) {
        return ['pass' => false, 'category' => 'too_short', 'strikes' => $strikes + 1];
    }

    if (eduCoachHasSubstantiveAnswer($text) && !eduNarrativeV2InputLooksOffTopic($text, $quest)) {
        return ['pass' => true, 'category' => null, 'strikes' => 0];
    }

    if (eduNarrativeV2InputLooksOffTopic($text, $quest)) {
        $relevant = eduNarrativeV2InputQualityLlmRelevant($text, $quest, $coachQuestion);
        if (!$relevant) {
            return ['pass' => false, 'category' => 'off_topic', 'strikes' => $strikes + 1];
        }
    }

    return ['pass' => true, 'category' => null, 'strikes' => 0];
}

/** @return array<string, mixed> */
function eduNarrativeV2InputQualityReset(): array
{
    return ['strikes' => 0, 'rot' => []];
}

/**
 * @param array<string, mixed> $blueprint
 * @return array<string, mixed>
 */
function eduNarrativeV2InputQualityBump(array $blueprint, string $category): array
{
    $state = is_array($blueprint['narrative_v2_input_quality'] ?? null)
        ? $blueprint['narrative_v2_input_quality']
        : ['strikes' => 0, 'rot' => []];
    $rot = is_array($state['rot'] ?? null) ? $state['rot'] : [];
    $idx = (int) ($rot[$category] ?? 0);
    $rot[$category] = $idx + 1;

    return [
        'strikes' => (int) ($state['strikes'] ?? 0) + 1,
        'rot' => $rot,
        '_last_idx' => $idx,
    ];
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2InputQualityCoachLine(string $category, array $quest, int $rotationIndex): string
{
    $topic = eduNarrativeV2InputQualityTopicLabel($quest);
    $lines = match ($category) {
        'meaningless' => [
            '음? 뭔가 더 있을 것 같은데. 한 문장만 들려줄래?',
            '천천히 해도 괜찮아. 지금 이 문제, 어떻게 느껴?',
            '조금만 더 써줘. 네 생각이 궁금해.',
        ],
        'profanity' => [
            '장난도 좋아 ㅎ 근데 이 문제, 네 진짜 생각은 어때?',
            '괜찮아, 편하게. 근데 이번엔 진지하게 한 번?',
        ],
        'off_topic' => [
            '오 그것도 재밌네! 근데 지금은 ' . $topic . '로 돌아가볼까?',
            '그 얘기도 나중에 하자 ㅎ 지금은 ' . $topic . ' 어떻게 봐?',
            '좋아 ㅎ 다만 지금은 ' . $topic . ' 이야기부터 같이 해보자.',
        ],
        'too_short' => [
            '좋아, 근데 \'왜\' 그렇게 생각해? 조금만 더.',
            '그 이유가 궁금해. 한 문장만 더 붙여줄래?',
            '응, 근데 조금만 더 설명해줄 수 있어?',
        ],
        default => [
            '한 문장만 더 들려줄래? 네 생각이 궁금해.',
        ],
    };
    $count = count($lines);

    return $lines[$rotationIndex % $count];
}

/** @param array<string, mixed> $node @return list<array{id: string, label: string}> */
function eduNarrativeV2InputQualityNodeChoices(array $node): array
{
    $out = [];
    foreach ($node['choices'] ?? [] as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $id = trim((string) ($choice['id'] ?? ''));
        $label = trim((string) ($choice['label'] ?? ''));
        if ($id === '' || $label === '') {
            continue;
        }
        $out[] = ['id' => $id, 'label' => $label];
    }

    return $out;
}

/**
 * @param array<string, mixed> $script
 * @return list<array{id: string, label: string}>
 */
function eduNarrativeV2InputQualityLayerButtonFallback(array $script, string $nodeId, string $layerId): array
{
    foreach ($script['nodes'] ?? [] as $parent) {
        if (!is_array($parent)) {
            continue;
        }
        $leadsToText = false;
        $buttons = [];
        foreach ($parent['choices'] ?? [] as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            if ((string) ($choice['next'] ?? '') === $nodeId) {
                $leadsToText = true;
            }
            if (!empty($choice['input_mode'])) {
                continue;
            }
            $id = trim((string) ($choice['id'] ?? ''));
            $label = trim((string) ($choice['label'] ?? ''));
            if ($id !== '' && $label !== '') {
                $buttons[] = ['id' => $id, 'label' => $label];
            }
        }
        if ($leadsToText && $buttons !== []) {
            return $buttons;
        }
    }

    foreach ($script['nodes'] ?? [] as $node) {
        if (!is_array($node) || ($node['layer'] ?? '') !== $layerId) {
            continue;
        }
        if (!empty($node['input_mode'])) {
            continue;
        }
        $choices = eduNarrativeV2InputQualityNodeChoices($node);
        if ($choices !== []) {
            return $choices;
        }
    }

    return [];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $script
 * @param array<string, mixed> $node
 * @param array{pass: bool, category: string|null, strikes: int} $quality
 * @return array<string, mixed>
 */
function eduNarrativeV2InputQualityRejectResponse(
    array $blueprint,
    array $quest,
    array $script,
    string $nodeId,
    array $node,
    string $text,
    array $quality
): array {
    $category = (string) ($quality['category'] ?? 'meaningless');
    $qualityState = eduNarrativeV2InputQualityBump($blueprint, $category);
    $rotIdx = (int) ($qualityState['_last_idx'] ?? 0);
    unset($qualityState['_last_idx']);

    $strikes = (int) ($qualityState['strikes'] ?? 0);
    $layerId = (string) ($node['layer'] ?? '');
    $fallbackChoices = [];
    $inputMode = (string) ($node['input_mode'] ?? 'text');
    $message = eduNarrativeV2InputQualityCoachLine($category, $quest, $rotIdx);

    if ($strikes >= 3 && in_array($category, ['meaningless', 'too_short'], true)) {
        $fallbackChoices = eduNarrativeV2InputQualityLayerButtonFallback($script, $nodeId, $layerId);
        if ($fallbackChoices !== []) {
            $message = '글로 쓰기 어려우면 — 아래에서 골라볼까?';
            $inputMode = '';
        }
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'narrative_v2_input_quality' => $qualityState,
        'narrative_v2_input_mode' => $inputMode !== '' ? $inputMode : null,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => $message,
        'choices' => $fallbackChoices,
        'input_mode' => $inputMode,
        'ui_hint' => 'narrative_v2',
        'should_compose' => false,
        'student_label' => $text,
        'board_pulse_layer' => null,
        'input_quality_redirect' => true,
    ];
}
