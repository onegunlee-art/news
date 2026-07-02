<?php
/**
 * EDU 퀘스트 난이도 — LLM 판정 (품질 ≠ 난이도)
 *
 * 중고등학생 기준 L1~L5. 거름망 점수(품질)와 분리.
 * 캐시: docs/difficulty_ratings/{quest_code}.json
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachLevel.php';
require_once __DIR__ . '/eduQuestCatalog.php';
require_once __DIR__ . '/eduQuestDifficulty.php';
require_once __DIR__ . '/eduLlmJson.php';
require_once __DIR__ . '/_llm.php';

function eduQuestDifficultyRatingsDir(): string
{
    return eduFindProjectRoot() . 'docs/difficulty_ratings';
}

function eduQuestDifficultyRatingPath(string $questCode): string
{
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $questCode) ?: 'unknown';

    return eduQuestDifficultyRatingsDir() . '/' . $safe . '.json';
}

function eduQuestDifficultyEnsureRatingsDir(): void
{
    $dir = eduQuestDifficultyRatingsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function eduQuestDifficultyLlmSystemPrompt(): string
{
    return <<<'PROMPT'
당신은 the gist EDU 퀘스트 난이도 분류기입니다.

★ 핵심: "난이도"는 "글 품질"이 아닙니다.
- the gist 글은 모두 고품질입니다. L1 = 질 낮음이 아닙니다.
- L1 = 중고등학생에게 **친숙하고 접근하기 쉬운** 주제·개념
- L5 = **낯선·추상·배경지식 많이 필요**한 주제·개념

코치 레벨 (L1~L5, 반드시 이 정수만):
- L1 관찰자: 일상·뉴스 흐름, 거의 배경지식 없이 시작 가능
- L2 질문자: 익숙한 이슈, 약간의 배경(핵·AI 일자리 등) 있으면 따라감
- L3 논객: 일반 시사지만 여러 이해관계·개념 연결 필요
- L4 분석가: 낯선 지역·전문 영역·추상 시스템 이해 필요
- L5 칼럼니스트: 고도 추상·전문 이론·다층 구조 (RSI, 양자, 복잡 지정학)

판정 신호 (품질 점수 사용 금지):
1. 주제 친숙도 — 친숙(전기세·청소년 AI·AI 일자리) vs 낯섦(아프리카 핀테크·이주 메커니즘)
2. 개념 추상도 — 구체 사건 vs 추상 이론·시스템
3. 배경지식 — 한국·일상 연결 vs 낯선 지역·전문 영역
4. 경첩 복잡도 — 단순 찬반 vs 다층·연쇄
5. 어휘·문장 — 쉬운 설명 vs 전문 용어·긴 논증

★ 앵커 (반드시 참고, 품질과 무관):
[낮음 L1–L2]
- "전기세 폭등의 주범, AI데이터센터!?" → L1 또는 L2 (일상·친숙)
- "청소년 AI 사용에 대한 바람직한 관리 방안" → L1 또는 L2
- "AI가 일자리를 대체한다" 류 → L1–L2

[중간 L2–L3]
- "핵 있으면 정말 안전할까?" → L2–L3 (친숙하지만 개념 있음)
- 일반 국제 정치·무역 뉴스 → L3 전후

[높음 L4–L5]
- "재귀적 자기개선(RSI)" → L4 또는 L5 (고도 추상)
- "양자기술과 국가 안보" → L4 또는 L5
- "아프리카 핀테크·스티어스" 류 → L4–L5 (낯선 맥락)
- "이주·메커니즘·복합 시스템" 류 → L4–L5

목표: 전체 풀에서 L1~L5가 **고르게** 퍼지도록 — 너무 많은 L3 피하기.
difficulty_score: 0(가장 쉬움)~100(가장 어려움), 품질과 무관.

JSON만 출력:
{
  "difficulty_level": 1,
  "difficulty_score": 25,
  "signals": {
    "topic_familiarity": "high|medium|low",
    "concept_abstraction": "concrete|mixed|abstract",
    "background_knowledge": "low|medium|high",
    "hinge_complexity": "simple|moderate|multilayer",
    "vocabulary_difficulty": "easy|medium|hard"
  },
  "reasons": ["한 줄 근거", "..."],
  "student_frame_ko": "관찰자 단계 · 시작하기 좋은 글 (친숙한 주제)"
}
PROMPT;
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $context title, excerpt, hinge, pro, con, lens, category, news_id
 */
function eduQuestDifficultyLlmBuildUserMessage(array $quest, array $context): string
{
    $code = (string) ($quest['quest_code'] ?? '');
    $title = (string) ($context['title'] ?? $quest['quest_title'] ?? '');
    $hinge = (string) ($context['hinge'] ?? '');
    $pro = (string) ($context['pro_line'] ?? $quest['pro_line'] ?? '');
    $con = (string) ($context['con_line'] ?? $quest['con_line'] ?? '');
    $excerpt = (string) ($context['excerpt'] ?? '');
    $lens = (string) ($context['lens_label'] ?? '');
    $category = (string) ($context['category_label'] ?? $context['category'] ?? '');
    $newsId = (int) ($context['news_id'] ?? 0);

    return <<<USER
quest_code: {$code}
news_id: {$newsId}
제목: {$title}
카테고리: {$category}
렌즈/각도: {$lens}

pro: {$pro}
con: {$con}
경첩(hinge): {$hinge}

본문 발췌(있으면):
{$excerpt}

위 글의 **중고등학생 난이도** L1~L5를 판정하세요. 품질이 아니라 접근 난이도입니다.
USER;
}

