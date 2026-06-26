<?php
/**
 * EDU coach level — level 7 = v1 axis_guide (고등, 보존). level 1 = 초등 (별도 인도).
 */
declare(strict_types=1);

require_once __DIR__ . '/eduBlueprint.php';

const EDU_COACH_LEVEL_ELEMENTARY = 1;
const EDU_COACH_LEVEL_ADVANCED = 7;
/** 세션·학생 기본값 — level 1 구현 완료 후 활성 (그 전엔 resolve가 7로 폴백) */
const EDU_COACH_LEVEL_DEFAULT = 1;

function eduCoachLevelNormalize(int $level): int
{
    return max(EDU_COACH_LEVEL_ELEMENTARY, min(EDU_COACH_LEVEL_ADVANCED, $level));
}

function eduCoachLevelIsAdvanced(int $level): bool
{
    return eduCoachLevelNormalize($level) >= EDU_COACH_LEVEL_ADVANCED;
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

    // level 1 인도 구현 전: v1 동작 유지 (고등 보존). 구현 후 이 분기 제거.
    if (!function_exists('eduCoachGuideElementaryReady') || !eduCoachGuideElementaryReady()) {
        return EDU_COACH_LEVEL_ADVANCED;
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
