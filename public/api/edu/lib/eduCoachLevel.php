<?php
/**
 * EDU coach level — 5단 (L1~L5). L5 = v1 axis_guide (구 level 7). L1 = 초등 (구 level 1).
 *
 * Legacy: 7→L5, 4→L3 (frozen blueprint·student 값 흡수).
 */
declare(strict_types=1);

require_once __DIR__ . '/eduBlueprint.php';

const EDU_COACH_LEVEL_MIN = 1;
const EDU_COACH_LEVEL_MAX = 5;

const EDU_COACH_LEVEL_L1 = 1;
const EDU_COACH_LEVEL_L2 = 2;
const EDU_COACH_LEVEL_L3 = 3;
const EDU_COACH_LEVEL_L4 = 4;
const EDU_COACH_LEVEL_L5 = 5;

/** @deprecated alias — L1 */
const EDU_COACH_LEVEL_ELEMENTARY = EDU_COACH_LEVEL_L1;
/** @deprecated alias — L5 (구 level 7) */
const EDU_COACH_LEVEL_ADVANCED = EDU_COACH_LEVEL_L5;
const EDU_COACH_LEVEL_DEFAULT = EDU_COACH_LEVEL_L1;

/** 구 7단 번호 → 5단 (frozen blueprint·student). ★ 4→3은 하지 않음 — 새 L4=4와 충돌, 구 coach_level 4는 라이브에 없었음 */
function eduCoachLevelFromLegacy(int $level): int
{
    return match ($level) {
        7 => EDU_COACH_LEVEL_L5,
        default => $level,
    };
}

function eduCoachLevelNormalize(int $level): int
{
    $level = eduCoachLevelFromLegacy($level);

    return max(EDU_COACH_LEVEL_MIN, min(EDU_COACH_LEVEL_MAX, $level));
}

/** 코치 FSM 경로 — l3·l5는 v1 axis_guide (동일 엔진, L4만 upper axes) */
function eduCoachLevelCoachPath(int $level): string
{
    return match (eduCoachLevelNormalize($level)) {
        EDU_COACH_LEVEL_L1 => 'l1',
        EDU_COACH_LEVEL_L2 => 'l2',
        EDU_COACH_LEVEL_L3 => 'l3',
        EDU_COACH_LEVEL_L4 => 'l4',
        EDU_COACH_LEVEL_L5 => 'l5',
        default => 'l5',
    };
}

/** @deprecated v1 분기용 — L5 이상(legacy 7 포함) */
function eduCoachLevelIsAdvanced(int $level): bool
{
    return eduCoachLevelNormalize($level) >= EDU_COACH_LEVEL_L5;
}

function eduCoachLevelIsElementary(int $level): bool
{
    return eduCoachLevelNormalize($level) === EDU_COACH_LEVEL_L1;
}

/** progress·choiceMeta용 축 개수 */
function eduCoachLevelAxisDivisor(int $level): int
{
    return match (eduCoachLevelCoachPath($level)) {
        'l1' => 2,
        'l2' => 3,
        default => 3,
    };
}

/**
 * @param array<string, mixed> $student
 * @param array<string, mixed> $blueprint
 */
function eduResolveCoachLevel(array $student, array $blueprint): int
{
    if (isset($blueprint['coach_level'])) {
        return eduCoachLevelNormalize((int) $blueprint['coach_level']);
    }
    if (isset($student['coach_level'])) {
        return eduCoachLevelNormalize((int) $student['coach_level']);
    }

    if (!function_exists('eduCoachGuideElementaryReady') || !eduCoachGuideElementaryReady()) {
        return EDU_COACH_LEVEL_L5;
    }

    return eduCoachLevelNormalize(EDU_COACH_LEVEL_DEFAULT);
}

/**
 * @param array<string, mixed> $blueprint
 * @return array<string, mixed>
 */
function eduBlueprintFreezeCoachLevel(array $blueprint, int $coachLevel): array
{
    if (isset($blueprint['coach_level'])) {
        return $blueprint;
    }

    return eduMergeBlueprint($blueprint, ['coach_level' => eduCoachLevelNormalize($coachLevel)]);
}
