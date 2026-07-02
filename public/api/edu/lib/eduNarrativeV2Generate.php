<?php
/**
 * narrative_bridge_v2 — 퀘스트별 6층 대본 생성 (630 few-shot + 철학 가드)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuideNarrativeV2.php';
require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduLlmJson.php';
require_once __DIR__ . '/_llm.php';

/** @return array<string, mixed> */
function eduNarrativeV2GenerateContextFromQuest(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];

    $sideA = trim((string) ($hinge['side_a'] ?? $quest['pro_line'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? $quest['con_line'] ?? ''));
    $hingeText = trim((string) ($hinge['hinge'] ?? $quest['conflict_summary'] ?? ''));
    $hook = trim((string) ($hinge['hook_student'] ?? $quest['hook_short'] ?? $quest['quest_title'] ?? ''));
    $shake = trim((string) ($hinge['shake_prompt'] ?? ''));

    if ($shake === '' && $sideB !== '') {
        $shake = '근데 ' . $sideB . ' — 이 관점도 생각해 본 적 있어?';
    }

    return [
        'quest_code' => (string) ($quest['quest_code'] ?? ''),
        'quest_title' => (string) ($quest['quest_title'] ?? ''),
        'side_a' => $sideA,
        'side_b' => $sideB,
        'hinge' => $hingeText,
        'hook_student' => $hook,
        'shake_prompt' => $shake,
        'difficulty_level' => max(1, min(5, (int) ($quest['difficulty_level'] ?? 3))),
    ];
}

function eduNarrativeV2GenerateIntroText(array $ctx): string
{
    $hook = trim((string) ($ctx['hook_student'] ?? ''));
    $hinge = trim((string) ($ctx['hinge'] ?? ''));
    $sideA = trim((string) ($ctx['side_a'] ?? ''));

    $lines = [];
    if ($hook !== '') {
        $lines[] = $hook;
    }
    if ($sideA !== '') {
        $lines[] = '많은 사람은 "' . eduNarrativeV2TrimPhrase($sideA, 80) . '"라고 생각해.';
    }
    if ($hinge !== '') {
        $lines[] = eduNarrativeV2TrimPhrase($hinge, 120);
    }
    $lines[] = '왜 그렇게 생각할까?';

    return implode("\n\n", array_filter($lines));
}

function eduNarrativeV2GenerateStanceBridgeText(array $ctx): string
{
    $sideA = eduNarrativeV2TrimPhrase((string) ($ctx['side_a'] ?? ''), 70);
    $title = eduNarrativeV2TrimPhrase((string) ($ctx['quest_title'] ?? '이 이슈'), 50);

    return "좋아, {$sideA}에서 시작해보자.\n{$title} — 네 입장은 어느 쪽에 가까워?";
}

function eduNarrativeV2GenerateCounterShakeText(array $ctx): string
{
    $shake = trim((string) ($ctx['shake_prompt'] ?? ''));
    $sideB = eduNarrativeV2TrimPhrase((string) ($ctx['side_b'] ?? ''), 100);

    if ($shake !== '') {
        return $shake . "\n\n" . $sideB . "\n\n네가 아까 말한 입장 — 이 이야기에도 그대로 맞을까?";
    }

    return "한 가지 더.\n" . $sideB . "\n\n네 입장을 흔들 수 있는 이야기야. 어떻게 생각해?";
}

function eduNarrativeV2TrimPhrase(string $text, int $maxLen): string
{
    $t = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($t) <= $maxLen) {
        return $t;
    }

    return mb_substr($t, 0, $maxLen - 1) . '…';
}

/** @param array<string, mixed> $ctx @return array<string, mixed> */
function eduNarrativeV2Load630Template(): array
{
    $path = eduFindProjectRoot() . EDU_NARRATIVE_V2_SCRIPT;
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        throw new RuntimeException('630 template missing');
    }

    return json_decode(json_encode($raw), true);
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $ctx
 * @return array{script: array<string, mixed>, audit: array<string, mixed>}
 */
