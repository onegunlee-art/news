<?php
/**
 * EDU coach L5 — 칼럼니스트 (the gist). v1 보존 + turn/conclusion 메타반론(반론의 반론). 비계 0.
 */
declare(strict_types=1);

/** @return array<string, array<string, string>> */
function eduCoachGuideColumnistAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'meta_ask' => '**{point}** — 네 말을 **반대쪽**이 어떻게 받아칠 것 같아? **그쪽 논리**만 짧게.',
                'meta_nudge' => '**반대편 시선**으로 — **네 주장**의 **약점** 한 줄. (내가 답 안 줌)',
            ],
            'norms' => [
                'meta_ask' => '**약속·규범** 축 — **반대쪽**이 네 말을 **어떻게 깰** 것 같아?',
                'meta_nudge' => '**반대편**이 **약속**을 들고 **뭐라** 할지 — **네 말**로 한 줄.',
            ],
            'defense' => [
                'meta_ask' => '**예산·방어** 축 — **반대쪽**이 **네 선택**을 **어떻게 받아칠** 것 같아?',
                'meta_nudge' => '**반대편** 시선 — **네 1순위**의 **허점** 한 줄.',
            ],
        ],
        150 => [
            'scale' => [
                'meta_ask' => '**전력 규모** — **반대쪽**이 **네 프레임**을 **어떻게 깰** 것 같아?',
                'meta_nudge' => '**반대편**이 **숫자**를 들고 **뭐라** 할지 — **네 말**로.',
            ],
            'policy' => [
                'meta_ask' => '**정책·약속** — **반대쪽** **받아치기** 한 줄만 상상해봐.',
                'meta_nudge' => '**반대편** 시선 — **네 말** **약점** 한 줄.',
            ],
            'grid_investment' => [
                'meta_ask' => '**원인** 축 — **반대쪽**이 **네 한쪽** 주장을 **어떻게 받아칠**까?',
                'meta_nudge' => '**반대편** **논리** — **네 주장** **허점** 한 줄.',
            ],
        ],
        default => [],
    };
}

/**
 * @param array<string, string> $axis
 * @return array<string, string>
 */
