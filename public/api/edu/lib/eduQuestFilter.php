<?php
/**
 * GIST EDU — 퀘스트 가능 여부 거름망 (Step 1, READ-only)
 *
 * 경첩 추출 결과 + 기사 메타 → 가능/불가/경계 + 강도 점수.
 * edu_daily_quests·본체 DB 쓰기 없음.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';
require_once __DIR__ . '/eduQuestCatalog.php';
require_once __DIR__ . '/eduQuestArticleSnapshot.php';

const EDU_QUEST_FILTER_ELIGIBLE_DEFAULT = 55;
const EDU_QUEST_FILTER_BORDERLINE_DEFAULT = 40;

/** Step 2 — 선언문·연설 blocklist (이원근 확정, draft purge용) */
const EDU_QUEST_FILTER_DECLARATION_NEWS_IDS = [
    647, 650, 651, 652, 653, 654, 656, 657, 658,
    590, 591, 592, 593, 594, 595, 596, 597, 598,
];

/**
 * @return list<int>
 */
function eduQuestFilterKnownDeclarationNewsIds(): array
{
    return EDU_QUEST_FILTER_DECLARATION_NEWS_IDS;
}

/**
 * 선언문/연설 vs 분석글 형식 판정.
 *
 * @param array<string, mixed> $meta title, category, topic_label, content_preview?
 * @param array<string, mixed>|null $extraction hinge fields
 * @return array{
 *   is_declaration: bool,
 *   kind: string,
 *   label: string,
 *   reasons: list<string>,
 *   analysis_override: bool
 * }
 */
function eduQuestFilterDeclarationCheck(array $meta, ?array $extraction = null): array
{
    $title = (string) ($meta['title'] ?? '');
    $topic = (string) ($meta['topic_label'] ?? '');
    $category = (string) ($meta['category'] ?? '');
    $preview = (string) ($meta['content_preview'] ?? '');
    $text = mb_strtolower($title . ' ' . $topic . ' ' . $category . ' ' . $preview);
    $newsId = (int) ($meta['news_id'] ?? 0);

    $reasons = [];
    $analysisOverride = (bool) preg_match(
        '/(비판|분석|함정|딜레마|어중간|한계|논쟁|문제는|왜\s|why|paradox|통념|깨|뒤집|함정|trade-off|양자택일)/ui',
        $title
    );
    if ($analysisOverride) {
        return [
            'is_declaration' => false,
            'kind' => 'analysis',
            'label' => '분석글(유지)',
            'reasons' => ['제목·주제가 선언 비판/분석형'],
            'analysis_override' => true,
        ];
    }

    if ($newsId > 0 && in_array($newsId, eduQuestFilterKnownDeclarationNewsIds(), true)) {
        return [
            'is_declaration' => true,
            'kind' => 'known_blocklist',
            'label' => '선언문·연설(확정 제외)',
            'reasons' => ["news_id={$newsId} blocklist"],
            'analysis_override' => false,
        ];
    }

    $declarationPatterns = [
        'g7' => '/\bG7\b|지\s*7\s*국|G-?7/u',
        'g20' => '/\bG20\b|G-?20/u',
        'summit_decl' => '/정상회의|정상\s*선언|공동\s*선언|합의\s*문|성명|촉구|communiqué|communique|joint\s*statement/u',
        'shangri' => '/샹그릴라|shangri/u',
        'speech' => '/국방\s*장관\s*연설|방위\s*상\s*연설|장관\s*연설|defence\s*minister.*speech|keynote/u',
        'official' => '/공식\s*발표|선언문\s*요약|선언\s*전문|회의\s*결과\s*요약/u',
    ];

    $kind = '';
    foreach ($declarationPatterns as $key => $pat) {
        if (preg_match($pat, $text)) {
            $kind = $key;
            $reasons[] = "형식 시그널: {$key}";
            break;
        }
    }

    if ($kind === '' && $extraction !== null) {
        $hinge = trim((string) ($extraction['hinge'] ?? ''));
        $weak = eduQuestFilterWeakTensionCheck($hinge, $extraction);
        if ($weak['weak'] && $weak['declaration_like']) {
            $kind = 'weak_hinge';
            $reasons = array_merge($reasons, $weak['reasons']);
        }
    }

    if ($kind === '') {
        return [
            'is_declaration' => false,
            'kind' => 'analysis',
            'label' => '분석글 후보',
            'reasons' => ['선언문 시그널 없음'],
            'analysis_override' => false,
        ];
    }

    return [
        'is_declaration' => true,
        'kind' => $kind,
        'label' => '선언문·연설(제외)',
        'reasons' => $reasons,
        'analysis_override' => false,
    ];
}

