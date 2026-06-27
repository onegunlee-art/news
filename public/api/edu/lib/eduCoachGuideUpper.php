<?php
/**
 * EDU coach L4 — 근거+다층 입문 (고1~2). turn에서 근거 요구(떠먹임 X). L3 < L4 < L5.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';

const EDU_COACH_UPPER_AXIS_MAX = 3;

/** @return array<string, array<string, string>> */
function eduCoachGuideUpperAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'upper_question' => '핵이 **재래식 공격**까지 막는지 — **기사 사건** 하나 짚고 말해볼래?',
                'evidence_ask' => '**기사 조각**에서 — 핵 **대신** 재래식만 쓴 **사건**이 뭐였는지 기억나? **나라·무기**만 짧게.',
                'evidence_nudge' => '방금은 **주장**만이야. **기사에 나온 사건·숫자** 하나만 덧붙여줘.',
                'layer_half' => '그 **근거**를 들면 — 반대로 **억지가 약해진다**는 쪽도 있어. **네 말**로 한 줄.',
            ],
            'norms' => [
                'upper_question' => '**약속·규범**이 전쟁을 막았는지 — **기사 사실** 하나 들고 말해볼래?',
                'evidence_ask' => '**기사**에서 — 핵 **시설**을 안 치겠다는 **약속** 관련 **사실** 하나 기억나?',
                'evidence_nudge' => '**주장** 말고 — **기사에 나온 나라·약속** 하나만 짧게.',
                'layer_half' => '그 근거로도 — **약속이 있어도 충돌은 났**다는 쪽이 있어. **네 말**로.',
            ],
            'defense' => [
                'upper_question' => '예산 **한정**이면 — **기사에 나온 선택지** 중 **하나**만 골라 **왜**?',
                'evidence_ask' => '**기사**에서 **드론·방공·기지** 이야기 — **어떤 위협**이었는지 **한 가지**만 짧게.',
                'evidence_nudge' => '**추상** 말고 — **기사 조각**에서 **구체 위협·선택** 하나만.',
                'layer_half' => '그 근거면 — **방공·기지만**으로는 **부족**하다는 쪽도 있어. **네 말**로.',
                'upper_choice_options' => '핵 현대화,방공·기지',
            ],
        ],
        150 => [
            'scale' => [
                'upper_question' => '애슈번 **전력 규모** — **기사 숫자·비교** 하나 들고 말해볼래?',
                'evidence_ask' => '**기사**에서 **데이터센터 개수·전력** — **숫자나 비교** 하나 기억나?',
                'evidence_nudge' => '**주장** 말고 — **기사 숫자·도시 비교** 하나만.',
                'layer_half' => '그 숫자면 — **AI만 탓**하기 **어렵다**는 쪽도 있어. **네 말**로.',
            ],
            'policy' => [
                'upper_question' => '빅테크 **전력 약속** — **기사에 뭐라 했는지** 하나 짚어볼래?',
                'evidence_ask' => '**기사**에서 **누가·무슨 약속**을 했는지 **한 가지**만?',
                'evidence_nudge' => '**추상** 말고 — **기사 인물·약속** 하나만.',
                'layer_half' => '그 약속이 — **요금·DC 탓** 프레임을 **키운다 / 줄인다** — **네 근거**로 한 줄.',
            ],
            'grid_investment' => [
                'upper_question' => '요금 상승 — **AI 탓** vs **송전망** — **기사 근거** 하나 들고 **한쪽**만.',
                'evidence_ask' => '**기사**에서 **송전망·시장** 또는 **AI 수요** — **어느 쪽 근거**가 기억나?',
                'evidence_nudge' => '**기사 조각**에서 **원인** 하나만 짧게.',
                'layer_half' => 'AI만 탓하면 — **송전망·시장**은 **어디에** 끼워? **네 말**로.',
                'upper_choice_options' => 'AI·DC 탓,송전망·시장 탓',
            ],
        ],
        default => [],
    };
}

/**
 * @param array<string, string> $axis
 * @return array<string, string>
 */
