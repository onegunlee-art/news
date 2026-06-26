<?php
/**
 * EDU coach level 1 — 초등 인도 (추가 전용). FSM은 axis_guide와 동일, 메시지만 쉬운 말·fact 비계.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';

const EDU_COACH_ELEMENTARY_AXIS_MAX = 2;

function eduCoachGuideElementaryReady(): bool
{
    return true;
}

/** @return array<string, array<string, string>> */
function eduCoachGuideElementaryAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'elementary_point' => '핵무기가 있으면 다른 나라 공격을 막아줄까?',
                'elementary_fact' => '2025년 우크라이나는 드론으로 러시아 비행기 기지를 공격했어. 러시아는 **핵무기 대신 일반 미사일**로만 맞대응했대.',
                'elementary_question' => '핵이 있으면 공격을 막을 수 있을까? **어떤 생각이 들어?**',
                'elementary_weak' => '우크라이나 사례만 보면 — 핵 대신 **일반 무기**만 썼어. 네 말을 **도와준다 / 약해진다** 중 하나만 골라줄래?',
            ],
            'norms' => [
                'elementary_point' => '나라끼리 약속으로 전쟁을 막을 수 있을까?',
                'elementary_fact' => '인도와 파키스탄은 핵을 가진 나라인데도 싸움이 났어. 그래도 서로 **핵 시설은 치지 말자**고 약속을 주고받기도 해.',
                'elementary_question' => '**약속**만으로 공격을 막을 수 있을까? 어떻게 생각해?',
                'elementary_weak' => '약속을 주고받았는데도 싸움이 났어. 약속이 **도움이 됐다 / 안 됐다** — 하나만 골라줄래?',
                'elementary_choice_options' => '도움이 됐어,안 됐어',
            ],
        ],
        150 => [
            'scale' => [
                'elementary_point' => '데이터센터가 전기를 얼마나 쓰는지',
                'elementary_fact' => '미국 버지니아 애슈번에는 컴퓨터 창고(데이터센터)가 **150개**나 있어. 거기서 쓰는 전기는 **큰 도시 하나** 분량이래.',
                'elementary_question' => '데이터센터가 전기를 **그렇게 많이** 쓰는 게 맞을까? 어떻게 생각해?',
                'elementary_weak' => '애슈번만 봐 — 창고 **150개**, 전기는 **도시 하나** 분량이야. AI 탓이라는 말을 **키워준다 / 약해진다** — 하나만!',
            ],
            'policy' => [
                'elementary_point' => '정치·기업이 전기 문제에 뭐라고 했는지',
                'elementary_fact' => '트럼프가 빅테크 회사들에게 **전기를 스스로 구해서 요금 안 올리겠다**고 약속하게 했대.',
                'elementary_question' => '이 약속이 데이터센터 전기 문제를 **키울까, 줄일까?** 하나만 골라줄래?',
                'elementary_weak' => '빅테크가 **전기 스스로 구하기** 약속했어. DC 탓이라는 말을 **키운다 / 줄인다** — 하나만!',
                'elementary_choice_options' => '키운다,줄인다',
            ],
        ],
        196 => [
            'regime' => [
                'elementary_point' => '이란 새 지도자가 핵을 더 밀어붙이는지',
                'elementary_fact' => '이란에 새 지도자가 올랐는데, 전보다 **핵무기를 더 원하는 것** 같다고 기사가 말해.',
                'elementary_question' => '새 지도자 때문에 핵 의지가 **커졌을까, 작아졌을까?** 하나만 골라줄래?',
                'elementary_weak' => '지도자가 바뀌었어. 핵 의지가 **커졌다 / 작아졌다** — 하나만!',
                'elementary_choice_options' => '커졌어,작아졌어',
            ],
            'uranium' => [
                'elementary_point' => '이란 안에 남은 핵 재료',
                'elementary_fact' => '이란 안에 **핵폭탄 여러 개를 만들 수 있는 재료**가 아직 남아 있다고 기사가 말해. (정확한 숫자는 어려워도 **위험할 수 있다**는 뜻이야.)',
                'elementary_question' => '그 재료가 남아 있으면 **더 위험해졌을까?** 어떻게 생각해?',
                'elementary_weak' => '핵 재료가 **아직 남아 있어**. **더 위험해 / 괜찮아** — 하나만 골라줄래!',
                'elementary_choice_options' => '더 위험해,괜찮아',
            ],
        ],
        288 => [
            'framing' => [
                'elementary_point' => 'AI가 위험한 이유 — 시간 때문일까, 다른 이유일까?',
                'elementary_fact' => '미국 큰 설문에서 AI가 **사용 시간**보다 **삶의 조건·기분**에 따라 더 크게 달라진다고 나왔어.',
                'elementary_question' => '청소년 AI 위험 — **시간** 때문이야, **삶·기분** 때문이야? 하나만 골라줄래?',
                'elementary_weak' => '설문은 **시간**보다 **맥락**을 강조해. **시간파 / 맥락파** — 하나만!',
                'elementary_choice_options' => '시간,삶·기분',
            ],
            'mobility' => [
                'elementary_point' => 'AI가 청소년에게 기회가 될 수 있는지',
                'elementary_fact' => '청소년 중 **10%** 정도는 AI를 많이 쓰는데, 그중 **3분의 2**는 AI가 **새 기회**를 열어준다고 답했어.',
                'elementary_question' => 'AI가 **기회를 연다 / 별 차이 없다** — 어떻게 생각해? 하나만!',
                'elementary_weak' => '파워 유저 **10%**, **3분의 2**가 기회 — **기회를 연다 / 별 차이 없다** — 하나만!',
                'elementary_choice_options' => '기회를 연다,별 차이 없다',
            ],
        ],
        default => [],
    };
}