/**
 * @param array<string, mixed> $extraction
 * @return array{weak: bool, declaration_like: bool, reasons: list<string>}
 */
function eduQuestFilterWeakTensionCheck(string $hinge, array $extraction): array
{
    $reasons = [];
    $sideA = trim((string) ($extraction['side_a'] ?? ''));
    $sideB = trim((string) ($extraction['side_b'] ?? ''));

    $hasStrongPivot = (bool) preg_match('/(이지만|그러나|하지만|반면)/u', $hinge);
    $softExpansion = (bool) preg_match('/(동시에|로도\s*볼\s*수|것으로\s*보이지만|듯하지만)/u', $hinge);

    if ($softExpansion && !$hasStrongPivot) {
        $reasons[] = '경첩: 동시에/확장형 (통념 깨기 약함)';
    }

    if ($sideB !== '' && $sideA !== '') {
        $coop = preg_match('/(국제\s*협력|협력|연대|공동|다자)/u', $sideB);
        $problem = preg_match('/(문제|위협|갈등|딜레마|함정)/u', $sideA);
        if ($coop && $problem && !$hasStrongPivot) {
            $reasons[] = 'side_b가 협력 당연론 — 따질 긴장 약함';
        }
    }

    $weak = $reasons !== [];
    $declarationLike = $softExpansion || ($reasons !== [] && !$hasStrongPivot);

    return [
        'weak' => $weak,
        'declaration_like' => $declarationLike,
        'reasons' => $reasons,
    ];
}

/** @return list<int> */
function eduQuestFilterDefaultSampleIds(): array
{
    return [
        630, 196, 150, 288, 371, 220, 546, 621, 631, 555, 618, 570,
        528, 452, 514, 459, 507, 475, 449, 615, 126, 248, 267,
    ];
}

/**
 * MySQL published에서 다양한 샘플 N개 (최근·과거·점수 분산).
 *
 * @return list<int>
 */
function eduQuestFilterPickSampleFromMysql(PDO $pdo, int $count = 25): array
{
    $count = max(5, min(50, $count));
    $known = array_values(array_unique(eduQuestFilterDefaultSampleIds()));
    $picked = [];
    $seen = [];

    foreach ($known as $id) {
        if (count($picked) >= (int) floor($count * 0.6)) {
            break;
        }
        $row = eduSnapshotLoadNewsRow($pdo, $id);
        if ($row !== null && ($row['status'] ?? '') === 'published') {
            $picked[] = $id;
            $seen[$id] = true;
        }
    }

    try {
        $st = $pdo->query(
            "SELECT id, title, category, published_at FROM news
             WHERE status = 'published'
             ORDER BY published_at DESC
             LIMIT 400"
        );
        $recent = [];
        $old = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $recent[] = $row;
            $seen[$id] = true;
        }

        $stOld = $pdo->query(
            "SELECT id, title, category, published_at FROM news
             WHERE status = 'published'
             ORDER BY published_at ASC
             LIMIT 200"
        );
        while ($row = $stOld->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $old[] = $row;
        }

        $candidates = array_merge($recent, $old);
        $low = [];
        $high = [];
        foreach ($candidates as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || in_array($id, $picked, true)) {
                continue;
            }
            $score = eduQuestScoreArticle($row);
            if (($score['safety'] ?? '') === 'N') {
                continue;
            }
            if (($score['note'] ?? '') === 'low') {
                $low[] = $id;
            } else {
                $high[] = $id;
            }
        }

        foreach ($high as $id) {
            if (count($picked) >= $count) {
                break;
            }
            if (!in_array($id, $picked, true)) {
                $picked[] = $id;
            }
        }
        foreach ($low as $id) {
            if (count($picked) >= $count) {
                break;
            }
            if (!in_array($id, $picked, true)) {
                $picked[] = $id;
            }
        }
    } catch (Throwable $e) {
        // keep known-only sample
    }

    if ($picked === []) {
        return array_slice($known, 0, $count);
    }

    return array_slice(array_values(array_unique($picked)), 0, $count);
}

