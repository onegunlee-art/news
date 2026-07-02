<?php
/**
 * 630 전용 narrative_bridge_v2 — 생각판 6층 + 10~15턴
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';

const EDU_NARRATIVE_V2_QUEST_CODE = 'Q-AUTO-NUKE-630';
const EDU_NARRATIVE_V2_MODE = 'narrative_bridge_v2';
const EDU_NARRATIVE_V2_SCRIPT = 'docs/coach_scripts/630_narrative_v2.json';
const EDU_NARRATIVE_V2_PHASE = 'narrative_bridge_v2';

/** @param array<string, mixed> $quest */
function eduQuestUsesNarrativeV2(array $quest): bool
{
    if (($quest['quest_code'] ?? '') !== EDU_NARRATIVE_V2_QUEST_CODE) {
        return false;
    }
    $hints = eduQuestHammerHints($quest);

    return ($hints['coach_mode'] ?? '') === EDU_NARRATIVE_V2_MODE;
}

/** @return array<string, mixed> */
function eduNarrativeV2LoadScript(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }
    $path = eduFindProjectRoot() . EDU_NARRATIVE_V2_SCRIPT;
    if (!is_file($path)) {
        throw new RuntimeException('Narrative v2 script missing');
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || empty($raw['nodes']) || empty($raw['start_node'])) {
        throw new RuntimeException('Invalid narrative v2 script');
    }
    $cached = $raw;

    return $cached;
}