/**
 * @return array{ok: bool, rating?: array<string, mixed>, error?: string, raw?: string}
 */
function eduQuestDifficultyLlmClassify($llm, array $quest, array $context): array
{
    $userMessage = eduQuestDifficultyLlmBuildUserMessage($quest, $context);
    $response = $llm->chat(eduQuestDifficultyLlmSystemPrompt(), [
        ['role' => 'user', 'content' => $userMessage],
    ], 1024, 0.15);

    if (isset($response['error'])) {
        return [
            'ok' => false,
            'error' => (string) ($response['message'] ?? $response['error']),
        ];
    }

    $raw = (string) ($response['content'] ?? '');
    $parsed = eduParseLlmJson($response);
    if (!is_array($parsed)) {
        if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }

    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'LLM JSON parse failed', 'raw' => $raw];
    }

    $level = eduCoachLevelNormalize((int) ($parsed['difficulty_level'] ?? EDU_COACH_LEVEL_L3));
    $score = max(0, min(100, (int) ($parsed['difficulty_score'] ?? 50)));
    $reasons = $parsed['reasons'] ?? [];
    if (!is_array($reasons)) {
        $reasons = [(string) $reasons];
    }

    $rating = [
        'quest_code' => (string) ($quest['quest_code'] ?? ''),
        'difficulty_level' => $level,
        'difficulty_score' => $score,
        'label_ko' => eduQuestDifficultyLabel($level)['ko'],
        'label_en' => eduQuestDifficultyLabel($level)['en'],
        'signals' => is_array($parsed['signals'] ?? null) ? $parsed['signals'] : [],
        'reasons' => array_values(array_map('strval', $reasons)),
        'student_frame_ko' => trim((string) ($parsed['student_frame_ko'] ?? '')),
        'source' => 'llm',
        'classified_at' => date('c'),
        'prompt_version' => 'llm-v1',
    ];

    return ['ok' => true, 'rating' => $rating, 'raw' => $raw];
}

