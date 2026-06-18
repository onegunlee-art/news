<?php
/**
 * GIST EDU — Quest catalog categories, arc mapping, questability (READ-only helpers)
 */
declare(strict_types=1);

/** @return array<string, array{label: string, arcs: list<string>}> */
function eduQuestCategoryDefinitions(): array
{
    return [
        'ai_tech' => [
            'label' => 'AI·기술',
            'arcs' => ['ARC-AI-JOBS', 'ARC-AI-GEOPOL', 'ARC-AI-SECURITY', 'ARC-CHIP-SUPPLY', 'ARC-AI-REGULATION', 'ARC-ENERGY-AI'],
        ],
        'us_china_trade' => [
            'label' => '미중·무역',
            'arcs' => ['ARC-US-CN-TRADE', 'ARC-TRUMP-TARIFF', 'ARC-SUPPLY-CHAIN'],
        ],
        'middle_east_iran' => [
            'label' => '중동·이란',
            'arcs' => ['ARC-IRAN-NUKE', 'ARC-IRAN-REGION', 'ARC-MIDEAST-CEASEFIRE'],
        ],
        'east_asia_security' => [
            'label' => '동아시아 안보',
            'arcs' => ['ARC-JAPAN-DEFENSE', 'ARC-TAIWAN-STRAIT', 'ARC-DPRK-PENINSULA', 'ARC-KOR-DEFENSE'],
        ],
        'europe_war' => [
            'label' => '유럽·전쟁',
            'arcs' => ['ARC-UKRAINE-WAR', 'ARC-NATO-EUROPE'],
        ],
        'energy_climate' => [
            'label' => '에너지·기후',
            'arcs' => ['ARC-CLIMATE-ENERGY', 'ARC-OIL-GAS', 'ARC-ENERGY-AI'],
        ],
        'global_economy' => [
            'label' => '세계경제',
            'arcs' => ['ARC-INFLATION-FED', 'ARC-SUPPLY-CHAIN'],
        ],
        'china_industry' => [
            'label' => '중국 산업',
            'arcs' => ['ARC-EV-CHINA'],
        ],
        'us_politics' => [
            'label' => '미국 정치',
            'arcs' => ['ARC-US-POLITICS', 'ARC-TRUMP-TARIFF'],
        ],
        'society_youth' => [
            'label' => '사회·청소년',
            'arcs' => ['ARC-AI-JOBS', 'ARC-SOCIETY-YOUTH'],
        ],
    ];
}

/** @return array<string, string> arc_code => category_id */
function eduQuestArcToCategoryMap(): array
{
    $map = [];
    foreach (eduQuestCategoryDefinitions() as $catId => $def) {
        foreach ($def['arcs'] as $arc) {
            if (!isset($map[$arc])) {
                $map[$arc] = $catId;
            }
        }
    }
    return $map;
}

function eduQuestCategoryForArc(string $arcCode): ?string
{
    return eduQuestArcToCategoryMap()[$arcCode] ?? null;
}

function eduQuestCategoryLabel(string $categoryId): string
{
    return eduQuestCategoryDefinitions()[$categoryId]['label'] ?? $categoryId;
}

/**
 * Student-facing shelves (6~7 chips on /edu/explore)
 * @return array<string, array{label: string, categories: list<string>}>
 */
function eduQuestShelfDefinitions(): array
{
    return [
        'ai_tech' => [
            'label' => 'AI·기술',
            'categories' => ['ai_tech'],
        ],
        'economy_trade' => [
            'label' => '경제·무역',
            'categories' => ['us_china_trade', 'global_economy', 'china_industry'],
        ],
        'war_security' => [
            'label' => '전쟁·안보',
            'categories' => ['middle_east_iran', 'east_asia_security', 'europe_war'],
        ],
        'energy_climate' => [
            'label' => '에너지·기후',
            'categories' => ['energy_climate'],
        ],
        'us_politics' => [
            'label' => '미국·정치',
            'categories' => ['us_politics'],
        ],
        'society' => [
            'label' => '사회',
            'categories' => ['society_youth'],
        ],
    ];
}

function eduQuestShelfLabel(string $shelfId): string
{
    return eduQuestShelfDefinitions()[$shelfId]['label'] ?? $shelfId;
}

/** @return list<string> */
function eduQuestCategoriesForShelf(string $shelfId): array
{
    return eduQuestShelfDefinitions()[$shelfId]['categories'] ?? [];
}

function eduQuestShelfForCategory(string $categoryId): ?string
{
    foreach (eduQuestShelfDefinitions() as $shelfId => $def) {
        if (in_array($categoryId, $def['categories'], true)) {
            return $shelfId;
        }
    }
    return null;
}

