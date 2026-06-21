<?php
/**
 * P2 — 축 순서 인도 코치 (안 떠먹임, Q-AUTO-NUKE-630 1차)
 * 기준: docs/P2_COACH_NO_SPOONFEED.md
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';

const EDU_COACH_GUIDE_QUEST_CODE = 'Q-AUTO-NUKE-630';
const EDU_COACH_GUIDE_STALL_ESCAPE = 3;
/** P2-B hook: per-student fact length (summary → medium → full) */
const EDU_COACH_GUIDE_FACT_DISPLAY = 'summary';

/** @param array<string, mixed> $quest */
function eduQuestUsesAxisGuide(array $quest): bool
{
    if (($quest['quest_code'] ?? '') === EDU_COACH_GUIDE_QUEST_CODE) {
        return true;
    }
    $hints = eduQuestHammerHints($quest);

    return ($hints['coach_mode'] ?? '') === 'axis_guide_v1';
}

/** 630 정규화 3축 — 군사 / 규범(§2 흡수) / 방어 */
function eduCoachGuide630Axes(): array
{
    return [
        [
            'axis_id' => 'military',
            'point' => '핵·억지가 재래식·드론 공격을 실제로 막는지',
            'core_question' => '핵이 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?',
            'article_fact' => '2025년 6월 우크라이나는 \'거미줄 작전\'으로 러시아 전략폭격기 기지를 타격했고, 러시아는 핵이 아니라 재래식 보복만 했다.',
            'weak_scaffold' => '우크라이나 사례만 보면 — 드론 타격 후 **핵 대신 재래식** 보복이었어. 이게 네 \'핵=억지\' 말을 **강하게** 해주나, **약하게** 해주나?',
        ],
        [
            'axis_id' => 'norms',
            'point' => '나라들끼리 약속·규범으로 공격을 막을 수 있는지',
            'core_question' => '핵시설·원전을 재래식으로 안 치겠다는 약속, 새로 만들 수 있을까?',
            'article_fact' => '2025년 인도-파키스탄은 핵보유국인데도 재래식 충돌이 났고, 인도·파키스탄은 매년 핵 관련 시설 목록을 바꿔 주며 상호 비공격을 약속한다.',
            'weak_scaffold' => '인도·파키스탄처럼 **약속·목록 교환**이 재래식 충돌을 막는 데 **도움이 됐다/안 됐다** — 네 말로 한쪽만 골라봐.',
        ],
        [
            'axis_id' => 'defense',
            'point' => '핵을 더 늘리기 vs 기지·방공·회복력에 쓸지',
            'core_question' => '예산이 한정돼 있다면 — 너는 **무엇에 먼저** 쓸 것 같아? (핵 현대화 / 기지·방공·드론 방어 / 규범 협상)',
            'article_fact' => '값싼 드론 떼를 막으려면 비싼 요격만으로는 오래 버티기 어렵고, 기지 방호·회복탄력성 이야기가 나온다.',
            'weak_scaffold' => '드론·미사일 앞에서 — **핵을 더 갖추는 쪽**과 **방공·기지를 키우는 쪽** 중 **하나만** 고르면 뭐부터야?',
        ],
    ];
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideAxes(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $fromHints = $hints['_guide_axes'] ?? null;
    if (is_array($fromHints) && $fromHints !== []) {
        return $fromHints;
    }

    return eduCoachGuide630Axes();
}

function eduCoachGuideAttachHints(array $hints): array
{
    $hints['coach_mode'] = 'axis_guide_v1';
    $hints['_guide_axes'] = eduCoachGuide630Axes();
    $hints['mode'] = 'adversarial';

    return $hints;
}

function eduCoachDetectEvasion(string $message): ?string
{
    $m = mb_strtolower(trim($message));
    if ($m === '') {
        return 'empty';
    }
    if (preg_match('/(둘\s*다|모두|전부|균형|병행|함께|같이)/u', $m)) {
        return 'both';
    }
    if (preg_match('/(모르겠|잘\s*모르|글쎄|아\s*모르)/u', $m)) {
        return 'unknown';
    }
    if (preg_match('/(나라마다|경우마다|상황마다|케이스\s*바이)/u', $m)) {
        return 'deflect';
    }
    if (preg_match('/(결론.{0,6}(뭐|어디|어떻)|정답|알려\s*줘)/u', $m)) {
        return 'ask_conclusion';
    }
    if (preg_match('/(기사\s*(가|는)|the\s*gist|본문\s*(이|가))\s*(그렇|말하|쓰)/u', $m)) {
        return 'defer_article';
    }
    if (preg_match('/(중요하고.*중요|필요하고.*필요)/u', $m)) {
        return 'list';
    }

    return null;
}

function eduCoachAxisStudentPass(string $message, ?string $evasion): bool
{
    if ($evasion !== null) {
        return false;
    }
    if (mb_strlen(trim($message)) < 10) {
        return false;
    }
    if (preg_match('/^(음+|아+|그래|맞아|네+|응+)\.?$/u', trim($message))) {
        return false;
    }

    return true;
}

function eduCoachSpoonfeedGuard(string $text): string
{
    $snippets = [];
    $out = preg_replace_callback(
        '/\{\{snippet\|\w+\}\}\s*[\s\S]*?\s*\{\{\/snippet\}\}/u',
        static function (array $m) use (&$snippets): string {
            $key = '___SNIP_' . count($snippets) . '___';
            $snippets[$key] = $m[0];

            return $key;
        },
        $text
    ) ?? $text;

    $patterns = [
        '/~?(중요하지|가\s*답이지|가\s*맞지)\??/u' => '?',
        '/정리하면\s*[^.!?]+[.!?]/u' => '',
        '/많은\s*분석가/u' => '기사에는',
        '/방어\s*투자\s*쪽으로\s*정리/u' => '네 말로 한 문장 정리',
        '/the\s*gist[^\s]*/iu' => '',
        '/gist랑\s*[^\s.]*/iu' => '',
        '/기사는\s*[^.!?—]+(?:쪽|방향)으로\s*갔[^.!?]*[.!?]?/u' => '',
    ];
    foreach ($patterns as $pat => $rep) {
        $out = preg_replace($pat, $rep, $out) ?? $out;
    }

    $out = trim(preg_replace('/\s+/u', ' ', $out) ?? $out);
    foreach ($snippets as $key => $snippet) {
        $out = str_replace($key, trim($snippet), $out);
    }

    return $out;
}

function eduCoachGuideNormalizeCompareKey(string $text): string
{
    $t = mb_strtolower(trim($text));
    $t = preg_replace('/[?？!！。．\.…,，、\s]+/u', '', $t) ?? $t;
    $t = preg_replace('/(을까|일까|할까|될까|는가|인가|겠어|거야|정말|진짜|것|거)$/u', '', $t) ?? $t;

    return $t;
}

function eduCoachGuideTextsOverlap(string $a, string $b): bool
{
    if ($a === '' || $b === '') {
        return false;
    }
    $ak = eduCoachGuideNormalizeCompareKey($a);
    $bk = eduCoachGuideNormalizeCompareKey($b);
    if ($ak === '' || $bk === '') {
        return false;
    }
    if ($ak === $bk || str_contains($ak, $bk) || str_contains($bk, $ak)) {
        return true;
    }
    similar_text($ak, $bk, $pct);

    return $pct >= 68.0;
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideHandleOpening(array $blueprint, array $quest, string $opening): array
{
    $axes = eduCoachGuideAxes($quest);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
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
function eduCoachGuideHandleTurn(array $blueprint, array $quest, string $message): array
{
    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideAxes($quest);

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

    if (eduCoachAxisStudentPass($message, $evasion)) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id']] = $message;
        $idx++;
        $stall = 0;

        if ($idx >= count($axes)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'guide_axis_stall' => 0,
                'phase' => 'guide_conclusion',
            ]);
            $msg = '지금까지 따져본 걸로 **네 결론**은 한 문장으로? '
                . "'나는 ~라고 본다'로만 — **네 말**이어야 해.";

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
        ]);
        $msg = "좋아, 다음으로 넘어가자.\n\n" . eduCoachGuideIntroAxis($axes[$idx], $idx, count($axes));

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
            $msg = "여기서 막혔구나 — 일단 넘어가자. **네 결론**은 한 문장으로? ('나는 ~라고 본다')";

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
        ]);
        $msg = "막히면 괜찮아 — **다음 축**으로 넘어가자.\n\n" . eduCoachGuideIntroAxis($axes[$idx], $idx, count($axes));

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useScaffold = $stall >= 2;
    $msg = eduCoachGuideEvasionReply($evasion ?? 'unknown', $axis, $idx, $axes, $useScaffold);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @param array<string, mixed> $blueprint
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer: bool}
 */
