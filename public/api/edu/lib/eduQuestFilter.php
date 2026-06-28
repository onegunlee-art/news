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

    return [
        'score' => max(0, min(100, $score)),
        'breakdown' => $breakdown,
        'axis_count' => $axisCount,
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
    $score = $strength['score'];
    $hinge = trim((string) ($extraction['hinge'] ?? ''));
    $confidence = strtolower(trim((string) ($extraction['confidence'] ?? '')));

    $reasons = [];

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