function eduCoachGuideColumnistAdaptAxis(array $axis, int $newsId): array
{
    $id = (string) ($axis['axis_id'] ?? '');
    $overlay = eduCoachGuideColumnistAxisOverlays($newsId)[$id] ?? [];
    $point = (string) ($axis['point'] ?? '');
    foreach (['meta_ask', 'meta_nudge'] as $key) {
        if (!empty($overlay[$key])) {
            $axis['columnist_' . $key] = str_replace('{point}', $point, $overlay[$key]);
        }
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideColumnistAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $adapted = [];
    foreach (eduCoachGuideAxes($quest) as $axis) {
        $adapted[] = eduCoachGuideColumnistAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideColumnistMetaAsk(array $axis): string
{
    return (string) ($axis['columnist_meta_ask'] ?? '**반대쪽**이 **네 말**을 **어떻게 받아칠** 것 같아? **그쪽 논리**만.');
}

/** @param array<string, string> $axis */
function eduCoachGuideColumnistMetaNudge(array $axis): string
{
    return (string) ($axis['columnist_meta_nudge'] ?? '**반대편 시선** — **네 말** **약점** 한 줄. (답 안 줌)');
}

function eduCoachGuideColumnistConclusionMetaAsk(): string
{
    return '**네 결론** — **반대편**이 **어떻게 받아칠** 것 같아? **그쪽 반론**만 짧게. (내가 답 안 줌)';
}

function eduCoachGuideColumnistConclusionMetaNudge(): string
{
    return '**반론의 반론** — **반대쪽** **받아치기** 한 줄. **네 말**로.';
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideColumnistHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_L5
): array {
    $axes = eduCoachGuideColumnistAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => EDU_COACH_LEVEL_L5,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideEvasionReply($evasion, $axes[0], 0, $axes, false);
    } else {
        $msg = eduCoachGuideIntroAxis($axes[0], 0, count($axes), $opening, $hookShort);
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
function eduCoachGuideColumnistHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_L5
): array {
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideColumnistAxes($quest);

    if ($phase === 'guide_conclusion') {
        return eduCoachGuideColumnistHandleConclusion($blueprint, $quest, $message, $axes);
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

    $pendingMeta = is_array($blueprint['guide_axis_pending_meta'] ?? null)
        ? $blueprint['guide_axis_pending_meta']
        : null;
    $pendingWhy = is_array($blueprint['guide_axis_pending_why'] ?? null)
        ? $blueprint['guide_axis_pending_why']
        : null;

    if ($pendingMeta !== null && ($pendingMeta['axis_id'] ?? '') === $axisId) {
        if (eduCoachAxisStudentPass($message, $evasion)) {
            $draft = trim((string) ($pendingMeta['draft'] ?? ''));
            $combined = $draft !== '' ? $draft . ' / [반론] ' . trim($message) : trim($message);
            $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
            $answers[$axisId] = $combined;
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_pending_meta' => null,
                'guide_axis_stall' => 0,
            ]);

            return eduCoachGuideColumnistAdvanceAxis($blueprint, $axes, $idx + 1);
        }

        $stall++;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideColumnistMetaNudge($axis)),
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

            return eduCoachGuideColumnistAfterAxisPass($blueprint, $axis, $axisId, $combined);
        }

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
        return eduCoachGuideColumnistAfterAxisPass($blueprint, $axis, $axisId, trim($message));
    }

    $stall++;
    if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axisId . '_skipped'] = $message;
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_answers' => $answers,
            'guide_axis_pending_why' => null,
            'guide_axis_pending_meta' => null,
        ]);

        return eduCoachGuideColumnistAdvanceAxis($blueprint, $axes, $idx + 1);
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useScaffold = $stall >= 2;
    $msg = eduCoachGuideEvasionReply($evasion, $axis, $idx, $axes, $useScaffold);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideColumnistAfterAxisPass(
    array $blueprint,
    array $axis,
    string $axisId,
    string $draft
): array {
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_axis_pending_meta' => ['axis_id' => $axisId, 'draft' => $draft],
        'guide_axis_stall' => 0,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard(eduCoachGuideColumnistMetaAsk($axis)),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideColumnistAdvanceAxis(array $blueprint, array $axes, int $idx): array
{
    if ($idx >= count($axes)) {
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'phase' => 'guide_conclusion',
            'guide_conclusion_meta_done' => false,
        ]);
        $msg = '지금까지 따진 걸 **네 결론** 한 문장! '
            . "'**나는 ~라고 본다**' — **네 말**만.";

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
        'guide_axis_pending_meta' => null,
    ]);
    $msg = "좋아, 다음으로.\n\n" . eduCoachGuideIntroAxis($axes[$idx], $idx, count($axes));

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer: bool}
 */
function eduCoachGuideColumnistHandleConclusion(
    array $blueprint,
    array $quest,
    string $message,
    array $axes
): array {
    unset($axes, $quest);
    $evasion = eduCoachDetectEvasion($message);
    $metaDone = !empty($blueprint['guide_conclusion_meta_done']);
    $pendingConclusion = trim((string) ($blueprint['guide_student_conclusion_draft'] ?? ''));

    if (!$metaDone && $pendingConclusion !== '') {
        if (eduCoachAxisStudentPass($message, $evasion)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_student_conclusion' => $pendingConclusion . ' / [메타] ' . trim($message),
                'guide_student_conclusion_draft' => null,
                'guide_conclusion_meta_done' => true,
                'phase' => 'hammer',
            ]);

            return [
                'blueprint' => $blueprint,
                'message' => '**반론의 반론**까지 **네 말**로 짚었어. 이제 다른 시각 하나 더.',
                'ui_hint' => 'hammer',
                'done_guide' => true,
                'advance_hammer' => true,
            ];
        }

        $stall = (int) ($blueprint['guide_conclusion_stall'] ?? 0) + 1;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_conclusion_stall' => $stall]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideColumnistConclusionMetaNudge()),
            'ui_hint' => 'guide_conclusion',
            'done_guide' => false,
            'advance_hammer' => false,
        ];
    }

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
                'message' => '애매해도 괜찮아 — 다음 단계로.',
                'ui_hint' => 'hammer',
                'done_guide' => true,
                'advance_hammer' => true,
            ];
        }

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(
                '결론은 **내가 안 줘**. **나는 ~라고 본다** 한 문장 — **네 생각**만.'
            ),
            'ui_hint' => 'guide_conclusion',
            'done_guide' => false,
            'advance_hammer' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_student_conclusion_draft' => trim($message),
        'guide_conclusion_stall' => 0,
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard(eduCoachGuideColumnistConclusionMetaAsk()),
        'ui_hint' => 'guide_conclusion',
        'done_guide' => false,
        'advance_hammer' => false,
    ];
}
