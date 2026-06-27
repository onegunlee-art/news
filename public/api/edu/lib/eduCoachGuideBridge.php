<?php
/**
 * EDU coach L2 — 양면 입문 (초고~중1). L1보다 깊고 L3보다 얕음. counter 없음.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';

const EDU_COACH_BRIDGE_AXIS_MAX = 3;

/** @return array<string, array<string, string>> */
function eduCoachGuideBridgeAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'bridge_point' => '핵이 있으면 공격을 막을 수 있을까?',
                'bridge_fact' => '2025년 우크라이나는 드론으로 러시아 기지를 공격했고, 러시아는 **핵 대신 재래식 미사일**로만 맞대응했어.',
                'bridge_question' => '한쪽으로 보면 핵이 **방패** 같아 보이는데 — 기사만 보면 **다른 이야기**도 있어. **어떤 쪽**이 더 와닿아?',
                'bridge_weak' => '우크라이나 사례 — 핵 대신 **재래식**만 썼어. 네 \'핵=방패\' 말을 **키워준다 / 약해진다** — 하나만!',
            ],
            'norms' => [
                'bridge_point' => '나라끼리 약속으로 전쟁을 막을 수 있을까?',
                'bridge_fact' => '인도·파키스탄은 핵을 가진 나라인데도 싸움이 났어. 그래도 **핵 시설은 치지 말자**고 약속을 주고받기도 해.',
                'bridge_question' => '약속이 **도움이 될 것 같다** vs **안 될 것 같다** — 기사 조각만 보고 하나만 골라줄래?',
                'bridge_weak' => '약속을 주고받았는데도 충돌이 있었어. **도움이 됐다 / 안 됐다** — 하나만!',
                'bridge_choice_options' => '도움이 됐어,안 됐어',
            ],
            'defense' => [
                'bridge_point' => '예산이 한정돼 있다면 — 무엇에 먼저 쓸까?',
                'bridge_fact' => '드론 떼를 막으려면 **방공·기지** 이야기가 나오고, **핵을 더 갖추자**는 이야기도 나와.',
                'bridge_question' => '**핵 현대화** vs **방공·기지** — 하나만 먼저 골라줄래?',
                'bridge_weak' => '둘 다 중요해 보이지만 **하나만** — **핵 쪽 / 방공·기지 쪽**?',
                'bridge_choice_options' => '핵 현대화,방공·기지',
            ],
        ],
        150 => [
            'scale' => [
                'bridge_point' => '데이터센터가 전기를 그렇게 많이 쓰는 게 맞을까?',
                'bridge_fact' => '애슈번에 데이터센터 **150개**, 전력은 **필라델피아급**이래.',
                'bridge_question' => '한쪽으로 보면 AI·DC **탓**인데 — 기사는 **송전망·시장** 이야기도 해. **어느 쪽**이 더 와닿아?',
                'bridge_weak' => '애슈번 **150개·도시급 전력** — \'AI=범인\' 말을 **키워준다 / 약해진다** — 하나만!',
            ],
            'policy' => [
                'bridge_point' => '빅테크 전력 약속이 문제를 키울까, 줄일까?',
                'bridge_fact' => '트럼프가 빅테크에 **자체 전력·요금 동결** 서약을 받았대.',
                'bridge_question' => '**키운다 / 줄인다** — 하나만 골라줄래?',
                'bridge_weak' => '자체 전력 약속 — DC 탓 프레임을 **키운다 / 줄인다** — 하나만!',
                'bridge_choice_options' => '키운다,줄인다',
            ],
            'grid_investment' => [
                'bridge_point' => '요금 상승 — AI 탓인가, 송전망·시장 탓인가?',
                'bridge_fact' => '요금은 AI 수요만이 아니라 **송전망·에너지시장** 제약과도 연결된다고 해.',
                'bridge_question' => '**AI·데이터센터 탓 / 송전망·시장 탓** — 하나만!',
                'bridge_weak' => 'AI만 탓하면 **송전망**은 어디에? **AI 탓 / 망·시장 탓** — 하나만!',
                'bridge_choice_options' => 'AI·DC 탓,송전망·시장 탓',
            ],
        ],
        default => [],
    };
}