/** @return array<string, array<string, mixed>> */
function eduQuestLensDefinitions(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = dirname(__DIR__, 4) . '/docs/GIST_EDU_LENSES.json';
    if (!is_file($path)) {
        $cache = [];
        return $cache;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    $cache = is_array($raw['lenses'] ?? null) ? $raw['lenses'] : [];
    return $cache;
}

function eduQuestLensLabel(string $lensId): string
{
    $defs = eduQuestLensDefinitions();
    return (string) ($defs[$lensId]['label'] ?? $lensId);
}

/**
 * @param array<string, mixed> $quest
 * @return array<string, mixed>
 */
function eduQuestRawHammerHints(array $quest): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }
    return is_array($hints) ? $hints : [];
}

/**
 * Resolve quest_frame — null/missing AUTO quests count as decision_inquiry
 * @param array<string, mixed> $hints
 */
function eduQuestResolvedFrame(array $hints): string
{
    $frame = trim((string) ($hints['quest_frame'] ?? ''));
    if ($frame === '') {
        return 'decision_inquiry';
    }
    return $frame;
}

/**
 * @param array<string, mixed> $quest edu_daily_quests row
 * @return array<string, mixed>
 */
function eduQuestParseScores(array $quest): array
{
    $scores = $quest['scores'] ?? [];
    if (is_string($scores)) {
        $scores = json_decode($scores, true) ?: [];
    }
    return is_array($scores) ? $scores : [];
}

/**
 * @param array<string, mixed> $quest
 */
function eduQuestListCategoryMeta(array $quest): array
{
    $scores = eduQuestParseScores($quest);
    $category = (string) ($scores['category'] ?? '');
    if ($category === '') {
        $category = eduQuestCategoryForArc((string) ($quest['manual_arc'] ?? '')) ?? '';
    }
    $lens = (string) ($scores['lens'] ?? '');
    $shelf = $category !== '' ? eduQuestShelfForCategory($category) : null;

    return [
        'category' => $category !== '' ? $category : null,
        'category_label' => $category !== '' ? eduQuestCategoryLabel($category) : null,
        'shelf' => $shelf,
        'shelf_label' => $shelf !== null ? eduQuestShelfLabel($shelf) : null,
        'lens' => $lens !== '' ? $lens : null,
        'lens_label' => $lens !== '' ? eduQuestLensLabel($lens) : null,
    ];
}

/**
 * @param array<string, mixed> $quest
 */
function eduQuestMatchesFrameFilter(array $quest, string $frame): bool
{
    $hints = eduQuestRawHammerHints($quest);
    $resolved = eduQuestResolvedFrame($hints);
    if ($frame === 'all') {
        return true;
    }
    if ($frame === 'myth_bust') {
        return $resolved === 'myth_bust';
    }
    if ($frame === 'decision_inquiry') {
        return $resolved === 'decision_inquiry';
    }
    return $resolved === $frame;
}

/**
 * @param array<string, mixed> $quest
 * @param list<string> $categoryIds empty = no filter
 */
function eduQuestMatchesCategoryFilter(array $quest, array $categoryIds): bool
{
    if ($categoryIds === []) {
        return true;
    }
    $meta = eduQuestListCategoryMeta($quest);
    $cat = (string) ($meta['category'] ?? '');
    return $cat !== '' && in_array($cat, $categoryIds, true);
}

/**
 * @param array<string, mixed> $quest
 * @param array<int, true> $completedIds
 * @return array<string, mixed>
 */
function eduQuestToListItem(array $quest, array $completedIds = []): array
{
    $hints = eduQuestRawHammerHints($quest);
    $meta = eduQuestListCategoryMeta($quest);
    $questId = (string) ($quest['id'] ?? '');
    $liveAt = $quest['live_at'] ?? null;
    $now = time();
    $isLive = $liveAt !== null && $liveAt !== '' && strtotime((string) $liveAt) <= $now;
    $resolvedFrame = eduQuestResolvedFrame($hints);

    return [
        'quest_id' => $questId,
        'quest_code' => $quest['quest_code'] ?? '',
        'quest_title' => $quest['quest_title'] ?? '',
        'pro_line' => $quest['pro_line'] ?? '',
        'con_line' => $quest['con_line'] ?? '',
        'conflict_summary' => $quest['conflict_summary'] ?? '',
        'grade_band' => $quest['grade_band'] ?? 'middle',
        'time_anchor' => $hints['time_anchor'] ?? null,
        'quest_frame' => $resolvedFrame,
        'category' => $meta['category'],
        'category_label' => $meta['category_label'],
        'shelf' => $meta['shelf'],
        'shelf_label' => $meta['shelf_label'],
        'lens' => $meta['lens'],
        'lens_label' => $meta['lens_label'],
        'subtitle' => $meta['lens_label'] ?? ($hints['shared_conclusion'] ?? null),
        'is_live' => $isLive,
        'live_at' => $liveAt,
        'completed' => isset($completedIds[$questId]),
    ];
}

