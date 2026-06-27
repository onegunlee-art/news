<?php
/**
 * EDU coach L3 — 중등 양면 (중2~3). 3축, 반론 1겹(counter 힌트). L2보다 깊고 L5(v1)보다 얕음.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';

const EDU_COACH_MIDDLE_AXIS_MAX = 3;

/** @return array<string, array<string, string>> */
function eduCoachGuideMiddleAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'middle_question' => '핵이 **방패**라고 생각하는 쪽 vs **기사가 보여주는 다른 쪽** — 지금은 어느 쪽이 더 와닿아?',
                'middle_counter' => '반대로 보면 — 재래식만 썼다는 건 **핵이 공격을 막지 못했다**는 신호일 수도 있어.',
                'middle_weak' => '우크라이나 사례 — **핵 대신 재래식**만. 반대쪽을 생각해보면 네 \'핵=방패\' 말을 **강하게 / 약하게** — 하나만!',
            ],
            'norms' => [
                'middle_question' => '**약속이 통한다** vs **약속만으론 부족하다** — 기사 조각만 보고 하나만 골라줄래?',
                'middle_counter' => '반대로 보면 — 약속을 주고받았는데도 **충돌은 났**어.',
                'middle_weak' => '인도·파키스탄 — 약속 O, 충돌도 O. **약속이 도움 / 한계** — 하나만!',
                'middle_choice_options' => '도움이 됐어,한계가 있어',
            ],
            'defense' => [
                'middle_question' => '예산이 한정이면 — **핵 현대화** vs **방공·기지** 중 **하나만** 먼저?',
                'middle_counter' => '반대로 보면 — 드론·미사일 앞에서 **방공만**으로는 버티기 어렵다는 이야기도 있어.',
                'middle_weak' => '**핵 더 갖추기** vs **방공·기지** — 반대쪽 생각해보면 **어느 쪽**이 더 급해 보여?',
                'middle_choice_options' => '핵 현대화,방공·기지',
            ],
        ],
        150 => [
            'scale' => [
                'middle_question' => '**AI·DC 탓** vs **송전망·시장 탓** — 애슈번 조각만 보고 하나만!',
                'middle_counter' => '반대로 보면 — DC 수요가 **새 발전·망 투자**를 부를 수도 있다는 이야기도 있어.',
                'middle_weak' => '애슈번 **150개·도시급 전력** — 반대쪽을 생각하면 \'AI=범인\' 말을 **키운다 / 줄인다** — 하나만!',
            ],
            'policy' => [
                'middle_question' => '빅테크 **자체 전력 약속** — DC 탓 프레임을 **키운다 / 줄인다**?',
                'middle_counter' => '반대로 보면 — 약속은 **요금만 동결**이지, 전력 문제 **원인**을 없앤 건 아닐 수도 있어.',
                'middle_weak' => '자체 전력 약속 — **키운다 / 줄인다** — 하나만!',
                'middle_choice_options' => '키운다,줄인다',
            ],
            'grid_investment' => [
                'middle_question' => '요금 상승 — **AI·DC** vs **송전망·시장** 중 하나만!',
                'middle_counter' => '반대로 보면 — AI만 탓하면 **송전망·시장** 제약을 놓칠 수 있어.',
                'middle_weak' => '**AI 탓 / 망·시장 탓** — 반대쪽도 생각해보면 **어느 쪽**?',
                'middle_choice_options' => 'AI·DC 탓,송전망·시장 탓',
            ],
        ],
        default => [],
    };
}

/**
 * @param array<string, string> $axis
 * @return array<string, string>
 */