function eduNarrativeV2GenerateScriptRuleBased(array $quest, array $ctx): array
{
    $script = eduNarrativeV2Load630Template();
    $code = (string) ($ctx['quest_code'] ?? $quest['quest_code'] ?? '');
    $script['quest_code'] = $code;
    $script['version'] = EDU_NARRATIVE_V2_MODE;
    $script['generated_by'] = 'narrative_v2_generate_rule_v1';
    $script['hinge_map'] = [
        'side_a' => (string) ($ctx['side_a'] ?? ''),
        'side_b' => (string) ($ctx['side_b'] ?? ''),
    ];

    $intro = eduNarrativeV2GenerateIntroText($ctx);
    $stanceBridge = eduNarrativeV2GenerateStanceBridgeText($ctx);
    $counterShake = eduNarrativeV2GenerateCounterShakeText($ctx);

    $nodes = &$script['nodes'];
    if (isset($nodes['n_intro']) && is_array($nodes['n_intro'])) {
        $nodes['n_intro']['coach_text'] = $intro;
        $nodes['n_intro']['choices'] = [
            ['id' => 'nuclear_fear', 'label' => 'A 쪽이 더 와닿아', 'next' => 'n_stance_bridge_fear'],
            ['id' => 'just_luck', 'label' => 'B 쪽도 설명돼', 'next' => 'n_stance_bridge_luck'],
            ['id' => 'other_reason', 'label' => '다른 이유', 'next' => 'n_stance_bridge_other'],
        ];
    }

    foreach (['n_stance_bridge_fear', 'n_stance_bridge_luck', 'n_stance_bridge_other'] as $nid) {
        if (isset($nodes[$nid]) && is_array($nodes[$nid])) {
            $nodes[$nid]['coach_text'] = $stanceBridge;
        }
    }

    if (isset($nodes['n_counter_0']) && is_array($nodes['n_counter_0'])) {
        $nodes['n_counter_0']['coach_text'] = $counterShake;
    }
    if (isset($nodes['n_counter_why']) && is_array($nodes['n_counter_why'])) {
        $nodes['n_counter_why']['coach_text'] = '왜 그렇게 봐? (답 강요 없어 — 네 생각대로)';
    }

    $stancePro = eduNarrativeV2TrimPhrase((string) ($ctx['side_a'] ?? ''), 60);
    $stanceCon = eduNarrativeV2TrimPhrase((string) ($ctx['side_b'] ?? ''), 60);
    eduNarrativeV2GeneratePatchEmitCards($nodes, [
        'stance' => $stancePro !== '' ? $stancePro : '나의 입장',
        'reason' => '근거: ' . eduNarrativeV2TrimPhrase((string) ($ctx['side_a'] ?? ''), 40),
        'depth' => '조건이 있다 — 항상 통하지는 않음',
        'counter' => eduNarrativeV2TrimPhrase((string) ($ctx['side_b'] ?? ''), 50),
        'refine' => '처음 생각보다 정교해졌다',
        'synthesis' => '내 결론 한 문장',
    ]);

    $level = (int) ($ctx['difficulty_level'] ?? 3);
    if ($level <= 2 && isset($nodes['n_depth_0']['coach_text'])) {
        $nodes['n_depth_0']['coach_text'] = "한 걸음 더.\n항상 그렇게만 될까? 예외는 없을까?";
    }
    if ($level >= 4 && isset($nodes['n_depth_0']['coach_text'])) {
        $nodes['n_depth_0']['coach_text'] = "한 걸음 더.\n" . eduNarrativeV2TrimPhrase((string) ($ctx['hinge'] ?? ''), 90) . "\n\n예외 상황은 없을까?";
    }

    $audit = eduNarrativeV2PhilosophyAudit($script, $ctx);

    return ['script' => $script, 'audit' => $audit];
}

/** @param array<string, mixed> $nodes @param array<string, string> $texts */
function eduNarrativeV2GeneratePatchEmitCards(array &$nodes, array $texts): void
{
    foreach ($nodes as &$node) {
        if (!is_array($node)) {
            continue;
        }
        $layer = (string) ($node['layer'] ?? '');
        if ($layer === '' || !isset($texts[$layer])) {
            continue;
        }
        if (!isset($node['choices']) || !is_array($node['choices'])) {
            continue;
        }
        foreach ($node['choices'] as &$choice) {
            if (!is_array($choice) || !isset($choice['emit_card']) || !is_array($choice['emit_card'])) {
                continue;
            }
            if (($choice['emit_card']['layer'] ?? '') === $layer) {
                $choice['emit_card']['text'] = $texts[$layer];
            }
        }
        unset($choice);
    }
    unset($node);
}

