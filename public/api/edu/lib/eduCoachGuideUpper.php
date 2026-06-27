<?php
/**
 * EDU coach L4 — 근거+다층 입문 (고1~2). L3(v1)보다 근거 강조, L5보다 얕음.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachGuide.php';

/** @return array<string, array<string, string>> */
function eduCoachGuideUpperAxisOverlays(int $newsId): array
{
    return match ($newsId) {
        630 => [
            'military' => [
                'upper_lead' => '근거부터 — 2025년 6월 **드론 타격·재래식 보복** 사례.',
            ],
            'norms' => [
                'upper_lead' => '근거 — **인도·파키스탄** 핵 시설 비공격 목록 교환.',
            ],
            'defense' => [
                'upper_lead' => '근거 — **드론 떼·방공·기지 회복탄력** vs **핵 현대화** 예산.',
            ],
        ],
        150 => [
            'scale' => [
                'upper_lead' => '근거 — 애슈번 **150개·필라델피아급 전력**.',
            ],
            'policy' => [
                'upper_lead' => '근거 — **자체 전력·요금 동결** 서약.',
            ],
            'grid_investment' => [
                'upper_lead' => '근거 — **송전망·시장 제약** vs **AI 수요**.',
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
    if (!empty($overlay['upper_lead']) && !empty($axis['weak_scaffold'])) {
        $axis['weak_scaffold'] = $overlay['upper_lead'] . ' ' . $axis['weak_scaffold'];
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideUpperAxes(array $quest): array
{
    $newsId = eduCoachGuideNewsIdFromQuest($quest);
    $adapted = [];
    foreach (eduCoachGuideAxes($quest) as $axis) {
        $adapted[] = eduCoachGuideUpperAdaptAxis($axis, $newsId);
    }

    return $adapted;
}

/** @param array<string, string> $axis */
function eduCoachGuideUpperIntroAxis(
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
        return '방금 말한 걸 **근거**로 따져보자. **' . $point . '**' . $snippetBlock
            . "\n\n위 조각만 놓고 — 네 말을 **강하게** 해주나 **약하게** 해주나?";
    }

    $lead = $index === 0
        ? '근거부터 — 한 층 더 따져보자.'
        : '다음 근거 —';

    return "{$lead} **{$point}**{$snippetBlock}\n\n{$q}";
}
