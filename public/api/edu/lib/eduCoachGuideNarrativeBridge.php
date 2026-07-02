<?php
/**
 * 630 전용 narrative_bridge_v1 FSM — axis_guide / Hammer 우회
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';

const EDU_NARRATIVE_BRIDGE_QUEST_CODE = 'Q-AUTO-NUKE-630';
const EDU_NARRATIVE_BRIDGE_MODE = 'narrative_bridge_v1';
const EDU_NARRATIVE_BRIDGE_SCRIPT = 'docs/coach_scripts/630_narrative_bridge.json';

/** @param array<string, mixed> $quest */
function eduQuestUsesNarrativeBridge(array $quest): bool
{
    if (($quest['quest_code'] ?? '') !== EDU_NARRATIVE_BRIDGE_QUEST_CODE) {
        return false;
    }
    $hints = eduQuestHammerHints($quest);

    return ($hints['coach_mode'] ?? '') === EDU_NARRATIVE_BRIDGE_MODE;
}

/** @return array<string, mixed> */
function eduNarrativeBridgeLoadScript(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $root = eduFindProjectRoot();
    $path = $root . EDU_NARRATIVE_BRIDGE_SCRIPT;
    if (!is_file($path)) {
        throw new RuntimeException('Narrative bridge script missing: ' . EDU_NARRATIVE_BRIDGE_SCRIPT);
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || empty($raw['nodes']) || empty($raw['start_node'])) {
        throw new RuntimeException('Invalid narrative bridge script');
    }
    $cached = $raw;

    return $cached;
}

/** @param array<string, mixed> $script @param array<string, mixed> $node */
function eduNarrativeBridgeNodeCoachText(array $script, array $node): string
{
    if (!empty($node['coach_text'])) {
        return trim((string) $node['coach_text']);
    }
    $parts = [];
    $receive = trim((string) ($node['receive_text'] ?? ''));
    if ($receive !== '') {
        $parts[] = $receive;
    }
    if (!empty($node['append_shake'])) {
        $shake = trim((string) ($script['shake_suffix'] ?? ''));
        if ($shake !== '') {
            $parts[] = $shake;
        }
    }

    return implode("\n\n", $parts);
}

/** @param array<string, mixed> $script */
function eduNarrativeBridgeGetNode(array $script, string $nodeId): ?array
{
    $nodes = $script['nodes'] ?? [];
    if (!is_array($nodes) || !isset($nodes[$nodeId]) || !is_array($nodes[$nodeId])) {
        return null;
    }

    return $nodes[$nodeId];
}