/**
 * @param array<string, string> $axis
 * @return array<string, string>
 */
function eduCoachGuideElementaryAdaptAxis(array $axis, int $newsId): array
{
    $id = (string) ($axis['axis_id'] ?? '');
    $overlays = eduCoachGuideElementaryAxisOverlays($newsId);
    $overlay = $overlays[$id] ?? [];
    if ($overlay === []) {
        return $axis;
    }

    if (!empty($overlay['elementary_point'])) {
        $axis['point'] = $overlay['elementary_point'];
    }
    if (!empty($overlay['elementary_fact'])) {
        $axis['article_fact'] = $overlay['elementary_fact'];
    }
    if (!empty($overlay['elementary_question'])) {
        $axis['core_question'] = $overlay['elementary_question'];
    }
    if (!empty($overlay['elementary_weak'])) {
        $axis['weak_scaffold'] = $overlay['elementary_weak'];
    }
    if (!empty($overlay['elementary_choice_options'])) {
        $axis['choice_options'] = array_map('trim', explode(',', $overlay['elementary_choice_options']));
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideElementaryAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $all = eduCoachGuideAxes($quest);
    $slice = array_slice($all, 0, EDU_COACH_ELEMENTARY_AXIS_MAX);
    $adapted = [];
    foreach ($slice as $axis) {
        $adapted[] = eduCoachGuideElementaryAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideElementaryIntroAxis(
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
        return '방금 말한 걸 더 생각해보자.' . $snippetBlock
            . "\n\n기사 내용만 보고 — 네 말을 **도와준다 / 약해진다** 중 하나만 골라줄래?";
    }

    $lead = $index === 0 ? '좋아! 하나씩 생각해보자.' : '이번엔 이것도 생각해보자.';

    return "{$lead}\n\n**{$point}**{$snippetBlock}\n\n{$q}";
}

/**
 * @param array<string, string> $axis
 * @param list<array<string, string>> $axes
 */
function eduCoachGuideElementaryEvasionReply(
    ?string $evasion,
    array $axis,
    bool $weakScaffold
): string {
    if ($weakScaffold && !empty($axis['weak_scaffold'])) {
        return $axis['weak_scaffold'];
    }

    if ($evasion === null || $evasion === 'empty') {
        return ($axis['core_question'] ?? '어떤 생각이 들어?') . ' 짧게만 말해줘.';
    }

    return match ($evasion) {
        'both', 'list' => '둘 다 좋지만 **하나만** 골라야 해. '
            . ($axis['core_question'] ?? '지금 질문에 답해줄래?'),
        'unknown' => '괜찮아, 아직 어려운 주제야. '
            . ($axis['core_question'] ?? '')
            . ' **맞다 / 아니다 / 잘 모르겠다** 중 하나만 골라줄래?',
        'deflect' => '나라마다 다를 수 있어. 이 기사 조각만 보고 — '
            . eduCoachGuideWrapArticleSnippet(trim((string) ($axis['article_fact'] ?? '')), 'medium')
            . ' 네 생각을 **도와준다 / 약해진다** — 하나만!',
        'defer_article' => '기사 **어느 부분**을 말하는 거야? 하나만 짚어줘. **네 생각이랑 같은지 다른지**만 말해.',
        'ask_conclusion' => '결론은 **내가 안 알려줘**. 지금 질문부터 — ' . ($axis['core_question'] ?? '어떻게 생각해?'),
        default => $axis['core_question'] ?? '어떤 생각이 들어? 짧게 말해줘.',
    };
}

/** @param array<string, string> $axis */
function eduCoachGuideElementaryWhyFollowUp(string $choice, array $axis): string
{
    $c = trim($choice);

    return "**{$c}** 골랐구나! **왜** 그렇게 생각해? 짧게만 말해줘.";
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideElementaryHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_ELEMENTARY
): array {
    $axes = eduCoachGuideElementaryAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => EDU_COACH_LEVEL_ELEMENTARY,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideElementaryEvasionReply($evasion, $axes[0], false);
    } else {
        $msg = eduCoachGuideElementaryIntroAxis($axes[0], 0, $opening, $hookShort);
    }

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
function eduCoachGuideElementaryHandleConclusion(
    array $blueprint,
    string $message,
    array $axes
): array {
    $evasion = eduCoachDetectEvasion($message);
    if (!eduCoachAxisStudentPass($message, $evasion) || $evasion === 'ask_conclusion') {
        $stall = (int) ($blueprint['guide_conclusion_stall'] ?? 0) + 1;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_conclusion_stall' => $stall]);
        if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_student_conclusion' => $message !== '' ? $message : '(미정)',
                'phase' => 'hammer',
            ]);

            return [
                'blueprint' => $blueprint,
                'message' => '괜찮아 — 다음으로 넘어가자. 다른 사람 시각도 들어볼게.',
                'ui_hint' => 'hammer',
                'done_guide' => true,
                'advance_hammer' => true,
            ];
        }

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(
                '결론은 **내가 안 알려줘**. 지금까지 **네가** 말한 걸 바탕으로 — '
                . '**나는 ~라고 생각해** 한 문장만. **네 말**이어야 해!'
            ),
            'ui_hint' => 'guide_conclusion',
            'done_guide' => false,
            'advance_hammer' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_student_conclusion' => $message,
        'phase' => 'hammer',
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => '고마워! **네 결론** 들었어. 이제 다른 시각도 들어볼게.',
        'ui_hint' => 'hammer',
        'done_guide' => true,
        'advance_hammer' => true,
    ];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideElementaryHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_ELEMENTARY
): array {
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideElementaryAxes($quest);

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
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideElementaryWhyFollowUp($choice, $axis)),
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
            $msg = '지금까지 말한 걸 **네 말**로 한 문장만! '
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
        $msg = "잘했어! 다음으로 넘어가자.\n\n" . eduCoachGuideElementaryIntroAxis($axes[$idx], $idx);

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
            $msg = "여기서 막혔구나 — 괜찮아. **네 결론**을 '**나는 ~라고 생각해**' 로 한 문장만!";

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
        $msg = "막히면 괜찮아 — **다음 질문**으로 넘어가자.\n\n" . eduCoachGuideElementaryIntroAxis($axes[$idx], $idx);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useScaffold = $stall >= 2;
    $msg = eduCoachGuideElementaryEvasionReply($evasion, $axis, $useScaffold);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}