/** @param array<string, mixed> $meta */
function eduQuestFilterTimeliness(array $meta): array
{
    $title = (string) ($meta['title'] ?? '');
    $topic = (string) ($meta['topic_label'] ?? '');
    $category = (string) ($meta['category'] ?? '');
    $text = $title . ' ' . $topic . ' ' . $category;

    $evergreen = (bool) preg_match(
        '/(핵|기후|AI|관세|무역|대만|우크라|이란|북한|유가|인플레|칩|반도체|청소년|교육|에너지|NATO|중동)/u',
        $text
    );
    $timeSensitive = (bool) preg_match(
        '/(선거|지난주|어제|속보|결과 발표|당선|합의|휴전|개표|표결|긴급|오늘|이번 주)/u',
        $text
    );
    $infoOnly = (bool) preg_match('/(란\?|이란|무엇인가|총정리|설명|가이드|소개)$/u', $title);

    $ageDays = null;
    $publishedAt = $meta['published_at'] ?? null;
    if ($publishedAt !== null && $publishedAt !== '') {
        $ts = strtotime((string) $publishedAt);
        if ($ts !== false) {
            $ageDays = (int) floor((time() - $ts) / 86400);
        }
    }

    if ($infoOnly && !$evergreen) {
        return [
            'label' => '정보 전달형',
            'kind' => 'info',
            'age_days' => $ageDays,
            'hint' => '제목이 설명·정리형 — 긴장 약할 수 있음',
        ];
    }

    if ($ageDays !== null && $ageDays > 120 && $timeSensitive && !$evergreen) {
        return [
            'label' => '시의성 지남',
            'kind' => 'stale',
            'age_days' => $ageDays,
            'hint' => '시점 뉴스 — 퀘스트로는 주제형만 재활용 가능',
        ];
    }

    if ($evergreen) {
        return [
            'label' => '주제형(지속)',
            'kind' => 'evergreen',
            'age_days' => $ageDays,
            'hint' => '시간이 지나도 따질 주제',
        ];
    }

    if ($ageDays !== null && $ageDays <= 60) {
        return [
            'label' => '최근',
            'kind' => 'recent',
            'age_days' => $ageDays,
            'hint' => '시의성 있음 — 경첩만 충분하면 OK',
        ];
    }

    return [
        'label' => '보통',
        'kind' => 'neutral',
        'age_days' => $ageDays,
        'hint' => '시의성·주제형 중간',
    ];
}

/**
 * @param array<string, mixed>|null $extraction
 * @param array<string, mixed> $meta
 * @return array{score: int, breakdown: list<string>, axis_count: int|null}
 */