/**
 * Sprint 0 pool news_ids (for "already cataloged" detection)
 * @return array<int, true>
 */
function eduQuestSprint0NewsIds(): array
{
    $ids = [
        126, 248, 267, 270, 366, 72, 288, 297, 462, 507, 371, 402,
        220, 240, 513, 532, 558, 93, 193, 195, 291, 225, 299, 392, 459, 506,
        150, 210, 338, 375, 432, 152, 196, 233, 238, 263, 132, 290, 384, 437, 528,
        433, 452, 546, 287, 496, 119, 427, 514, 521, 237, 397, 497, 503,
        87, 252, 283,
    ];
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = true;
    }
    return $out;
}

/**
 * Questability heuristic 0~15 (Sprint 0 rubric simplified)
 * @param array<string, mixed> $article
 */
function eduQuestScoreArticle(array $article): array
{
    $category = (string) ($article['category'] ?? '');
    $title = (string) ($article['title'] ?? '');
    $topic = (string) ($article['topic_label'] ?? '');
    $text = mb_strtolower($title . ' ' . $topic);

    $noAnswer = 4;
    $life = 3;
    $debate = 4;

    if (preg_match('/(사망|학대|성범|테러|잔혹|학살)/u', $text)) {
        return ['total' => 0, 'no_answer' => 0, 'life' => 0, 'debate' => 0, 'safety' => 'N', 'note' => 'sensitive'];
    }

    if (str_contains($category, 'society') || preg_match('/(청소년|학생|교육|일자리)/u', $text)) {
        $life = 5;
    }
    if (preg_match('/(관세|찬성|반대|해야|말아|vs|딜레마|양자택일)/u', $text)) {
        $debate = 5;
    }
    if (preg_match('/(전쟁|핵|관세|규제|지원|협상|개방|금지)/u', $text)) {
        $noAnswer = 5;
    }

    $total = $noAnswer + $life + $debate;

    return [
        'total' => $total,
        'no_answer' => $noAnswer,
        'life' => $life,
        'debate' => $debate,
        'safety' => $total >= 10 ? 'Y' : 'M',
        'note' => $total >= 12 ? 'quest_ready' : ($total >= 9 ? 'review' : 'low'),
    ];
}

/** @return list<string> */
function eduQuestMatchArcsForArticle(array $article, array $arcKeywords): array
{
    $haystack = mb_strtolower(implode(' ', [
        (string) ($article['title'] ?? ''),
        (string) ($article['topic_label'] ?? ''),
        (string) ($article['category'] ?? ''),
    ]));

    $matched = [];
    foreach ($arcKeywords as $arc => $keywords) {
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($haystack, mb_strtolower($kw))) {
                $matched[] = $arc;
                break;
            }
        }
    }
    return array_values(array_unique($matched));
}

/**
 * Fallback when MySQL unavailable — judgement_records + analysis_embeddings
 * @return array<int, array<string, mixed>>
 */
function eduQuestLoadArticlesFromJudgement($supabase, int $lookbackDays, int $limit): array
{
    $since = date('c', strtotime("-{$lookbackDays} days"));
    $rows = $supabase->select(
        'judgement_records',
        'created_at=gte.' . rawurlencode($since) . '&order=created_at.desc',
        $limit
    ) ?? [];

    $articles = [];
    foreach ($rows as $row) {
        $nid = (int) ($row['news_id'] ?? 0);
        if ($nid < 1 || isset($articles[$nid])) {
            continue;
        }
        $human = $row['human_output'] ?? [];
        if (is_string($human)) {
            $human = json_decode($human, true) ?: [];
        }
        $title = trim((string) ($human['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $articles[$nid] = [
            'news_id' => $nid,
            'title' => $title,
            'category' => (string) ($human['category'] ?? ''),
            'topic_label' => '',
            'published_at' => $row['created_at'] ?? null,
            'gist_url' => 'https://www.thegist.co.kr/news/' . $nid,
            'excerpt' => mb_substr(strip_tags((string) ($human['narration'] ?? '')), 0, 300),
            'why_important' => strip_tags((string) ($human['why_important'] ?? '')),
        ];
    }

    if ($articles === []) {
        return [];
    }

    foreach (array_chunk(array_keys($articles), 50) as $chunk) {
        $filter = 'news_id=in.(' . implode(',', $chunk) . ')&chunk_type=eq.published';
        $embedRows = $supabase->select('analysis_embeddings', $filter, 200) ?? [];
        foreach ($embedRows as $er) {
            $nid = (int) ($er['news_id'] ?? 0);
            if (!isset($articles[$nid])) {
                continue;
            }
            $meta = $er['metadata'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $label = trim((string) ($meta['topic_label'] ?? ''));
            if ($label !== '') {
                $articles[$nid]['topic_label'] = $label;
            }
        }
    }

    return $articles;
}