/**
 * @param array<string, string> $axis
 * @return array<string, string>
 */
function eduCoachGuideBridgeAdaptAxis(array $axis, int $newsId): array
{
    $id = (string) ($axis['axis_id'] ?? '');
    $overlay = eduCoachGuideBridgeAxisOverlays($newsId)[$id] ?? [];
    if ($overlay === []) {
        return $axis;
    }

    foreach ([
        'bridge_point' => 'point',
        'bridge_fact' => 'article_fact',
        'bridge_question' => 'core_question',
        'bridge_weak' => 'weak_scaffold',
    ] as $from => $to) {
        if (!empty($overlay[$from])) {
            $axis[$to] = $overlay[$from];
        }
    }
    if (!empty($overlay['bridge_choice_options'])) {
        $axis['choice_options'] = array_map('trim', explode(',', $overlay['bridge_choice_options']));
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideBridgeAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $all = eduCoachGuideAxes($quest);
    $slice = array_slice($all, 0, EDU_COACH_BRIDGE_AXIS_MAX);
    $adapted = [];
    foreach ($slice as $axis) {
        $adapted[] = eduCoachGuideBridgeAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideBridgeIntroAxis(
    array $axis,
    int $index,
    string $openingContext = '',
    string $hookShort = ''
): string {
    $point = $axis['point'] ?? '';
    $fact = trim((string) ($axis['article_fact'] ?? ''));
    $q = $axis['core_question'] ?? '';
    $snippetBlock = $fact !== '' ? "\n\n" . eduCoachGuideWrapArticleSnippet($fact, 'medium') : '';

    $hookOverlap = $index === 0 && (
        ($hookShort !== '' && eduCoachGuideTextsOverlap($hookShort, $q))
        || ($openingContext !== '' && eduCoachGuideTextsOverlap($openingContext, $q))
    );

    if ($hookOverlap) {
        return '방금 말한 걸 **양쪽**에서 생각해보자.' . $snippetBlock
            . "\n\n기사 조각만 놓고 — 네 말을 **강하게** 해주나 **약하게** 해주나?";
    }

    if ($index === 0) {
        return '한쪽으로 보면 **' . $point . '** 같아 보이는데 — 기사 보면 **다른 면**도 있어.'
            . $snippetBlock . "\n\n" . $q;
    }

    return '이번엔 다른 각도야. **' . $point . '**' . $snippetBlock . "\n\n" . $q;
}

/** @param array<string, string> $axis */
function eduCoachGuideBridgeEvasionReply(?string $evasion, array $axis, bool $weakScaffold): string
{
    if ($weakScaffold && !empty($axis['weak_scaffold'])) {
        return $axis['weak_scaffold'];
    }

    if ($evasion === null || $evasion === 'empty') {
        return ($axis['core_question'] ?? '') . ' **한쪽만** 골라서 짧게 말해줘.';
    }

    return match ($evasion) {
        'both', 'list' => '**하나씩**만 — 지금 축에서 **1순위 하나**만 골라봐.',
        'unknown' => '모르겠다는 건 아직 안 따진 거야. '
            . ($axis['core_question'] ?? '')
            . ' **맞다 / 틀리다 / 잘 모르겠다** — 하나만?',
        'deflect' => '나라마다 다를 수 있어. 이 조각만 — '
            . eduCoachGuideWrapArticleSnippet(trim((string) ($axis['article_fact'] ?? '')), 'medium')
            . ' **강하게 / 약하게** — 하나만!',
        'defer_article' => '기사 **어느 부분**을 말하는 거야? **네 생각이랑 같은지 다른지**만 말해.',
        'ask_conclusion' => '결론은 **내가 안 알려줘**. 지금 — ' . ($axis['core_question'] ?? '한쪽만 골라봐.'),
        default => $axis['core_question'] ?? '한쪽만 골라서 짧게 말해줘.',
    };
}

/** @param array<string, string> $axis */
function eduCoachGuideBridgeWhyFollowUp(string $choice, array $axis): string
{
    $c = trim($choice);

    return "**{$c}** 골랐구나. **왜** 그쪽이야? 한두 문장만.";
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideBridgeHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_L2
): array {
    $axes = eduCoachGuideBridgeAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => EDU_COACH_LEVEL_L2,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideBridgeEvasionReply($evasion, $axes[0], false);
    } else {
        $msg = eduCoachGuideBridgeIntroAxis($axes[0], 0, $opening, $hookShort);
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
function eduCoachGuideBridgeHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_L2
): array {
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideBridgeAxes($quest);

    if ($phase === 'guide_conclusion') {
        return eduCoachGuideElementaryHandleConclusion($blueprint, $message, $axes);
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
    $axisAnswerMessage = $message;
    $pendingWhy = is_array($blueprint['guide_axis_pending_why'] ?? null)
        ? $blueprint['guide_axis_pending_why']
        : null;
    $pendingWhyIncomplete = false;

    if ($pendingWhy !== null && ($pendingWhy['axis_id'] ?? '') === ($axis['axis_id'] ?? '')) {
        $storedChoice = trim((string) ($pendingWhy['choice'] ?? ''));
        $sameChoice = $storedChoice !== ''
            && eduCoachGuideNormalizeCompareKey($message) === eduCoachGuideNormalizeCompareKey($storedChoice);

        if (!$sameChoice && eduCoachAxisStudentPass($message, $evasion)) {
            $axisAnswerMessage = $storedChoice !== ''
                ? $storedChoice . ' — ' . trim($message)
                : trim($message);
            $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_pending_why' => null]);
        } else {
            $pendingWhyIncomplete = true;
        }
    } elseif (eduCoachGuideMessageMatchesChoiceOption($message, $axis)) {
        $choice = trim($message);
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_pending_why' => [
                'axis_id' => (string) ($axis['axis_id'] ?? ''),
                'choice' => $choice,
            ],
            'guide_axis_stall' => 0,
        ]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideBridgeWhyFollowUp($choice, $axis)),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $axisCount = count($axes);

    if (!$pendingWhyIncomplete && eduCoachAxisStudentPass($axisAnswerMessage, $evasion)) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id']] = $axisAnswerMessage;
        $idx++;
        $stall = 0;

        if ($idx >= $axisCount) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'guide_axis_stall' => 0,
                'phase' => 'guide_conclusion',
            ]);
            $msg = '지금까지 **양쪽**에서 따진 걸 **네 말**로 한 문장! '
                . "'**나는 ~라고 생각해**' 로 말해줘.";

            return [
                'blueprint' => $blueprint,
                'message' => eduCoachSpoonfeedGuard($msg),
                'ui_hint' => 'guide_conclusion',
                'done_guide' => false,
            ];
        }

        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_answers' => $answers,
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'guide_axis_pending_why' => null,
        ]);
        $msg = "좋아 — **다른 각도**로 넘어가자.\n\n" . eduCoachGuideBridgeIntroAxis($axes[$idx], $idx);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $stall++;
    if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id'] . '_skipped'] = $message;
        $idx++;
        $stall = 0;

        if ($idx >= $axisCount) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'phase' => 'guide_conclusion',
            ]);
            $msg = "막혔구나 — 괜찮아. **네 결론**을 '**나는 ~라고 생각해**' 로 한 문장만!";

            return [
                'blueprint' => $blueprint,
                'message' => eduCoachSpoonfeedGuard($msg),
                'ui_hint' => 'guide_conclusion',
                'done_guide' => false,
            ];
        }

        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_answers' => $answers,
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'guide_axis_pending_why' => null,
        ]);
        $msg = "다음 각도로 — \n\n" . eduCoachGuideBridgeIntroAxis($axes[$idx], $idx);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useScaffold = $stall >= 2;
    $msg = eduCoachGuideBridgeEvasionReply($evasion, $axis, $useScaffold);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}