function eduCoachGuideUpperAdaptAxis(array $axis, int $newsId): array
{
    $id = (string) ($axis['axis_id'] ?? '');
    $overlay = eduCoachGuideUpperAxisOverlays($newsId)[$id] ?? [];
    if ($overlay === []) {
        return $axis;
    }

    foreach ([
        'upper_question' => 'core_question',
        'evidence_ask' => 'upper_evidence_ask',
        'evidence_nudge' => 'upper_evidence_nudge',
        'layer_half' => 'upper_layer_half',
    ] as $from => $to) {
        if (!empty($overlay[$from])) {
            $axis[$to] = $overlay[$from];
        }
    }
    if (!empty($overlay['upper_question'])) {
        $axis['core_question'] = $overlay['upper_question'];
    }
    if (!empty($overlay['upper_choice_options'])) {
        $axis['choice_options'] = array_map('trim', explode(',', $overlay['upper_choice_options']));
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideUpperAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $slice = array_slice(eduCoachGuideAxes($quest), 0, EDU_COACH_UPPER_AXIS_MAX);
    $adapted = [];
    foreach ($slice as $axis) {
        $adapted[] = eduCoachGuideUpperAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideUpperMessageHasEvidence(string $message, array $axis): bool
{
    $m = trim($message);
    if ($m === '') {
        return false;
    }
    if (preg_match('/\d/u', $m)) {
        return true;
    }
    if (preg_match('/(우크라|러시아|인도|파키|이란|드론|미사일|기지|핵|데이터|센터|애슈번|트럼프|송전|전력|2024|2025|400|150)/ui', $m)) {
        return true;
    }
    $fact = trim((string) ($axis['article_fact'] ?? ''));
    if ($fact !== '' && eduCoachGuideTextsOverlap($m, $fact)) {
        return true;
    }

    return mb_strlen(preg_replace('/\s+/u', '', $m) ?? $m) >= 28;
}

/** @param array<string, string> $axis */
function eduCoachGuideUpperIntroAxis(
    array $axis,
    int $index,
    string $openingContext = '',
    string $hookShort = ''
): string {
    $point = $axis['point'] ?? '';
    $fact = eduCoachGuideFactForDisplay($axis);
    $q = $axis['core_question'] ?? '';
    $snippetBlock = $fact !== '' ? "\n\n" . eduCoachGuideWrapArticleSnippet($fact) : '';

    $hookOverlap = $index === 0 && (
        ($hookShort !== '' && eduCoachGuideTextsOverlap($hookShort, $q))
        || ($openingContext !== '' && eduCoachGuideTextsOverlap($openingContext, $q))
    );

    if ($hookOverlap) {
        return '방금 말한 걸 **근거**로 받쳐보자. **' . $point . '**' . $snippetBlock
            . "\n\n" . ($axis['upper_evidence_ask'] ?? $q);
    }

    $lead = $index === 0 ? '근거부터 — **기사 사실** 하나 들고.' : '다음 — **근거**로.';

    return "{$lead} **{$point}**{$snippetBlock}\n\n{$q}";
}

/** @param array<string, string> $axis */
function eduCoachGuideUpperEvasionReply(?string $evasion, array $axis, string $mode): string
{
    return match ($mode) {
        'evidence' => (string) ($axis['upper_evidence_ask'] ?? $axis['core_question'] ?? '기사 **사건·숫자** 하나만.'),
        'nudge' => (string) ($axis['upper_evidence_nudge'] ?? '**기사 조각**에서 **구체 사실** 하나만 덧붙여줘.'),
        'layer' => (string) ($axis['upper_layer_half'] ?? '그 **근거**로 — **반대쪽**도 한 줄.'),
        default => match ($evasion) {
            'both', 'list' => '**하나**만 — 지금 축에서 **한쪽** + **기사 근거** 하나.',
            'unknown' => '모르겠으면 — **기사 조각**에서 **기억나는 사실** 하나만 짧게.',
            'deflect' => '**이 기사**만 — **사건·숫자** 하나 짚어줘.',
            'defer_article' => '**어느 부분**? **사건·숫자** 하나만.',
            'ask_conclusion' => '결론은 **내가 안 줘**. **기사 근거** 하나 + **네 생각**.',
            null, 'empty' => (string) ($axis['upper_evidence_ask'] ?? $axis['core_question'] ?? ''),
            default => (string) ($axis['core_question'] ?? ''),
        },
    };
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideUpperHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_L4
): array {
    $axes = eduCoachGuideUpperAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => EDU_COACH_LEVEL_L4,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideUpperEvasionReply($evasion, $axes[0], 'default');
    } else {
        $msg = eduCoachGuideUpperIntroAxis($axes[0], 0, $opening, $hookShort);
    }

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideUpperHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_L4
): array {
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideUpperAxes($quest);

    if ($phase === 'guide_conclusion') {
        return eduCoachGuideHandleConclusion($blueprint, $quest, $message, $axes);
    }

    if ($phase !== 'guide_axis') {
        return [
            'blueprint' => $blueprint,
            'message' => '계속 이야기해줘.',
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $idx = (int) ($blueprint['guide_axis_index'] ?? 0);
    $stall = (int) ($blueprint['guide_axis_stall'] ?? 0);
    $evasion = eduCoachDetectEvasion($message);
    $axis = $axes[$idx] ?? $axes[0];
    $axisId = (string) ($axis['axis_id'] ?? '');

    $pendingWhy = is_array($blueprint['guide_axis_pending_why'] ?? null)
        ? $blueprint['guide_axis_pending_why']
        : null;
    $pendingEvidence = is_array($blueprint['guide_axis_pending_evidence'] ?? null)
        ? $blueprint['guide_axis_pending_evidence']
        : null;
    $pendingLayer = is_array($blueprint['guide_axis_pending_layer'] ?? null)
        ? $blueprint['guide_axis_pending_layer']
        : null;

    if ($pendingLayer !== null && ($pendingLayer['axis_id'] ?? '') === $axisId) {
        if (eduCoachAxisStudentPass($message, $evasion)) {
            $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
            $stored = trim((string) ($pendingLayer['evidence'] ?? ''));
            $answers[$axisId] = $stored !== '' ? $stored . ' / ' . trim($message) : trim($message);
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_pending_layer' => null,
                'guide_axis_pending_evidence' => null,
                'guide_axis_stall' => 0,
            ]);

            return eduCoachGuideUpperAdvanceAxis($blueprint, $quest, $axes, $idx + 1);
        }

        $stall++;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideUpperEvasionReply($evasion, $axis, 'layer')),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if ($pendingEvidence !== null && ($pendingEvidence['axis_id'] ?? '') === $axisId) {
        if (eduCoachGuideUpperMessageHasEvidence($message, $axis) && eduCoachAxisStudentPass($message, $evasion)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_pending_evidence' => null,
                'guide_axis_pending_layer' => [
                    'axis_id' => $axisId,
                    'evidence' => trim($message),
                ],
                'guide_axis_stall' => 0,
            ]);

            return [
                'blueprint' => $blueprint,
                'message' => eduCoachSpoonfeedGuard(eduCoachGuideUpperEvasionReply(null, $axis, 'layer')),
                'ui_hint' => 'guide_axis',
                'done_guide' => false,
            ];
        }

        $stall++;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideUpperEvasionReply($evasion, $axis, 'nudge')),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if ($pendingWhy !== null && ($pendingWhy['axis_id'] ?? '') === $axisId) {
        $storedChoice = trim((string) ($pendingWhy['choice'] ?? ''));
        $sameChoice = $storedChoice !== ''
            && eduCoachGuideNormalizeCompareKey($message) === eduCoachGuideNormalizeCompareKey($storedChoice);

        if (!$sameChoice && eduCoachAxisStudentPass($message, $evasion)) {
            $combined = $storedChoice !== '' ? $storedChoice . ' — ' . trim($message) : trim($message);
            $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_pending_why' => null]);

            return eduCoachGuideUpperProcessAxisAnswer($blueprint, $quest, $axes, $idx, $axis, $combined, $evasion);
        }

        $stall++;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideWhyFollowUpMessage($axis, $storedChoice)),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if (eduCoachGuideMessageMatchesChoiceOption($message, $axis)) {
        $choice = trim($message);
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_pending_why' => ['axis_id' => $axisId, 'choice' => $choice],
            'guide_axis_stall' => 0,
        ]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideWhyFollowUpMessage($axis, $choice)),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if (eduCoachAxisStudentPass($message, $evasion)) {
        return eduCoachGuideUpperProcessAxisAnswer($blueprint, $quest, $axes, $idx, $axis, trim($message), $evasion);
    }

    $stall++;
    if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
        return eduCoachGuideUpperSkipAxis($blueprint, $quest, $axes, $idx, $message);
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $mode = $stall >= 2 ? 'evidence' : 'default';
    $msg = eduCoachGuideUpperEvasionReply($evasion, $axis, $mode);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideUpperProcessAxisAnswer(
    array $blueprint,
    array $quest,
    array $axes,
    int $idx,
    array $axis,
    string $message,
    ?string $evasion
): array {
    $axisId = (string) ($axis['axis_id'] ?? '');

    if (!eduCoachGuideUpperMessageHasEvidence($message, $axis)) {
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_pending_evidence' => ['axis_id' => $axisId, 'draft' => $message],
            'guide_axis_stall' => 0,
        ]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideUpperEvasionReply(null, $axis, 'evidence')),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_axis_pending_layer' => ['axis_id' => $axisId, 'evidence' => $message],
        'guide_axis_stall' => 0,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard(eduCoachGuideUpperEvasionReply(null, $axis, 'layer')),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideUpperAdvanceAxis(
    array $blueprint,
    array $quest,
    array $axes,
    int $idx
): array {
    if ($idx >= count($axes)) {
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'phase' => 'guide_conclusion',
        ]);
        $msg = '근거로 따진 걸 **네 말**로 한 문장! '
            . "'**나는 ~라고 본다**' — **사실·숫자** 하나 들고.";

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_conclusion',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_axis_index' => $idx,
        'guide_axis_stall' => 0,
        'guide_axis_pending_why' => null,
        'guide_axis_pending_evidence' => null,
        'guide_axis_pending_layer' => null,
    ]);
    $msg = "좋아 — **근거** OK. 다음 축.\n\n" . eduCoachGuideUpperIntroAxis($axes[$idx], $idx);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideUpperSkipAxis(
    array $blueprint,
    array $quest,
    array $axes,
    int $idx,
    string $message
): array {
    $axis = $axes[$idx] ?? $axes[0];
    $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
    $answers[(string) ($axis['axis_id'] ?? '') . '_skipped'] = $message;

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_axis_answers' => $answers,
        'guide_axis_pending_why' => null,
        'guide_axis_pending_evidence' => null,
        'guide_axis_pending_layer' => null,
    ]);

    return eduCoachGuideUpperAdvanceAxis($blueprint, $quest, $axes, $idx + 1);
}