function eduNarrativeV2GenerateSystemPrompt(): string
{
    return <<<'PROMPT'
당신은 the gist EDU narrative_bridge_v2 코치 대본 작성기입니다.
630(핵 억지) 예시와 **동일한 6층 FSM 구조**를 유지하되, 주어진 퀘스트에 맞게 coach_text·선택지 라벨만 바꿉니다.

★ 철학 (반드시):
- 반론(counter) = **흔들기** — 질문으로 던짐. 정답·결론·"~해야 한다" 금지.
- 코치는 학생 결론을 대신 말하지 않음.
- side_a = 통념/질문 프레임, side_b = 본문이 드러내는 복잡한 진실.
- ①입장 ②근거 ③깊이 ④반론 ⑤재정립 ⑥종합 — 6층 생각판.

난이도(L1~L5): L1~2는 짧고 구체적, L4~5는 조건·예외 질문을 한 단계 더.

JSON만 출력:
{
  "intro_text": "서사+질문 (3~4문단, 마지막은 ?)",
  "intro_choices": [{"id":"...","label":"..."}, ...],
  "stance_bridge_text": "...",
  "counter_shake_text": "반론 흔들기 — 반드시 ? 포함, shake_prompt 활용",
  "depth_prompt": "...",
  "philosophy_notes": "작성 시 지킨 점 한 줄"
}
PROMPT;
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $ctx
 * @return array{script: array<string, mixed>, audit: array<string, mixed>, llm_used: bool}
 */
function eduNarrativeV2GenerateScript(array $quest, array $ctx, bool $useLlm = true): array
{
    $base = eduNarrativeV2GenerateScriptRuleBased($quest, $ctx);
    if (!$useLlm) {
        return ['script' => $base['script'], 'audit' => $base['audit'], 'llm_used' => false];
    }

    $fewShotIntro = "1945년 이후, 핵을 가진 나라끼리 전면전이 한 번도 없었어.\n...\n\n왜 그랬을까?";
    $fewShotCounter = "최근 이란-이스라엘에서 — ... 값싼 무기가 비싼 무기를 이긴 거야.\n\n핵처럼 '비싸고 강한 무기'는 앞으로도 최강일까?";

    $user = <<<USER
630 few-shot (구조 참고만, 내용 복붙 금지):
intro 예: {$fewShotIntro}
counter 예: {$fewShotCounter}

퀘스트:
- quest_code: {$ctx['quest_code']}
- title: {$ctx['quest_title']}
- difficulty_level: L{$ctx['difficulty_level']}
- side_a: {$ctx['side_a']}
- side_b: {$ctx['side_b']}
- hinge: {$ctx['hinge']}
- hook_student: {$ctx['hook_student']}
- shake_prompt: {$ctx['shake_prompt']}
USER;

    $llm = eduLlm();
    $response = $llm->chat(eduNarrativeV2GenerateSystemPrompt(), [
        ['role' => 'user', 'content' => $user],
    ], 4096, 0.35);

    $parsed = eduParseLlmJson($response);
    if (!is_array($parsed)) {
        return ['script' => $base['script'], 'audit' => $base['audit'], 'llm_used' => false];
    }

    $script = $base['script'];
    $nodes = &$script['nodes'];
    if (!empty($parsed['intro_text']) && isset($nodes['n_intro'])) {
        $nodes['n_intro']['coach_text'] = trim((string) $parsed['intro_text']);
    }
    if (!empty($parsed['intro_choices']) && is_array($parsed['intro_choices']) && isset($nodes['n_intro']['choices'])) {
        $merged = [];
        $defaults = $nodes['n_intro']['choices'];
        foreach ($parsed['intro_choices'] as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $merged[] = [
                'id' => (string) ($c['id'] ?? ($defaults[$i]['id'] ?? 'opt_' . $i)),
                'label' => (string) ($c['label'] ?? ($defaults[$i]['label'] ?? '선택')),
                'next' => (string) ($defaults[$i]['next'] ?? 'n_stance_bridge_fear'),
            ];
        }
        if ($merged !== []) {
            $nodes['n_intro']['choices'] = $merged;
        }
    }
    if (!empty($parsed['stance_bridge_text'])) {
        foreach (['n_stance_bridge_fear', 'n_stance_bridge_luck', 'n_stance_bridge_other'] as $nid) {
            if (isset($nodes[$nid])) {
                $nodes[$nid]['coach_text'] = trim((string) $parsed['stance_bridge_text']);
            }
        }
    }
    if (!empty($parsed['counter_shake_text']) && isset($nodes['n_counter_0'])) {
        $nodes['n_counter_0']['coach_text'] = trim((string) $parsed['counter_shake_text']);
    }
    if (!empty($parsed['depth_prompt']) && isset($nodes['n_depth_0'])) {
        $nodes['n_depth_0']['coach_text'] = trim((string) $parsed['depth_prompt']);
    }
    $script['generated_by'] = 'narrative_v2_generate_llm_v1';
    $script['llm_philosophy_notes'] = trim((string) ($parsed['philosophy_notes'] ?? ''));

    $audit = eduNarrativeV2PhilosophyAudit($script, $ctx);

    return ['script' => $script, 'audit' => $audit, 'llm_used' => true];
}

/** @param array<string, mixed> $script @param array<string, mixed> $ctx @return array<string, mixed> */
function eduNarrativeV2PhilosophyAudit(array $script, array $ctx = []): array
{
    $flags = [];
    $forbidden = ['정답', '결론은', '틀렸', '옳다고', '맞다고 단정'];
    $shakeCounterNodes = ['n_counter_0', 'n_counter_why', 'n_counter_still', 'n_counter_drone', 'n_counter_hard'];
    $nodes = is_array($script['nodes'] ?? null) ? $script['nodes'] : [];

    foreach ($nodes as $nid => $node) {
        if (!is_array($node)) {
            continue;
        }
        $layer = (string) ($node['layer'] ?? '');
        $text = (string) ($node['coach_text'] ?? '');

        if ($layer === 'counter' && in_array((string) $nid, $shakeCounterNodes, true)) {
            foreach ($forbidden as $word) {
                if ($word !== '' && mb_strpos($text, $word) !== false) {
                    $flags[] = "counter_forbidden:{$nid}:{$word}";
                }
            }
            if (!preg_match('/[?？]/u', $text)) {
                $flags[] = "counter_no_question:{$nid}";
            }
        }

        if ($nid === 'n_intro' && $text !== '' && !preg_match('/[?？]/u', $text)) {
            $flags[] = 'intro_no_question';
        }
    }

    return [
        'philosophy_ok' => $flags === [],
        'flags' => $flags,
        'flag_count' => count($flags),
        'quest_code' => (string) ($script['quest_code'] ?? ''),
        'difficulty_level' => (int) ($ctx['difficulty_level'] ?? 0),
    ];
}

function eduNarrativeV2GenerateScriptPath(string $questCode): string
{
    return eduNarrativeV2ScriptPath($questCode);
}

/** @param array<string, mixed> $script */
function eduNarrativeV2SaveGeneratedScript(array $script): string
{
    $code = (string) ($script['quest_code'] ?? '');
    if ($code === EDU_NARRATIVE_V2_QUEST_CODE) {
        throw new InvalidArgumentException('Refusing to overwrite 630 golden script');
    }
    $path = eduNarrativeV2GenerateScriptPath($code);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode($script, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    return $path;
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2ValidateGeneratedScript(array $quest, array $script): bool
{
    if (count($script['layers'] ?? []) !== 6) {
        return false;
    }
    $paths = eduNarrativeV2EnumeratePaths($script, (string) ($script['start_node'] ?? ''));
    if ($paths === []) {
        return false;
    }
    $code = (string) ($script['quest_code'] ?? '');
    if ($code === '') {
        return false;
    }
    $path = eduNarrativeV2GenerateScriptPath($code);
    if (!is_file($path)) {
        return false;
    }
    $q = $quest;
    $hints = eduQuestHammerHints($q);
    $hints['coach_mode'] = EDU_NARRATIVE_V2_MODE;
    $q['hammer_hints'] = $hints;
    try {
        $init = eduNarrativeV2HandleInit(eduBlueprintDefaults(), $q);
    } catch (Throwable) {
        return false;
    }

    return ($init['message'] ?? '') !== '';
}
