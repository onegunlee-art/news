<?php
/**
 * EDU 퀘스트 난이도 (L1~L5) — 코치 레벨과 동일 명칭·체계
 *
 * ★ 판정: eduQuestDifficultyLlm.php (LLM, 품질≠난이도)
 * @deprecated eduQuestDeriveDifficultyLevel — filter 품질 점수, 변별 실패 (audit v1)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachLevel.php';
require_once __DIR__ . '/eduQuestFilter.php';
require_once __DIR__ . '/eduQuestCatalog.php';
require_once __DIR__ . '/eduHingeExtract.php';

/** @return array{ko: string, en: string} */
function eduQuestDifficultyLabel(int $level): array
{
    $labels = eduCoachLevelLabels(eduCoachLevelNormalize($level));

    return ['ko' => $labels['ko'], 'en' => $labels['en']];
}

/**
 * 퀘스트 행 + (선택) 경첩 extraction → 난이도 판정
 *
 * @param array<string, mixed> $quest edu_daily_quests row
 * @param array<string, mixed>|null $extraction hinge extraction or null
 * @param array<string, mixed> $meta article meta for filter (title, category, news_id, …)
 * @return array{
 *   level: int,
 *   label_ko: string,
 *   label_en: string,
 *   score: int,
 *   axis_count: int|null,
 *   reasons: list<string>,
 *   source: string
 * }
 */
function eduQuestDeriveDifficultyLevel(array $quest, ?array $extraction, array $meta = []): array
{
    if ($extraction === null) {
        $extraction = eduQuestDifficultyFallbackExtraction($quest);
        $source = 'quest_fallback';
    } else {
        $source = 'hinge';
    }

    $strength = eduQuestFilterStrengthScore($extraction, $meta);
    $score = (int) ($strength['score'] ?? 0);
    $axisCount = isset($strength['axis_count']) ? (int) $strength['axis_count'] : null;
    $reasons = $strength['breakdown'] ?? [];

    $weak = eduQuestFilterWeakTensionCheck(
        trim((string) ($extraction['hinge'] ?? '')),
        $extraction
    );
    $confidence = strtolower(trim((string) ($extraction['confidence'] ?? '')));

    $level = eduQuestDifficultyLevelFromScore($score, $axisCount, $confidence);

    if (($weak['weak'] ?? false) && $level > EDU_COACH_LEVEL_L2) {
        $level = EDU_COACH_LEVEL_L2;
        $reasons[] = '약한 경첩 긴장 → 상한 L2';
    }

    if ($axisCount !== null && $axisCount >= 2 && $level < EDU_COACH_LEVEL_L4) {
        $level = EDU_COACH_LEVEL_L4;
        $reasons[] = "축 {$axisCount}개 → 최소 L4";
    }

    if ($axisCount !== null && $axisCount >= 3 && $confidence === 'high' && $level < EDU_COACH_LEVEL_L5) {
        $level = EDU_COACH_LEVEL_L5;
        $reasons[] = '축 3+ & confidence high → L5';
    }

    $labels = eduQuestDifficultyLabel($level);

    return [
        'level' => $level,
        'label_ko' => $labels['ko'],
        'label_en' => $labels['en'],
        'score' => $score,
        'axis_count' => $axisCount,
        'reasons' => $reasons,
        'source' => $source,
    ];
}

function eduQuestDifficultyLevelFromScore(int $score, ?int $axisCount, string $confidence): int
{
    if ($score >= 85) {
        return EDU_COACH_LEVEL_L5;
    }
    if ($score >= 75) {
        return EDU_COACH_LEVEL_L4;
    }
    if ($score >= 65) {
        return EDU_COACH_LEVEL_L3;
    }
    if ($score >= 55) {
        return EDU_COACH_LEVEL_L2;
    }

    return EDU_COACH_LEVEL_L1;
}

/**
 * 경첩 캐시 없을 때 퀘스트 pro/con/conflict로 pseudo extraction
 *
 * @param array<string, mixed> $quest
 * @return array<string, mixed>
 */
function eduQuestDifficultyFallbackExtraction(array $quest): array
{
    $pro = trim((string) ($quest['pro_line'] ?? ''));
    $con = trim((string) ($quest['con_line'] ?? ''));
    $hinge = trim((string) ($quest['conflict_summary'] ?? ''));

    if ($hinge === '' && ($pro !== '' || $con !== '')) {
        $hinge = trim($pro . ' 그러나 ' . $con);
    }

    return [
        'hinge' => $hinge,
        'side_a' => $pro,
        'side_b' => $con,
        'confidence' => mb_strlen($hinge) >= 40 ? 'medium' : 'low',
        'shake_prompt' => '',
        'hook_student' => '',
        'news_id' => 0,
    ];
}

/**
 * @param array<string, mixed> $quest
 * @param int $newsId
 * @return array<string, mixed>
 */
function eduQuestDifficultyMetaForQuest(array $quest, int $newsId): array
{
    $meta = eduQuestListCategoryMeta($quest);
    $scores = eduQuestParseScores($quest);

    return [
        'news_id' => $newsId,
        'title' => (string) ($quest['quest_title'] ?? ''),
        'category' => (string) ($meta['category'] ?? ''),
        'topic_label' => (string) ($scores['lens_label'] ?? ($meta['lens_label'] ?? '')),
        'published_at' => $quest['live_at'] ?? null,
        'status' => (string) ($quest['status'] ?? ''),
        'content_preview' => mb_substr((string) ($quest['conflict_summary'] ?? ''), 0, 400),
    ];
}

/**
 * DB difficulty_level 컬럼 (nullable) → int|null
 */
function eduQuestReadDifficultyLevel(array $quest): ?int
{
    if (!array_key_exists('difficulty_level', $quest)) {
        return null;
    }
    $raw = $quest['difficulty_level'];
    if ($raw === null || $raw === '') {
        return null;
    }

    return eduCoachLevelNormalize((int) $raw);
}