function eduCoachGuideHandleConclusion(array $blueprint, array $quest, string $message, array $axes): array
{
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
                'message' => '애매해도 괜찮아 — 다음 단계로 넘어가자. 아까 다른 시각 하나 짚어볼게.',
                'ui_hint' => 'hammer',
                'done_guide' => true,
                'advance_hammer' => true,
            ];
        }

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(
                '결론은 **내가 안 줘**. 지금까지 **네가** 말한 걸 바탕으로 — '
                . '**나는 ~라고 본다** 한 문장만. **네 생각**만 말해줘.'
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
        'message' => '고마워 — **네 결론** 받았어. 이제 다른 시각 하나 짚어볼게.',
        'ui_hint' => 'hammer',
        'done_guide' => true,
        'advance_hammer' => true,
    ];
}

/** @param array<string, string> $axis P2-B: article_fact_full for longer tiers */
function eduCoachGuideFactForDisplay(array $axis, string $displayMode = EDU_COACH_GUIDE_FACT_DISPLAY): string
{
    if ($displayMode === 'full' && !empty($axis['article_fact_full'])) {
        return trim((string) $axis['article_fact_full']);
    }
    if ($displayMode === 'medium' && !empty($axis['article_fact_medium'])) {
        return trim((string) $axis['article_fact_medium']);
    }

    return trim((string) ($axis['article_fact'] ?? ''));
}

