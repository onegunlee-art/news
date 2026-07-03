<?php
/**
 * narrative_bridge_v2 — 퀘스트별 6층 대본 생성 (630 FSM 골격 + LLM 전체 대사)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuideNarrativeV2.php';
require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduLlmJson.php';
require_once __DIR__ . '/_llm.php';

/** @return list<string> */
function eduNarrativeV2GoldenFingerprintPhrases(): array
{
    return [
        '1945년 이후',
        '핵을 가진 나라끼리',
        '핵=안전',
        '우리나라도 핵',
        '왜 핵이 있으면 안전하다고',
        '핵 없이도 괜찮다고',
        '핵이 무서워서',
        '가지는 게 안전해',
        '안 가지는 게 나아',
        '핵이 있으면 우리도',
        '핵 없이도 안전',
        '핵이 완전한 답',
        '조건이 있네',
        '처음엔 \'핵=안전\'',
        '드론 수천 대 vs 핵',
        '핵처럼 \'비싸고 강한',
        '그래도 핵은 달라',
        '핵이 제일 무서워',
        '핵이 억지해',
        '핵을 진짜 쏘면',
        '핵은 안전을 주지만',
        '여전히 핵이 최선',
        '처음 \'핵=안전\'에서',
        '억지력은 \'잃을 게 있는',
        '이걸 **\'억지력\'**',
        '건드리면 같이 망한다',
    ];
}

function eduNarrativeV2StripNuclearFalsePositives(string $text): string
{
    $text = preg_replace('/핵심(적)?/u', '', $text) ?? $text;
    $text = preg_replace('/확인/u', '', $text) ?? $text;

    return $text;
}

/** @param array<string, mixed> $ctx */
function eduNarrativeV2IsNuclearTopic(array $ctx): bool
{
    $code = (string) ($ctx['quest_code'] ?? '');
    if (preg_match('/NUKE|630|IRAN-196/i', $code)) {
        return true;
    }

    $hay = eduNarrativeV2StripNuclearFalsePositives(mb_strtolower(implode(' ', [
        (string) ($ctx['quest_title'] ?? ''),
        (string) ($ctx['side_a'] ?? ''),
        (string) ($ctx['side_b'] ?? ''),
        (string) ($ctx['hinge'] ?? ''),
        (string) ($ctx['article_context'] ?? ''),
    ])));

    return (bool) preg_match(
        '/핵(?:무기|억지|전쟁|실험|인| 문제| 위협|보유|개발)|양자(?:무기|기술)|nuclear|nuke/u',
        $hay
    );
}

function eduNarrativeV2TextHasNuclearLeak(string $text): bool
{
    $clean = eduNarrativeV2StripNuclearFalsePositives($text);

    return (bool) preg_match(
        '/핵(?:무기|억지|전쟁|실험|인|=|을|이|은|처럼|만큼|을)?|양자무기|nuclear weapon|\bnuke\b/u',
        $clean
    );
}

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
        'article_context' => eduNarrativeV2ArticleContextFromQuest($quest),
    ];
}