function eduQuestFilterStrengthScore(?array $extraction, array $meta): array
{
    $breakdown = [];
    $score = 0;
    $axisCount = null;

    if ($extraction === null) {
        return ['score' => 0, 'breakdown' => ['경첩 추출 없음'], 'axis_count' => null];
    }

    $hinge = trim((string) ($extraction['hinge'] ?? ''));
    $sideA = trim((string) ($extraction['side_a'] ?? ''));
    $sideB = trim((string) ($extraction['side_b'] ?? ''));
    $confidence = strtolower(trim((string) ($extraction['confidence'] ?? '')));
    $shake = trim((string) ($extraction['shake_prompt'] ?? ''));
    $hook = trim((string) ($extraction['hook_student'] ?? ''));

    if ($hinge === '') {
        $breakdown[] = '경첩 없음 (−)';
    } else {
        $score += 15;
        $breakdown[] = '경첩 있음 (+15)';
        if (preg_match('/(이지만|그러나|하지만|동시에|반면|vs|VS|한편)/u', $hinge)) {
            $score += 20;
            $breakdown[] = 'A이지만 B 긴장 (+20)';
        } elseif (mb_strlen($hinge) >= 18) {
            $score += 10;
            $breakdown[] = '경첩 문장 충분 (+10)';
        }
    }

    if ($sideA !== '' && mb_strlen($sideA) >= 8) {
        $score += 10;
        $breakdown[] = 'side_a (+10)';
    }
    if ($sideB !== '' && mb_strlen($sideB) >= 8) {
        $score += 15;
        $breakdown[] = 'side_b (+15)';
    }

    if ($confidence === 'high') {
        $score += 20;
        $breakdown[] = 'confidence high (+20)';
    } elseif ($confidence === 'medium') {
        $score += 12;
        $breakdown[] = 'confidence medium (+12)';
    } elseif ($confidence === 'low') {
        $breakdown[] = 'confidence low (0)';
    } else {
        $breakdown[] = 'confidence 없음 (0)';
    }

    if ($shake !== '' && mb_strlen($shake) >= 25) {
        $score += 8;
        $breakdown[] = 'shake_prompt 구체 (+8)';
    }
    if ($hook !== '') {
        $score += 5;
        $breakdown[] = 'hook_student (+5)';
    }

    $heuristic = eduQuestScoreArticle($meta);
    if (($heuristic['safety'] ?? '') === 'N') {
        return ['score' => 0, 'breakdown' => ['민감 키워드 — 제외'], 'axis_count' => null];
    }
    $hTotal = (int) ($heuristic['total'] ?? 0);
    if ($hTotal >= 12) {
        $score += 10;
        $breakdown[] = '제목 휴리스틱 quest_ready (+10)';
    } elseif ($hTotal <= 8) {
        $score -= 8;
        $breakdown[] = '제목 휴리스틱 low (−8)';
    }

    $axisPath = eduHingeProjectRoot() . '/docs/axis_extractions/' . (int) ($extraction['news_id'] ?? 0) . '.json';
    if (is_file($axisPath)) {
        $axisData = json_decode((string) file_get_contents($axisPath), true);
        if (is_array($axisData['axes'] ?? null)) {
            $axisCount = count($axisData['axes']);
            if ($axisCount >= 2) {
                $score += 10;
                $breakdown[] = "축 {$axisCount}개 (+10)";
            }
        }
    }

    $timeliness = eduQuestFilterTimeliness($meta);
    if (($timeliness['kind'] ?? '') === 'stale') {
        $score -= 12;
        $breakdown[] = '시의성 지남 (−12)';
    } elseif (($timeliness['kind'] ?? '') === 'info') {
        $score -= 10;
        $breakdown[] = '정보 전달형 (−10)';
    }

    $decl = eduQuestFilterDeclarationCheck($meta, $extraction);
    if ($decl['is_declaration']) {
        $score -= 35;
        $breakdown[] = '선언문·연설 형식 (−35)';
    } elseif (($decl['analysis_override'] ?? false) === true) {
        $score += 5;
        $breakdown[] = '분석글 시그널 (+5)';
    }

    $weak = eduQuestFilterWeakTensionCheck($hinge, $extraction ?? []);
    if ($weak['weak'] && !$decl['is_declaration']) {
        $score -= 15;
        $breakdown[] = '약한 경첩 긴장 (−15)';
    }

    return [
        'score' => max(0, min(100, $score)),
        'breakdown' => $breakdown,
        'axis_count' => $axisCount,
        'declaration' => $decl,
        'weak_tension' => $weak,
    ];
}