/** @param array<string, mixed> $script @return list<array<string, mixed>> */
function eduNarrativeV2DefaultThoughtBoard(array $script): array
{
    $layers = $script['layers'] ?? [];
    if (!is_array($layers)) {
        return [];
    }
    $board = [];
    foreach ($layers as $layerId => $def) {
        if (!is_array($def)) {
            continue;
        }
        $board[] = [
            'layer_id' => (string) $layerId,
            'index' => (int) ($def['index'] ?? 0),
            'label' => (string) ($def['label'] ?? $layerId),
            'heading' => (string) ($def['heading'] ?? ''),
            'scqa_key' => (string) ($def['scqa_key'] ?? ''),
            'role' => (string) ($def['role'] ?? ''),
            'text' => '',
            'filled' => false,
            'turn' => null,
        ];
    }
    usort($board, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

    return $board;
}

/** @param array<string, mixed> $script */
function eduNarrativeV2GetNode(array $script, string $nodeId): ?array
{
    $nodes = $script['nodes'] ?? [];
    if (!is_array($nodes) || !isset($nodes[$nodeId]) || !is_array($nodes[$nodeId])) {
        return null;
    }

    return $nodes[$nodeId];
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $script */
function eduNarrativeV2InitBlueprint(array $blueprint, array $script): array
{
    return eduMergeBlueprint($blueprint, [
        'phase' => EDU_NARRATIVE_V2_PHASE,
        'narrative_v2_node' => (string) ($script['start_node'] ?? 'n_intro'),
        'narrative_turn_count' => 0,
        'thought_board' => eduNarrativeV2DefaultThoughtBoard($script),
        'narrative_v2_log' => [],
        'opening_submitted' => true,
        'narrative_version' => 'v2',
    ]);
}

/** @param list<array<string, mixed>> $board */
function eduNarrativeV2FilledLayerCount(array $board): int
{
    $n = 0;
    foreach ($board as $row) {
        if (!empty($row['filled'])) {
            $n++;
        }
    }

    return $n;
}

/** @param array<string, mixed> $blueprint */
function eduNarrativeV2Progress(array $blueprint): int
{
    if (!empty($blueprint['ready_for_compose']) || (string) ($blueprint['phase'] ?? '') === 'compose') {
        return 100;
    }
    $board = is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : [];
    $filled = eduNarrativeV2FilledLayerCount($board);
    $turns = (int) ($blueprint['narrative_turn_count'] ?? 0);
    $layerPct = (int) round($filled / 6 * 85);
    $turnPct = (int) min(10, round($turns / 12 * 10));

    return min(99, max(0, $layerPct + $turnPct));
}

/** @param array<string, mixed> $node @return list<array{id: string, label: string}> */
function eduNarrativeV2NodeChoices(array $node): array
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

/** @param array<string, mixed> $node */
function eduNarrativeV2FindChoice(array $node, string $choiceId): ?array
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

/**
 * @param list<array<string, mixed>> $board
 * @param array<string, mixed> $emit
 */
function eduNarrativeV2EmitCard(array &$board, array $emit, int $turn, string $fallbackText = ''): ?string
{
    $layerId = (string) ($emit['layer'] ?? '');
    $text = trim((string) ($emit['text'] ?? $fallbackText));
    if ($layerId === '' || $text === '') {
        return null;
    }
    foreach ($board as &$row) {
        if (($row['layer_id'] ?? '') !== $layerId || !empty($row['filled'])) {
            continue;
        }
        $row['text'] = $text;
        $row['filled'] = true;
        $row['turn'] = $turn;

        return $layerId;
    }
    unset($row);

    return null;
}

/** @param list<array<string, mixed>> $board @return array{first: string, latest: string, lines: list<string>}|null */
function eduNarrativeV2BoardDiff(array $board): ?array
{
    $filled = array_values(array_filter($board, static fn (array $r): bool => !empty($r['filled'])));
    if (count($filled) < 2) {
        return null;
    }
    $stance = null;
    $refine = null;
    foreach ($filled as $row) {
        if (($row['layer_id'] ?? '') === 'stance') {
            $stance = (string) ($row['text'] ?? '');
        }
        if (($row['layer_id'] ?? '') === 'refine') {
            $refine = (string) ($row['text'] ?? '');
        }
    }
    $lines = [];
    foreach ($filled as $row) {
        $lines[] = ($row['index'] ?? '') . '. ' . ($row['heading'] ?? '') . ' — ' . ($row['text'] ?? '');
    }

    return [
        'first' => $stance !== '' ? $stance : (string) ($filled[0]['text'] ?? ''),
        'latest' => $refine !== '' ? $refine : (string) ($filled[count($filled) - 1]['text'] ?? ''),
        'lines' => $lines,
    ];
}

/** @param array<string, mixed> $node @param array<string, mixed> $script */
function eduNarrativeV2NodeCoachText(array $node, array $script, array $blueprint): string
{
    $text = trim((string) ($node['coach_text'] ?? ''));
    if (!empty($node['board_diff'])) {
        $diff = eduNarrativeV2BoardDiff(is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : []);
        if ($diff !== null && $diff['first'] !== '' && $diff['latest'] !== '') {
            $text .= "\n\n📌 처음: \"" . $diff['first'] . "\"\n→ 지금: \"" . $diff['latest'] . '"';
        }
    }

    return $text;
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array<string, mixed>
 */
function eduNarrativeV2PresentNode(array $blueprint, array $quest, array $script, string $nodeId): array
{
    $node = eduNarrativeV2GetNode($script, $nodeId);
    if ($node === null) {
        throw new RuntimeException('Unknown v2 node: ' . $nodeId);
    }

    $message = eduNarrativeV2NodeCoachText($node, $script, $blueprint);
    $inputMode = (string) ($node['input_mode'] ?? '');
    $choices = $inputMode === 'text' ? [] : eduNarrativeV2NodeChoices($node);
    $pulse = (string) ($node['board_pulse'] ?? '');

    $blueprint = eduMergeBlueprint($blueprint, [
        'narrative_v2_node' => $nodeId,
        'board_pulse_layer' => $pulse !== '' ? $pulse : null,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => $message,
        'choices' => $choices,
        'input_mode' => $inputMode,
        'ui_hint' => 'narrative_v2',
        'should_compose' => false,
        'board_pulse_layer' => $pulse !== '' ? $pulse : null,
        'board_diff' => !empty($node['board_diff']) ? eduNarrativeV2BoardDiff(is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : []) : null,
    ];
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest */
function eduNarrativeV2HandleInit(array $blueprint, array $quest): array
{
    $script = eduNarrativeV2LoadScript();
    $phase = (string) ($blueprint['phase'] ?? '');
    $nodeId = trim((string) ($blueprint['narrative_v2_node'] ?? ''));
    if ($phase !== EDU_NARRATIVE_V2_PHASE || $nodeId === '') {
        $blueprint = eduNarrativeV2InitBlueprint($blueprint, $script);
        $nodeId = (string) ($blueprint['narrative_v2_node'] ?? $script['start_node']);
    }

    return eduNarrativeV2PresentNode($blueprint, $quest, $script, $nodeId);
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $script
 * @param array<string, mixed> $choice
 */
function eduNarrativeV2ApplyChoiceSideEffects(array &$blueprint, array $quest, array $script, array $choice, string $studentLabel): ?string
{
    $turn = (int) ($blueprint['narrative_turn_count'] ?? 0) + 1;
    $board = is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : eduNarrativeV2DefaultThoughtBoard($script);
    $pulse = null;

    $emit = $choice['emit_card'] ?? null;
    if (is_array($emit)) {
        $pulse = eduNarrativeV2EmitCard($board, $emit, $turn, $studentLabel);
    }

    $log = is_array($blueprint['narrative_v2_log'] ?? null) ? $blueprint['narrative_v2_log'] : [];
    $log[] = [
        'turn' => $turn,
        'choice_id' => (string) ($choice['id'] ?? ''),
        'label' => $studentLabel,
        'node' => (string) ($blueprint['narrative_v2_node'] ?? ''),
    ];

    $blueprint = eduMergeBlueprint($blueprint, [
        'thought_board' => $board,
        'narrative_v2_log' => $log,
        'narrative_turn_count' => $turn,
        'last_choice_id' => (string) ($choice['id'] ?? ''),
    ]);

    return $pulse;
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest */
function eduNarrativeV2HandleChoice(array $blueprint, array $quest, string $choiceId): array
{
    $script = eduNarrativeV2LoadScript();
    $nodeId = trim((string) ($blueprint['narrative_v2_node'] ?? ''));
    if ($nodeId === '') {
        $init = eduNarrativeV2HandleInit($blueprint, $quest);
        $nodeId = (string) ($init['blueprint']['narrative_v2_node'] ?? '');
        $blueprint = $init['blueprint'];
    }

    $node = eduNarrativeV2GetNode($script, $nodeId);
    if ($node === null) {
        throw new RuntimeException('Invalid v2 state');
    }

    $choice = eduNarrativeV2FindChoice($node, $choiceId);
    if ($choice === null) {
        throw new InvalidArgumentException('Invalid narrative v2 choice');
    }

    $studentLabel = trim((string) ($choice['label'] ?? $choiceId));
    $pulse = eduNarrativeV2ApplyChoiceSideEffects($blueprint, $quest, $script, $choice, $studentLabel);

    $nextId = trim((string) ($choice['next'] ?? ''));
    if ($choiceId === 'go_compose' || $nextId === 'n_compose') {
        $blueprint = eduNarrativeV2BuildComposeBlueprint($blueprint, $quest);
        return [
            'blueprint' => $blueprint,
            'message' => '좋아! 생각판을 바탕으로 글을 써볼게. 잠시만 기다려줘.',
            'choices' => [],
            'input_mode' => '',
            'ui_hint' => 'compose',
            'should_compose' => true,
            'student_label' => $studentLabel,
            'board_pulse_layer' => 'synthesis',
        ];
    }

    if ($nextId === '') {
        throw new RuntimeException('Choice missing next node');
    }

    $present = eduNarrativeV2PresentNode($blueprint, $quest, $script, $nextId);
    if ($pulse !== null && empty($present['board_pulse_layer'])) {
        $present['board_pulse_layer'] = $pulse;
        $present['blueprint']['board_pulse_layer'] = $pulse;
    }
    $present['student_label'] = $studentLabel;

    return $present;
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest */
function eduNarrativeV2HandleMessage(array $blueprint, array $quest, string $message): array
{
    $script = eduNarrativeV2LoadScript();
    $nodeId = trim((string) ($blueprint['narrative_v2_node'] ?? ''));
    $node = eduNarrativeV2GetNode($script, $nodeId);
    if ($node === null) {
        throw new RuntimeException('Invalid v2 state');
    }

    $text = trim($message);
    if ($text === '') {
        throw new InvalidArgumentException('message required');
    }

    $turn = (int) ($blueprint['narrative_turn_count'] ?? 0) + 1;
    $board = is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : eduNarrativeV2DefaultThoughtBoard($script);
    $emit = $node['emit_card_on_message'] ?? null;
    $pulse = null;
    if (is_array($emit)) {
        $pulse = eduNarrativeV2EmitCard($board, array_merge($emit, ['text' => $text]), $turn, $text);
    }

    $log = is_array($blueprint['narrative_v2_log'] ?? null) ? $blueprint['narrative_v2_log'] : [];
    $log[] = ['turn' => $turn, 'message' => $text, 'node' => $nodeId];

    $blueprint = eduMergeBlueprint($blueprint, [
        'thought_board' => $board,
        'narrative_v2_log' => $log,
        'narrative_turn_count' => $turn,
    ]);

    $nextId = trim((string) ($node['next_after_message'] ?? ''));
    if ($nextId === '') {
        throw new RuntimeException('Node missing next_after_message');
    }

    $present = eduNarrativeV2PresentNode($blueprint, $quest, $script, $nextId);
    if ($pulse !== null) {
        $present['board_pulse_layer'] = $pulse;
        $present['blueprint']['board_pulse_layer'] = $pulse;
    }
    $present['student_label'] = $text;

    return $present;
}

/** @param list<array<string, mixed>> $board @return array<string, mixed> */
function eduNarrativeV2ScqaFromBoard(array $board): array
{
    $map = [];
    foreach ($board as $row) {
        if (empty($row['filled'])) {
            continue;
        }
        $key = (string) ($row['scqa_key'] ?? '');
        $text = trim((string) ($row['text'] ?? ''));
        if ($key === '' || $text === '') {
            continue;
        }
        if ($key === 'C' && isset($map['C']) && $map['C'] !== '') {
            $map['C'] .= ' ' . $text;
        } else {
            $map[$key] = $text;
        }
    }

    return [
        'S' => $map['S'] ?? '',
        'C' => $map['C'] ?? '',
        'Q' => $map['Q'] ?? '',
        'A' => $map['A'] ?? '',
        'conclusion' => $map['conclusion'] ?? '',
    ];
}

/** @param list<array<string, mixed>> $board @param array<string, mixed> $quest */
function eduNarrativeV2EssayStructureFromBoard(array $board, array $quest): array
{
    $sections = [];
    foreach ($board as $row) {
        if (empty($row['filled'])) {
            continue;
        }
        $sections[] = [
            'heading' => (string) ($row['heading'] ?? $row['label'] ?? ''),
            'role' => (string) ($row['role'] ?? ''),
            'bullets' => [trim((string) ($row['text'] ?? ''))],
        ];
    }
    $conclusion = '';
    foreach ($board as $row) {
        if (($row['layer_id'] ?? '') === 'synthesis' && !empty($row['filled'])) {
            $conclusion = (string) ($row['text'] ?? '');
            break;
        }
    }

    return [
        'title' => ($quest['quest_title'] ?? '핵 억지') . ' — 나의 탐구',
        'subtitle' => $conclusion !== '' ? $conclusion : '대화로 쌓은 생각',
        'sections' => $sections,
        'conclusion_heading' => '결론',
        'conclusion_bullets' => $conclusion !== '' ? [$conclusion] : [],
        'generated_by' => 'thought_board_v2',
        'student_stance' => 'myth_bust',
    ];
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest */
function eduNarrativeV2BuildComposeBlueprint(array $blueprint, array $quest): array
{
    $board = is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : [];
    $scqa = eduNarrativeV2ScqaFromBoard($board);
    $structure = eduNarrativeV2EssayStructureFromBoard($board, $quest);
    $hero = trim((string) ($scqa['conclusion'] ?? ''));
    if ($hero === '') {
        $hero = trim((string) ($scqa['A'] ?? ''));
    }

    $stance = null;
    foreach ($board as $row) {
        if (($row['layer_id'] ?? '') === 'stance' && !empty($row['filled'])) {
            $t = (string) ($row['text'] ?? '');
            if (str_contains($t, '안전') && !str_contains($t, '없')) {
                $stance = 'pro';
            } elseif (str_contains($t, '없')) {
                $stance = 'con';
            }
            break;
        }
    }

    $reasonParts = array_filter([$scqa['S'] ?? '', $scqa['C'] ?? '']);
    $reason = implode(' — ', $reasonParts);

    return eduMergeBlueprint($blueprint, [
        'phase' => 'compose',
        'stance' => $stance,
        'final_stance' => $stance,
        'reason' => $reason,
        'evidence' => $scqa['C'] ?? $reason,
        'rebuttal' => $scqa['A'] ?? '',
        'counter_argument' => $scqa['Q'] ?? '',
        'counter_handled' => true,
        'reflection_confirmed' => true,
        'ready_for_compose' => true,
        'narrative_complete' => true,
        'scqa_slots' => $scqa,
        'essay_structure' => $structure,
        'hero_sentence_seed' => $hero,
        'reflection_lines' => eduNarrativeV2BoardDiff($board)['lines'] ?? [],
    ]);
}

/** narrative v2 prebuilt structure for GistStyleComposer */
function eduNarrativeV2HasPrebuiltStructure(array $blueprint): bool
{
    $s = $blueprint['essay_structure'] ?? [];

    return is_array($s) && ($s['generated_by'] ?? '') === 'thought_board_v2' && !empty($s['sections']);
}

/**
 * @param array<string, mixed> $response
 * @param list<array{id: string, label: string}> $choices
 * @return array<string, mixed>
 */
function eduNarrativeV2AttachResponseFields(array $response, array $blueprint, array $choices, string $message): array
{
    $options = array_map(static fn (array $c): string => (string) ($c['label'] ?? ''), $choices);
    $board = is_array($blueprint['thought_board'] ?? null) ? $blueprint['thought_board'] : [];

    return array_merge($response, [
        'choice_question' => $options !== [],
        'options' => array_values(array_filter($options)),
        'choice_question_text' => $message,
        'thought_board' => $board,
        'board_pulse_layer' => $blueprint['board_pulse_layer'] ?? null,
        'narrative_turn_count' => (int) ($blueprint['narrative_turn_count'] ?? 0),
        'narrative_v2_input_mode' => (string) ($response['input_mode'] ?? ''),
        'narrative_choices' => $choices,
        'coach_mode' => EDU_NARRATIVE_V2_MODE,
    ]);
}

/** @param array<string, mixed> $blueprint @param list<array<string, mixed>> $dialogue */
function eduNarrativeV2RestoreMeta(array $blueprint, array $quest, array $dialogue): ?array
{
    if ((string) ($blueprint['phase'] ?? '') !== EDU_NARRATIVE_V2_PHASE || !empty($blueprint['ready_for_compose'])) {
        return null;
    }
    $script = eduNarrativeV2LoadScript();
    $nodeId = trim((string) ($blueprint['narrative_v2_node'] ?? $script['start_node']));
    $node = eduNarrativeV2GetNode($script, $nodeId);
    if ($node === null) {
        return null;
    }
    $present = eduNarrativeV2PresentNode($blueprint, $quest, $script, $nodeId);

    return eduNarrativeV2AttachResponseFields([], $present['blueprint'], $present['choices'], $present['message']);
}

/** @param list<array<string, mixed>> $paths @param array<string, mixed> $script */
function eduNarrativeV2EnumeratePaths(array $script, string $nodeId, array $trail = []): array
{
    $node = eduNarrativeV2GetNode($script, $nodeId);
    if ($node === null) {
        return [];
    }
    if (!empty($node['terminal'])) {
        return [$trail];
    }
    if (($node['input_mode'] ?? '') === 'text') {
        $next = trim((string) ($node['next_after_message'] ?? ''));
        if ($next === '') {
            return [$trail];
        }

        return eduNarrativeV2EnumeratePaths($script, $next, array_merge($trail, ['__text__']));
    }
    $paths = [];
    foreach ($node['choices'] ?? [] as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $id = (string) ($choice['id'] ?? '');
        $next = (string) ($choice['next'] ?? '');
        if ($id === '' || $next === '') {
            continue;
        }
        foreach (eduNarrativeV2EnumeratePaths($script, $next, array_merge($trail, [$id])) as $p) {
            $paths[] = $p;
        }
    }

    return $paths;
}

/** @param array<string, mixed> $blueprint @param array<string, mixed> $quest @param list<string> $path */
function eduNarrativeV2SimulatePath(array $blueprint, array $quest, array $path): array
{
    $state = eduNarrativeV2HandleInit($blueprint, $quest);
    $bp = $state['blueprint'];
    foreach ($path as $step) {
        if ($step === '__text__') {
            $result = eduNarrativeV2HandleMessage($bp, $quest, '시뮬레이션 결론 문장입니다.');
        } else {
            $result = eduNarrativeV2HandleChoice($bp, $quest, $step);
        }
        $bp = $result['blueprint'];
    }

    return ['blueprint' => $bp, 'result' => $result ?? []];
}