function eduCoachGuideWrapArticleSnippet(string $text, string $displayMode = EDU_COACH_GUIDE_FACT_DISPLAY): string
{
    if ($text === '') {
        return '';
    }

    return "{{snippet|{$displayMode}}}\n{$text}\n{{/snippet}}";
}

/** @param array<string, string> $axis */
function eduCoachGuideIntroAxis(
    array $axis,
    int $index,
    int $total,
    string $openingContext = '',
    string $hookShort = ''
): string {
    unset($total);
    $point = $axis['point'] ?? '';
    $fact = eduCoachGuideFactForDisplay($axis);
    $q = $axis['core_question'] ?? '';
    $snippet = eduCoachGuideWrapArticleSnippet($fact);
    $snippetBlock = $snippet !== '' ? "\n\n{$snippet}" : '';

    $hookOverlap = $index === 0 && (
        ($hookShort !== '' && eduCoachGuideTextsOverlap($hookShort, $q))
        || ($openingContext !== '' && eduCoachGuideTextsOverlap($openingContext, $q))
    );

    if ($hookOverlap) {
        return '방금 말한 걸 더 따져보자. **' . $point . '**' . $snippetBlock
            . "\n\n위 기사 조각만 놓고 — 네 말을 **강하게** 해주나 **약하게** 해주나?";
    }

    $lead = $index === 0 ? '한 가지부터 따져보자.' : '이번엔 이걸 생각해보자.';

    return "{$lead} **{$point}**{$snippetBlock}\n\n{$q}";
}

/**
 * @param array<string, string> $axis
 * @param list<array<string, string>> $axes
 */