/** @param array<string, mixed> $blueprint @return array<string, mixed> */
function eduNarrativeBridgeInitBlueprint(array $blueprint, array $script): array
{
    $start = (string) ($script['start_node'] ?? 'step_0');

    return eduMergeBlueprint($blueprint, [
        'phase' => 'narrative_bridge',
        'narrative_node' => $start,
        'narrative_step' => 0,
        'narrative_depth' => 0,
        'narrative_choices' => [],
        'opening_submitted' => true,
    ]);
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $node @return list<array{id: string, label: string}> */
function eduNarrativeBridgeNodeChoices(array $blueprint, array $node): array
{
    $choices = [];
    foreach ($node['choices'] ?? [] as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $id = trim((string) ($choice['id'] ?? ''));
        $label = trim((string) ($choice['label'] ?? ''));
        if ($id === '' || $label === '') {
            continue;
        }
        $choices[] = ['id' => $id, 'label' => $label];
    }

    return $choices;
}

/** @param array<string, mixed> $node */
function eduNarrativeBridgeFindChoice(array $node, string $choiceId): ?array
{
    foreach ($node['choices'] ?? [] as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        if ((string) ($choice['id'] ?? '') === $choiceId) {
            return $choice;
        }
    }

    return null;
}

/** @param array<string, mixed> $blueprint */
function eduNarrativeBridgeProgress(array $blueprint): int
{
    if (!empty($blueprint['ready_for_compose']) || (string) ($blueprint['phase'] ?? '') === 'compose') {
        return 100;
    }
    $depth = (int) ($blueprint['narrative_depth'] ?? 0);
    $step = (int) ($blueprint['narrative_step'] ?? 0);

    return min(100, max(0, (int) round(max($depth, $step) / 5 * 100)));
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{
 *   blueprint: array<string, mixed>,
 *   message: string,
 *   choices: list<array{id: string, label: string}>,
 *   ui_hint: string,
 *   complete: bool,
 *   should_compose: bool
 * }
 */
function eduNarrativeBridgePresentNode(array $blueprint, array $quest, array $script, string $nodeId): array
{
    $node = eduNarrativeBridgeGetNode($script, $nodeId);
    if ($node === null) {
        throw new RuntimeException('Unknown narrative node: ' . $nodeId);
    }

    $message = eduNarrativeBridgeNodeCoachText($script, $node);
    $choices = eduNarrativeBridgeNodeChoices($blueprint, $node);
    $stepIndex = (int) ($node['step_index'] ?? 0);
    $blueprint = eduMergeBlueprint($blueprint, [
        'narrative_node' => $nodeId,
        'narrative_step' => $stepIndex,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => $message,
        'choices' => $choices,
        'ui_hint' => 'narrative_bridge',
        'complete' => !empty($node['terminal']),
        'should_compose' => false,
    ];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{
 *   blueprint: array<string, mixed>,
 *   message: string,
 *   choices: list<array{id: string, label: string}>,
 *   ui_hint: string,
 *   complete: bool,
 *   should_compose: bool
 * }
 */
function eduNarrativeBridgeHandleInit(array $blueprint, array $quest): array
{
    $script = eduNarrativeBridgeLoadScript();
    $phase = (string) ($blueprint['phase'] ?? '');
    $nodeId = trim((string) ($blueprint['narrative_node'] ?? ''));

    if ($phase !== 'narrative_bridge' || $nodeId === '') {
        $blueprint = eduNarrativeBridgeInitBlueprint($blueprint, $script);
        $nodeId = (string) ($blueprint['narrative_node'] ?? $script['start_node']);
    }

    return eduNarrativeBridgePresentNode($blueprint, $quest, $script, $nodeId);
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{
 *   blueprint: array<string, mixed>,
 *   message: string,
 *   choices: list<array{id: string, label: string}>,
 *   ui_hint: string,
 *   complete: bool,
 *   should_compose: bool,
 *   student_label: string
 * }
 */
function eduNarrativeBridgeHandleChoice(array $blueprint, array $quest, string $choiceId): array
{
    $script = eduNarrativeBridgeLoadScript();
    $nodeId = trim((string) ($blueprint['narrative_node'] ?? ''));
    if ($nodeId === '') {
        $init = eduNarrativeBridgeHandleInit($blueprint, $quest);
        $nodeId = (string) ($init['blueprint']['narrative_node'] ?? '');
    }

    $node = eduNarrativeBridgeGetNode($script, $nodeId);
    if ($node === null) {
        throw new RuntimeException('Invalid narrative state');
    }

    $choice = eduNarrativeBridgeFindChoice($node, $choiceId);
    if ($choice === null) {
        throw new InvalidArgumentException('Invalid narrative choice');
    }

    $studentLabel = trim((string) ($choice['label'] ?? $choiceId));
    $log = is_array($blueprint['narrative_choices'] ?? null) ? $blueprint['narrative_choices'] : [];
    $log[] = [
        'node' => $nodeId,
        'choice_id' => $choiceId,
        'label' => $studentLabel,
    ];

    $depth = (int) ($blueprint['narrative_depth'] ?? 0) + 1;
    $nextId = trim((string) ($choice['next'] ?? ''));
    if ($nextId === '') {
        throw new RuntimeException('Narrative choice missing next node');
    }

    $nextNode = eduNarrativeBridgeGetNode($script, $nextId);
    if ($nextNode === null) {
        throw new RuntimeException('Unknown narrative next node: ' . $nextId);
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'narrative_choices' => $log,
        'narrative_depth' => $depth,
        'last_choice_id' => $choiceId,
    ]);

    if ($choiceId === 'go_compose' || !empty($nextNode['terminal'])) {
        $blueprint = eduNarrativeBridgeBuildComposeBlueprint($blueprint, $quest);
        return [
            'blueprint' => $blueprint,
            'message' => '좋아! 아래 구조대로 네 생각을 글로 정리해볼게. 잠시만 기다려줘.',
            'choices' => [],
            'ui_hint' => 'compose',
            'complete' => true,
            'should_compose' => true,
            'student_label' => $studentLabel,
        ];
    }

    $present = eduNarrativeBridgePresentNode($blueprint, $quest, $script, $nextId);
    $present['student_label'] = $studentLabel;

    return $present;
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest @return array<string, mixed> */
function eduNarrativeBridgeBuildComposeBlueprint(array $blueprint, array $quest): array
{
    $log = is_array($blueprint['narrative_choices'] ?? null) ? $blueprint['narrative_choices'] : [];
    $labels = array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $log);
    $labels = array_values(array_filter($labels, static fn (string $v): bool => $v !== ''));

    $stance = null;
    foreach ($log as $row) {
        $id = (string) ($row['choice_id'] ?? '');
        if ($id === 'want_nuclear') {
            $stance = 'pro';
            break;
        }
        if ($id === 'no_nuclear') {
            $stance = 'con';
            break;
        }
    }

    $reason = $labels !== [] ? implode(' → ', $labels) : '핵 억지력과 드론 시대의 안전';
    $reflectionLines = [
        '처음 생각과 드론 이야기 이후 생각을 비교해 봤어.',
        '강한 무기가 늘 최강은 아닐 수 있다는 걸 스스로 따졌어.',
    ];

    return eduMergeBlueprint($blueprint, [
        'phase' => 'compose',
        'stance' => $stance,
        'final_stance' => $stance,
        'reason' => $reason,
        'evidence' => $reason,
        'counter_handled' => true,
        'reflection_lines' => $reflectionLines,
        'reflection_confirmed' => true,
        'ready_for_compose' => true,
        'narrative_complete' => true,
    ]);
}

/**
 * @param array<string, mixed> $response
 * @param array<string, mixed> $blueprint
 * @param list<array{id: string, label: string}> $choices
 * @return array<string, mixed>
 */
function eduNarrativeBridgeAttachResponseFields(array $response, array $blueprint, array $choices, string $message): array
{
    $options = array_map(static fn (array $c): string => (string) ($c['label'] ?? ''), $choices);
    $options = array_values(array_filter($options, static fn (string $v): bool => $v !== ''));

    return array_merge($response, [
        'choice_question' => $options !== [],
        'options' => $options,
        'choice_question_text' => $message,
        'narrative_step' => (int) ($blueprint['narrative_step'] ?? 0),
        'narrative_choices' => $choices,
        'narrative_complete' => !empty($blueprint['narrative_complete']),
        'coach_mode' => EDU_NARRATIVE_BRIDGE_MODE,
    ]);
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @param list<array<string, mixed>> $dialogue
 */
function eduNarrativeBridgeRestoreChoiceMeta(array $blueprint, array $quest, array $dialogue): ?array
{
    if ((string) ($blueprint['phase'] ?? '') !== 'narrative_bridge') {
        return null;
    }
    if (!empty($blueprint['ready_for_compose'])) {
        return null;
    }

    $script = eduNarrativeBridgeLoadScript();
    $nodeId = trim((string) ($blueprint['narrative_node'] ?? $script['start_node']));
    $node = eduNarrativeBridgeGetNode($script, $nodeId);
    if ($node === null) {
        return null;
    }

    $message = eduNarrativeBridgeNodeCoachText($script, $node);
    $choices = eduNarrativeBridgeNodeChoices($blueprint, $node);

    return eduNarrativeBridgeAttachResponseFields([], $blueprint, $choices, $message);
}