/** @param array<string, mixed> $quest */
function eduNarrativeV2ArticleContextFromQuest(array $quest): string
{
    $parts = [];
    foreach ($quest['articles'] ?? [] as $article) {
        if (!is_array($article)) {
            continue;
        }
        $title = trim((string) ($article['title'] ?? ''));
        $body = trim(strip_tags(html_entity_decode(
            (string) ($article['excerpt'] ?? $article['why_important'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )));
        if ($title === '' && $body === '') {
            continue;
        }
        $parts[] = ($title !== '' ? $title . ': ' : '') . eduNarrativeV2TrimPhrase($body, 400);
    }

    return implode("\n\n", $parts);
}

function eduNarrativeV2TrimPhrase(string $text, int $maxLen): string
{
    $t = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($t) <= $maxLen) {
        return $t;
    }

    return mb_substr($t, 0, $maxLen - 1) . '…';
}

/** @return array<string, mixed> */
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
 * 630 FSM 골격만 유지 — coach_text·label·카드 텍스트 전부 비움.
 *
 * @return array<string, mixed>
 */
function eduNarrativeV2Load630Skeleton(): array
{
    $script = eduNarrativeV2Load630Template();
    unset($script['llm_followup_fallback']);

    foreach ($script['nodes'] as $nid => &$node) {
        if (!is_array($node)) {
            continue;
        }
        $node['coach_text'] = '';
        if (!isset($node['choices']) || !is_array($node['choices'])) {
            continue;
        }
        foreach ($node['choices'] as &$choice) {
            if (!is_array($choice)) {
                continue;
            }
            $choice['label'] = '';
            if (isset($choice['emit_card']) && is_array($choice['emit_card'])) {
                $choice['emit_card']['text'] = '';
            }
        }
        unset($choice);
    }
    unset($node);

    return $script;
}

/** 630 전용 choice id → 일반 id (LLM이 핵 대사로 끌리는 것 방지) */
const EDU_NARRATIVE_V2_ID_REMAP = [
    'nuclear_fear' => 'intro_a',
    'just_luck' => 'intro_b',
    'other_reason' => 'intro_c',
    'want_nuclear' => 'stance_pro',
    'no_nuclear' => 'stance_con',
    'half_half' => 'stance_mid',
];

/**
 * @param array<string, mixed> $script
 * @return array<string, mixed>
 */
function eduNarrativeV2RemapSkeletonChoiceIds(array $script): array
{
    foreach ($script['nodes'] ?? [] as $nid => &$node) {
        if (!is_array($node) || !isset($node['choices']) || !is_array($node['choices'])) {
            continue;
        }
        foreach ($node['choices'] as &$choice) {
            if (!is_array($choice)) {
                continue;
            }
            $id = (string) ($choice['id'] ?? '');
            if (isset(EDU_NARRATIVE_V2_ID_REMAP[$id])) {
                $choice['id'] = EDU_NARRATIVE_V2_ID_REMAP[$id];
            }
        }
        unset($choice);
    }
    unset($node);

    return $script;
}

/** @param array<string, mixed> $script @return array<string, mixed> */
function eduNarrativeV2BuildLlmNodeBlueprint(array $script): array
{
    $blueprint = [];
    foreach ($script['nodes'] ?? [] as $nid => $node) {
        if (!is_array($node)) {
            continue;
        }
        $entry = ['layer' => (string) ($node['layer'] ?? '')];
        if (!empty($node['input_mode'])) {
            $entry['input_mode'] = (string) $node['input_mode'];
        }
        if (!empty($node['board_pulse'])) {
            $entry['board_pulse'] = true;
        }
        if (!empty($node['board_diff'])) {
            $entry['board_diff'] = true;
        }
        if (!empty($node['terminal'])) {
            $entry['terminal'] = true;
        }
        if (isset($node['choices']) && is_array($node['choices'])) {
            $entry['choices'] = [];
            foreach ($node['choices'] as $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                $ce = ['id' => (string) ($choice['id'] ?? '')];
                if (isset($choice['emit_card']['layer'])) {
                    $ce['emit_card_layer'] = (string) $choice['emit_card']['layer'];
                }
                if (!empty($choice['llm_followup'])) {
                    $ce['llm_followup'] = true;
                }
                $entry['choices'][] = $ce;
            }
        }
        $blueprint[(string) $nid] = $entry;
    }

    return $blueprint;
}

/**
 * @param array<string, mixed> $script
 * @param array<string, mixed> $llmNodes
 * @return list<string>
 */
function eduNarrativeV2ApplyLlmNodeTexts(array &$script, array $llmNodes): array
{
    $errors = [];
    foreach ($script['nodes'] ?? [] as $nid => $skeleton) {
        if (!is_array($skeleton)) {
            continue;
        }
        $llmNode = $llmNodes[$nid] ?? null;
        if (!is_array($llmNode)) {
            $errors[] = "missing_node:{$nid}";
            continue;
        }

        $coach = trim((string) ($llmNode['coach_text'] ?? ''));
        if ($coach === '') {
            $errors[] = "empty_coach:{$nid}";
            continue;
        }
        $script['nodes'][$nid]['coach_text'] = $coach;

        if (!isset($skeleton['choices']) || !is_array($skeleton['choices'])) {
            continue;
        }

        $llmChoices = is_array($llmNode['choices'] ?? null) ? $llmNode['choices'] : [];
        $byId = [];
        foreach ($llmChoices as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            $id = (string) ($choice['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $choice;
            }
        }

        foreach ($script['nodes'][$nid]['choices'] as $i => &$choice) {
            if (!is_array($choice)) {
                continue;
            }
            $id = (string) ($choice['id'] ?? '');
            $llmChoice = $byId[$id] ?? null;
            if (!is_array($llmChoice)) {
                $errors[] = "missing_choice:{$nid}:{$id}";
                continue;
            }
            $label = trim((string) ($llmChoice['label'] ?? ''));
            if ($label === '') {
                $errors[] = "empty_label:{$nid}:{$id}";
                continue;
            }
            $choice['label'] = $label;

            if (isset($choice['emit_card']) && is_array($choice['emit_card'])) {
                $cardText = trim((string) (
                    $llmChoice['emit_card_text']
                    ?? ($llmChoice['emit_card']['text'] ?? '')
                ));
                if ($cardText === '') {
                    $cardText = eduNarrativeV2FallbackEmitCardText(
                        (string) ($choice['emit_card']['layer'] ?? ''),
                        $label
                    );
                }
                if ($cardText === '') {
                    $errors[] = "empty_emit_card:{$nid}:{$id}";
                } else {
                    $choice['emit_card']['text'] = $cardText;
                }
            }
        }
        unset($choice);
    }

    return $errors;
}

function eduNarrativeV2FallbackEmitCardText(string $layer, string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return '';
    }
    $prefix = match ($layer) {
        'stance' => '① 입장: ',
        'reason' => '② 근거: ',
        'depth' => '③ 조건: ',
        'counter' => '④ 반론: ',
        'refine' => '⑤ 재정립: ',
        'synthesis' => '⑥ 결론: ',
        default => '',
    };

    return $prefix . eduNarrativeV2TrimPhrase($label, 60);
}

function eduNarrativeV2GenerateSystemPrompt(): string
{
    return <<<'PROMPT'
당신은 the gist EDU narrative_bridge_v2 코치 대본 작성기입니다.

★ 630(핵 억지) 예시는 **FSM 형식·턴 흐름 참고만**. 내용·표현·선택지 문구를 복붙하면 실패입니다.

★ 철학 (반드시):
- 반론(counter) = **흔들기** — 질문으로 던짐. 정답·결론·"~해야 한다" 금지.
- 코치는 학생 결론을 대신 말하지 않음.
- side_a = 통념/질문 프레임, side_b = 본문이 드러내는 복잡한 진실.
- ①입장 ②근거 ③깊이 ④반론 ⑤재정립 ⑥종합 — 6층 생각판.

★ 오염 금지 (이 퀘스트 주제가 핵이 아니면 절대 사용 금지):
- 핵, 억지력, 드론 vs 핵, 1945년, "핵=안전", "조건이 있네", "우리나라도 핵", "가지는 게 안전해"

난이도(L1~L5): L1~2는 짧고 구체적, L4~L5는 조건·예외 질문을 한 단계 더.

blueprint의 **모든 node_id**에 대해 coach_text를 작성하세요.
choices가 있는 노드는 **모든 choice id**에 label을 작성하세요.
emit_card_layer가 있는 choice는 emit_card_text(생각판 카드 한 줄)도 작성하세요.
input_mode=text 노드는 coach_text만 작성(학생 자유 입력).
counter layer 노드는 coach_text에 반드시 ? 포함.

JSON만 출력:
{
  "nodes": {
    "n_intro": {
      "coach_text": "...",
      "choices": [{"id":"...","label":"..."}]
    },
    "n_stance_card": {
      "coach_text": "...",
      "choices": [{"id":"...","label":"...","emit_card_text":"..."}]
    }
  },
  "llm_followup_fallback": "학생 자유입력 후 코치 한 줄",
  "philosophy_notes": "작성 시 지킨 점 한 줄"
}
PROMPT;
}

/** @param array<string, mixed> $ctx @param array<string, mixed> $blueprint */
function eduNarrativeV2BuildLlmUserPrompt(array $ctx, array $blueprint): string
{
    $fewShotFormat = <<<'FMT'
630 few-shot — **형식만** (내용 복붙 금지):
- intro: 역사적 사실 2~3문장 → "왜 ~?" 질문
- stance: 입장 고르게 → emit_card로 ① 입장
- reason/depth: 왜/조건 따지기 → ②③ 카드
- counter: side_b로 흔들기(?) → ④ 카드
- refine/synthesis: 판 보고 정리 → ⑤⑥
FMT;

    $blueprintJson = json_encode($blueprint, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return <<<USER
{$fewShotFormat}

퀘스트 (이 내용으로 **전 노드** 대사·선택지·카드를 새로 작성):
- quest_code: {$ctx['quest_code']}
- title: {$ctx['quest_title']}
- difficulty_level: L{$ctx['difficulty_level']}
- side_a: {$ctx['side_a']}
- side_b: {$ctx['side_b']}
- hinge: {$ctx['hinge']}
- hook_student: {$ctx['hook_student']}
- shake_prompt: {$ctx['shake_prompt']}

기사 맥락:
{$ctx['article_context']}

FSM blueprint (node_id·choice id·layer — 구조 변경 금지):
{$blueprintJson}
USER;
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $ctx
 * @return array{script: array<string, mixed>, audit: array<string, mixed>, llm_used: bool, apply_errors: list<string>}
 */
function eduNarrativeV2GenerateScriptLlm(array $quest, array $ctx): array
{
    $script = eduNarrativeV2RemapSkeletonChoiceIds(eduNarrativeV2Load630Skeleton());
    $code = (string) ($ctx['quest_code'] ?? $quest['quest_code'] ?? '');
    $script['quest_code'] = $code;
    $script['version'] = EDU_NARRATIVE_V2_MODE;
    $script['generated_by'] = 'narrative_v2_generate_llm_v2';
    $script['hinge_map'] = [
        'side_a' => (string) ($ctx['side_a'] ?? ''),
        'side_b' => (string) ($ctx['side_b'] ?? ''),
    ];

    $blueprint = eduNarrativeV2BuildLlmNodeBlueprint($script);
    $user = eduNarrativeV2BuildLlmUserPrompt($ctx, $blueprint);

    $llm = eduLlm();
    $response = $llm->chat(eduNarrativeV2GenerateSystemPrompt(), [
        ['role' => 'user', 'content' => $user],
    ], 16384, 0.4);

    if (!empty($response['error'])) {
        throw new RuntimeException('LLM error: ' . (string) ($response['message'] ?? $response['error']));
    }

    $parsed = eduParseLlmJson($response);
    if (!is_array($parsed) || !is_array($parsed['nodes'] ?? null)) {
        throw new RuntimeException('LLM JSON parse failed');
    }

    $applyErrors = eduNarrativeV2ApplyLlmNodeTexts($script, $parsed['nodes']);
    $fallback = trim((string) ($parsed['llm_followup_fallback'] ?? ''));
    if ($fallback !== '') {
        $script['llm_followup_fallback'] = $fallback;
    } else {
        $script['llm_followup_fallback'] = '그렇구나. 한 가지만 더 — 그 말을 한 문장으로 정리하면?';
    }
    $script['llm_philosophy_notes'] = trim((string) ($parsed['philosophy_notes'] ?? ''));

    $audit = eduNarrativeV2FullAudit($script, $ctx);

    return [
        'script' => $script,
        'audit' => $audit,
        'llm_used' => true,
        'apply_errors' => $applyErrors,
    ];
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $ctx
 * @return array{script: array<string, mixed>, audit: array<string, mixed>, llm_used: bool}
 */
function eduNarrativeV2GenerateScript(array $quest, array $ctx, bool $useLlm = true): array
{
    if (!$useLlm) {
        throw new InvalidArgumentException(
            'rule-based generation is disabled (630 template copy caused contamination). Use --llm.'
        );
    }

    $lastError = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $result = eduNarrativeV2GenerateScriptLlm($quest, $ctx);
            if ($result['apply_errors'] !== []) {
                throw new RuntimeException(
                    'LLM apply incomplete: ' . implode(', ', array_slice($result['apply_errors'], 0, 5))
                );
            }
            if (empty($result['audit']['contamination_ok'])) {
                throw new RuntimeException(
                    '630 contamination: ' . implode(', ', $result['audit']['contamination_flags'] ?? [])
                );
            }
            if (empty($result['audit']['philosophy_ok'])) {
                throw new RuntimeException(
                    'philosophy flags: ' . implode(', ', $result['audit']['flags'] ?? [])
                );
            }

            return [
                'script' => $result['script'],
                'audit' => $result['audit'],
                'llm_used' => true,
            ];
        } catch (Throwable $e) {
            $lastError = $e;
            if ($attempt < 2) {
                usleep(500000);
            }
        }
    }

    throw $lastError ?? new RuntimeException('LLM generation failed');
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

/** @param array<string, mixed> $script @param array<string, mixed> $ctx @return array<string, mixed> */
function eduNarrativeV2ContaminationAudit(array $script, array $ctx = []): array
{
    $flags = [];
    $isNuclear = eduNarrativeV2IsNuclearTopic($ctx);
    $texts = [];

    foreach ($script['nodes'] ?? [] as $node) {
        if (!is_array($node)) {
            continue;
        }
        $texts[] = (string) ($node['coach_text'] ?? '');
        foreach ($node['choices'] ?? [] as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            $texts[] = (string) ($choice['label'] ?? '');
            if (isset($choice['emit_card']['text'])) {
                $texts[] = (string) $choice['emit_card']['text'];
            }
        }
    }
    if (!empty($script['llm_followup_fallback'])) {
        $texts[] = (string) $script['llm_followup_fallback'];
    }

    $blob = implode("\n", array_filter($texts));

    foreach (eduNarrativeV2GoldenFingerprintPhrases() as $phrase) {
        if ($phrase !== '' && mb_strpos($blob, $phrase) !== false) {
            $flags[] = 'golden_phrase:' . $phrase;
        }
    }

    if (!$isNuclear && eduNarrativeV2TextHasNuclearLeak($blob)) {
        $flags[] = 'unrelated_nuclear:leak';
    }
    if (!$isNuclear && preg_match('/억지력/u', eduNarrativeV2StripNuclearFalsePositives($blob))) {
        $flags[] = 'unrelated_nuclear:억지력';
    }

    return [
        'contamination_ok' => $flags === [],
        'contamination_flags' => $flags,
        'contamination_count' => count($flags),
    ];
}

/** @param array<string, mixed> $script @param array<string, mixed> $ctx @return array<string, mixed> */
function eduNarrativeV2FullAudit(array $script, array $ctx = []): array
{
    $philosophy = eduNarrativeV2PhilosophyAudit($script, $ctx);
    $contamination = eduNarrativeV2ContaminationAudit($script, $ctx);
    $flags = array_merge($philosophy['flags'] ?? [], $contamination['contamination_flags'] ?? []);

    return array_merge($philosophy, $contamination, [
        'flags' => $flags,
        'flag_count' => count($flags),
        'ok' => ($philosophy['philosophy_ok'] ?? false) && ($contamination['contamination_ok'] ?? false),
    ]);
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
    $audit = eduNarrativeV2FullAudit($script, eduNarrativeV2GenerateContextFromQuest($quest));
    if (empty($audit['ok'])) {
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