/** @return array<string, mixed>|null */
function eduQuestDifficultyLoadRatingCache(string $questCode): ?array
{
    $path = eduQuestDifficultyRatingPath($questCode);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

/** @param array<string, mixed> $rating */
function eduQuestDifficultySaveRatingCache(array $rating): string
{
    eduQuestDifficultyEnsureRatingsDir();
    $code = (string) ($rating['quest_code'] ?? 'unknown');
    $path = eduQuestDifficultyRatingPath($code);
    file_put_contents($path, json_encode($rating, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    return $path;
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $context
 * @return array{
 *   level: int,
 *   label_ko: string,
 *   label_en: string,
 *   difficulty_score: int,
 *   reasons: list<string>,
 *   signals: array<string, mixed>,
 *   student_frame_ko: string,
 *   source: string,
 *   quantile_adjusted: bool
 * }
 */
function eduQuestDifficultyResolveRating(
    $llm,
    array $quest,
    array $context,
    bool $useCache = true,
    bool $forceLlm = false
): array {
    $code = (string) ($quest['quest_code'] ?? '');

    if ($useCache && !$forceLlm) {
        $cached = eduQuestDifficultyLoadRatingCache($code);
        if ($cached !== null && isset($cached['difficulty_level'])) {
            $level = eduCoachLevelNormalize((int) $cached['difficulty_level']);
            $labels = eduQuestDifficultyLabel($level);

            return [
                'level' => $level,
                'label_ko' => $labels['ko'],
                'label_en' => $labels['en'],
                'difficulty_score' => (int) ($cached['difficulty_score'] ?? 50),
                'reasons' => is_array($cached['reasons'] ?? null) ? $cached['reasons'] : [],
                'signals' => is_array($cached['signals'] ?? null) ? $cached['signals'] : [],
                'student_frame_ko' => (string) ($cached['student_frame_ko'] ?? ''),
                'source' => (string) ($cached['source'] ?? 'cache'),
                'quantile_adjusted' => (bool) ($cached['quantile_adjusted'] ?? false),
            ];
        }
    }

    $result = eduQuestDifficultyLlmClassify($llm, $quest, $context);
    if (!$result['ok']) {
        throw new RuntimeException($result['error'] ?? 'LLM classify failed');
    }

    $rating = $result['rating'];
    eduQuestDifficultySaveRatingCache($rating);

    $level = eduCoachLevelNormalize((int) $rating['difficulty_level']);
    $labels = eduQuestDifficultyLabel($level);

    return [
        'level' => $level,
        'label_ko' => $labels['ko'],
        'label_en' => $labels['en'],
        'difficulty_score' => (int) $rating['difficulty_score'],
        'reasons' => $rating['reasons'],
        'signals' => $rating['signals'],
        'student_frame_ko' => $rating['student_frame_ko'],
        'source' => 'llm',
        'quantile_adjusted' => false,
    ];
}

/**
 * LLM difficulty_score 순으로 quantile 버킷 → L1~L5 균등 분포
 *
 * @param list<array<string, mixed>> $rows each has quest_code, difficulty_score, ...
 * @return array<string, int> quest_code => level
 */
function eduQuestDifficultyQuantileLevels(array $rows): array
{
    $sorted = $rows;
    usort($sorted, static function ($a, $b) {
        $sa = (int) ($a['difficulty_score'] ?? 50);
        $sb = (int) ($b['difficulty_score'] ?? 50);
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }

        return strcmp((string) ($a['quest_code'] ?? ''), (string) ($b['quest_code'] ?? ''));
    });

    $n = count($sorted);
    if ($n === 0) {
        return [];
    }

    $out = [];
    foreach ($sorted as $i => $row) {
        $code = (string) ($row['quest_code'] ?? '');
        if ($code === '') {
            continue;
        }
        // 0-index → level 1-5 in equal buckets
        $bucket = (int) floor($i * 5 / $n);
        $level = min(EDU_COACH_LEVEL_L5, max(EDU_COACH_LEVEL_L1, $bucket + 1));
        $out[$code] = $level;
    }

    return $out;
}

/**
 * @param array<int, int> $distribution
 * @return array{pass: bool, checks: list<string>}
 */
function eduQuestDifficultyDistributionGate(array $distribution, int $total): array
{
    $checks = [];
    $l1 = $distribution[1] ?? 0;
    $l3 = $distribution[3] ?? 0;
    $l3pct = $total > 0 ? round(100 * $l3 / $total, 1) : 0;

    $checks[] = $l1 >= 8
        ? "PASS L1≥8 ({$l1})"
        : "FAIL L1≥8 ({$l1})";
    $checks[] = $l3pct <= 35.0
        ? "PASS L3≤35% ({$l3pct}%)"
        : "FAIL L3≤35% ({$l3pct}%)";

    $pass = $l1 >= 8 && $l3pct <= 35.0;

    return ['pass' => $pass, 'checks' => $checks];
}

/**
 * 앵커 샘플 상식 검증 (힌트 — hard fail 아님)
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function eduQuestDifficultyAnchorChecks(array $rows): array
{
    $byCode = [];
    foreach ($rows as $row) {
        $byCode[(string) ($row['quest_code'] ?? '')] = $row;
    }

    $checks = [];

    $electric = $byCode['Q-AUTO-DC-150'] ?? null;
    if ($electric !== null) {
        $lv = (int) ($electric['difficulty_level'] ?? 0);
        $checks[] = ($lv >= 1 && $lv <= 2)
            ? "PASS 전기세(Q-AUTO-DC-150)=L{$lv} (L1-2)"
            : "WARN 전기세(Q-AUTO-DC-150)=L{$lv} (기대 L1-2)";
    }

    $youth = $byCode['Q-AUTO-YOUTH-288'] ?? null;
    if ($youth !== null) {
        $lv = (int) ($youth['difficulty_level'] ?? 0);
        $checks[] = ($lv >= 1 && $lv <= 2)
            ? "PASS 청소년AI(Q-AUTO-YOUTH-288)=L{$lv} (L1-2)"
            : "WARN 청소년AI(Q-AUTO-YOUTH-288)=L{$lv} (기대 L1-2)";
    }

    foreach ($rows as $row) {
        $title = (string) ($row['quest_title'] ?? '');
        $lv = (int) ($row['difficulty_level'] ?? 0);
        if (preg_match('/RSI|재귀적\s*자기개선/ui', $title) && $lv >= 4) {
            $checks[] = "PASS RSI 제목=L{$lv} (L4-5)";
        } elseif (preg_match('/RSI|재귀적\s*자기개선/ui', $title)) {
            $checks[] = "WARN RSI 제목=L{$lv} (기대 L4-5)";
        }
        if (preg_match('/양자/u', $title) && $lv >= 4) {
            $checks[] = 'PASS 양자 제목=L' . $lv . ' (L4-5)';
        } elseif (preg_match('/양자/u', $title)) {
            $checks[] = 'WARN 양자 제목=L' . $lv . ' (기대 L4-5)';
        }
        if (preg_match('/스티어스|핀테크|아프리카/u', $title) && $lv >= 4) {
            $checks[] = 'PASS 핀테크/아프리카=L' . $lv . ' (L4-5)';
        } elseif (preg_match('/스티어스|핀테크|아프리카/u', $title)) {
            $checks[] = 'WARN 핀테크/아프리카=L' . $lv . ' (기대 L4-5)';
        }
    }

    return array_values(array_unique($checks));
}