function eduCoachGuideMiddleAdaptAxis(array $axis, int $newsId): array
{
    $id = (string) ($axis['axis_id'] ?? '');
    $overlay = eduCoachGuideMiddleAxisOverlays($newsId)[$id] ?? [];
    if ($overlay === []) {
        return $axis;
    }

    if (!empty($overlay['middle_question'])) {
        $axis['core_question'] = $overlay['middle_question'];
    }
    if (!empty($overlay['middle_counter'])) {
        $axis['middle_counter'] = $overlay['middle_counter'];
    }
    if (!empty($overlay['middle_weak'])) {
        $axis['weak_scaffold'] = $overlay['middle_weak'];
    }
    if (!empty($overlay['middle_choice_options'])) {
        $axis['choice_options'] = array_map('trim', explode(',', $overlay['middle_choice_options']));
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideMiddleAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $slice = array_slice(eduCoachGuideAxes($quest), 0, EDU_COACH_MIDDLE_AXIS_MAX);
    $adapted = [];
    foreach ($slice as $axis) {
        $adapted[] = eduCoachGuideMiddleAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideMiddleIntroAxis(
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
        return '방금 말은 **한쪽**이야. 기사 조각 보면 **다른 쪽**도 보여.'
            . $snippetBlock
            . "\n\n" . $q;
    }

    if ($index === 0) {
        return '**' . $point . '** — 네 생각 **한쪽**인데, 기사는 **양쪽** 이야기를 해.'
            . $snippetBlock
            . "\n\n" . $q;
    }

    return '다음 축 — **' . $point . '**' . $snippetBlock . "\n\n" . $q;
}

/** @param array<string, string> $axis */
function eduCoachGuideMiddleEvasionReply(?string $evasion, array $axis, bool $useCounter): string
{
    if ($useCounter && !empty($axis['middle_counter'])) {
        return $axis['middle_counter'] . ' — 그래도 **네 말**을 **강하게 / 약하게** — 하나만!';
    }

    if ($useCounter && !empty($axis['weak_scaffold'])) {
        return $axis['weak_scaffold'];
    }

    if ($evasion === null || $evasion === 'empty') {
        return ($axis['core_question'] ?? '') . ' **한쪽만** 골라서 짧게!';
    }

    return match ($evasion) {
        'both', 'list' => '**하나씩**만 — 지금 축에서 **한쪽**만 골라봐.',
        'unknown' => '모르겠다면 — **맞다 / 틀리다 / 잘 모르겠다** 중 하나만?',
        'deflect' => '이 조각만 — '
            . eduCoachGuideWrapArticleSnippet(eduCoachGuideFactForDisplay($axis))
            . ' **강하게 / 약하게** — 하나만!',
        'defer_article' => '기사 **어느 부분**? **네 생각이랑 같은지 다른지**만.',
        'ask_conclusion' => '결론은 **내가 안 줘**. 지금 — ' . ($axis['core_question'] ?? ''),
        default => $axis['core_question'] ?? '한쪽만 골라서 짧게!',
    };
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideMiddleHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_L3
): array {
    $axes = eduCoachGuideMiddleAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => EDU_COACH_LEVEL_L3,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideMiddleEvasionReply($evasion, $axes[0], false);
    } else {
        $msg = eduCoachGuideMiddleIntroAxis($axes[0], 0, $opening, $hookShort);
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
function eduCoachGuideMiddleHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_L3
): array {
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideMiddleAxes($quest);

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
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideWhyFollowUpMessage($axis, $choice)),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if (!$pendingWhyIncomplete && eduCoachAxisStudentPass($axisAnswerMessage, $evasion)) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id']] = $axisAnswerMessage;
        $idx++;
        $stall = 0;

        if ($idx >= count($axes)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'guide_axis_stall' => 0,
                'phase' => 'guide_conclusion',
            ]);
            $msg = '양쪽 따진 걸 **네 말**로 한 문장! '
                . "'**나는 ~라고 본다**' — **네 결론**이어야 해.";

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
        $msg = "좋아 — **다음 축**.\n\n" . eduCoachGuideMiddleIntroAxis($axes[$idx], $idx);

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

        if ($idx >= count($axes)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'phase' => 'guide_conclusion',
            ]);
            $msg = '막혔구나 — **네 결론** 한 문장! (\'**나는 ~라고 본다**\')';

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
        $msg = "다음 축으로 — \n\n" . eduCoachGuideMiddleIntroAxis($axes[$idx], $idx);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useCounter = $stall >= 2;
    $msg = eduCoachGuideMiddleEvasionReply($evasion, $axis, $useCounter);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}