function eduCoachGuideEvasionReply(?string $evasion, array $axis, int $index, array $axes, bool $weakScaffold): string
{
    if ($weakScaffold && !empty($axis['weak_scaffold'])) {
        return $axis['weak_scaffold'];
    }

    return match ($evasion) {
        'both', 'list' => '예산·시간은 **하나씩**만 고를 수 있어. **1순위 하나**만 — '
            . ($axis['core_question'] ?? '지금 축에서 한쪽만 골라봐.'),
        'unknown' => '모르겠다는 건 아직 이 축을 안 따진 거야. '
            . ($axis['core_question'] ?? '')
            . ' **맞다 / 틀리다 / 잘 모르겠다** 셋 중 하나만?',
        'deflect' => '나라마다 다를 수 있어. 이 조각만 보고 — '
            . eduCoachGuideWrapArticleSnippet(eduCoachGuideFactForDisplay($axis))
            . ' 이게 네 생각을 **강하게** 해주나 **약하게** 해주나?',
        'defer_article' => '아까 **기사 조각** 중 어느 부분을 말하는 거야? 하나만 짚고, **그게 네 생각이랑** 같은지 다른지만 말해.',
        'ask_conclusion' => '결론은 **내가 안 줘**. 지금은 '
            . ($axis['point'] ?? '이 주제') . ' — ' . ($axis['core_question'] ?? ''),
        default => ($axis['core_question'] ?? '네 생각을 한 줄로 말해줘.'),
    };
}

/** @param array<string, mixed> $blueprint @return list<string> */
function eduCoachGuideReflectionLines(array $blueprint): array
{
    $opening = trim((string) ($blueprint['guide_opening'] ?? $blueprint['reason'] ?? ''));
    $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
    $conclusion = trim((string) ($blueprint['guide_student_conclusion'] ?? ''));
    $rebuttal = trim((string) ($blueprint['rebuttal'] ?? ''));

    $lines = [];
    if ($opening !== '') {
        $lines[] = '처음: ' . mb_substr($opening, 0, 60);
    }
    $i = 1;
    foreach ($answers as $key => $ans) {
        if (str_ends_with((string) $key, '_skipped')) {
            continue;
        }
        $lines[] = "축{$i}: " . mb_substr((string) $ans, 0, 50);
        $i++;
    }
    if ($conclusion !== '') {
        $lines[] = '네 결론: ' . mb_substr($conclusion, 0, 60);
    }
    if ($rebuttal !== '') {
        $lines[] = '다른 시각 들은 뒤: ' . mb_substr($rebuttal, 0, 40);
    }

    while (count($lines) < 3) {
        $lines[] = '네가 말한 내용을 바탕으로 생각이 이어졌어.';
    }

    return array_slice($lines, 0, 3);
}

/** @param array<string, mixed> $blueprint */
function eduCoachGuideBuildStudentReason(array $blueprint): string
{
    $parts = [];
    $opening = trim((string) ($blueprint['guide_opening'] ?? $blueprint['reason'] ?? ''));
    if ($opening !== '') {
        $parts[] = $opening;
    }
    $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
    foreach ($answers as $key => $ans) {
        if (str_ends_with((string) $key, '_skipped')) {
            continue;
        }
        $t = trim((string) $ans);
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    $conclusion = trim((string) ($blueprint['guide_student_conclusion'] ?? ''));
    if ($conclusion !== '') {
        $parts[] = $conclusion;
    }

    return implode(' / ', $parts);
}

/** @param array<string, mixed> $blueprint */
function eduCoachGuideProgress(array $blueprint): int
{
    $phase = (string) ($blueprint['phase'] ?? '');
    $idx = (int) ($blueprint['guide_axis_index'] ?? 0);
    $base = 15;
    if ($phase === 'guide_axis') {
        return min(55, $base + (int) (30 * ($idx / 3)));
    }
    if ($phase === 'guide_conclusion') {
        return 60;
    }
    if ($phase === 'hammer') {
        return 72;
    }
    if ($phase === 'reflection') {
        return 85;
    }

    return eduBlueprintProgress($blueprint);
}