/**
 * @param array<string, mixed>|null $extraction
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function eduQuestFilterClassify(?array $extraction, array $meta, int $eligibleMin = EDU_QUEST_FILTER_ELIGIBLE_DEFAULT, int $borderlineMin = EDU_QUEST_FILTER_BORDERLINE_DEFAULT): array
{
    $strength = eduQuestFilterStrengthScore($extraction, $meta);
    $timeliness = eduQuestFilterTimeliness($meta);
    $declaration = $strength['declaration'] ?? eduQuestFilterDeclarationCheck($meta, $extraction);
    $score = $strength['score'];
    $hinge = trim((string) ($extraction['hinge'] ?? ''));
    $confidence = strtolower(trim((string) ($extraction['confidence'] ?? '')));

    $reasons = [];

    if (($declaration['is_declaration'] ?? false) === true) {
        return [
            'verdict' => '불가',
            'verdict_en' => 'ineligible',
            'score' => $score,
            'reasons' => array_merge(
                [$declaration['label'] ?? '선언문·연설'],
                $declaration['reasons'] ?? []
            ),
            'strength' => $strength,
            'timeliness' => $timeliness,
            'declaration' => $declaration,
        ];
    }

    if (($strength['breakdown'][0] ?? '') === '민감 키워드 — 제외') {
        return [
            'verdict' => '불가',
            'verdict_en' => 'ineligible',
            'score' => 0,
            'reasons' => ['민감·노출 부적합 주제'],
            'strength' => $strength,
            'timeliness' => $timeliness,
        ];
    }

    if ($extraction === null || $hinge === '') {
        $reasons[] = '경첩(A이지만 B) 없음 — 따질 긴장 부재';
        return [
            'verdict' => '불가',
            'verdict_en' => 'ineligible',
            'score' => $score,
            'reasons' => $reasons,
            'strength' => $strength,
            'timeliness' => $timeliness,
        ];
    }

    if ($confidence === 'low') {
        $reasons[] = 'LLM confidence low — 긴장 불확실';
    }

    if ($score >= $eligibleMin && $confidence !== 'low') {
        $reasons[] = "강도 {$score}≥{$eligibleMin}, 경첩·양면 뚜렷";
        if (($timeliness['kind'] ?? '') === 'stale') {
            $reasons[] = '단, 시의성 지남 — 주제형 재프레이밍 필요할 수 있음';
        }
        return [
            'verdict' => '가능',
            'verdict_en' => 'eligible',
            'score' => $score,
            'reasons' => $reasons,
            'strength' => $strength,
            'timeliness' => $timeliness,
        ];
    }

    if ($score >= $borderlineMin || ($confidence === 'medium' && $hinge !== '')) {
        $reasons[] = "강도 {$score} — 경계(사람 확인)";
        if ($confidence === 'low') {
            $reasons[] = 'confidence 보강 또는 edit 검수 권장';
        }
        return [
            'verdict' => '경계',
            'verdict_en' => 'borderline',
            'score' => $score,
            'reasons' => $reasons,
            'strength' => $strength,
            'timeliness' => $timeliness,
        ];
    }

    $reasons[] = "강도 {$score}<{$borderlineMin} — 단순 정보·약한 긴장";
    if (($timeliness['kind'] ?? '') === 'info') {
        $reasons[] = '정보 전달형 제목';
    }

    return [
        'verdict' => '불가',
        'verdict_en' => 'ineligible',
        'score' => $score,
        'reasons' => $reasons,
        'strength' => $strength,
        'timeliness' => $timeliness,
    ];
}

/** @param array<string, mixed> $meta */
function eduQuestFilterLoadArticleMeta(PDO $pdo, int $newsId): ?array
{
    require_once __DIR__ . '/eduQuestArticleSnapshot.php';

    $row = eduSnapshotLoadNewsRow($pdo, $newsId);
    if ($row === null) {
        return null;
    }

    return [
        'news_id' => $newsId,
        'title' => (string) ($row['title'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'topic_label' => (string) ($row['topic_label'] ?? ''),
        'published_at' => $row['published_at'] ?? null,
        'status' => $row['status'] ?? '',
    ];
}
