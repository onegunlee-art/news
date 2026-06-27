<?php
/**
 * GIST EDU — B-2 코치 레벨 게이지 XP (Phase 2 진단 × 현재 레벨 관문)
 *
 * XP = 현재 코치 레벨(L1~5) 진척 게이지. 질(관문) 달성 시 많이, 미달 시 적게.
 * 스트릭·코치 FSM과 무관. 레벨업 트리거는 B-3.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduCoachLevel.php';

/** 게이지 100% = 약 5회 질 높은 완주 */
const EDU_COACH_GAUGE_TARGET = 100;

const EDU_XP_COMPLETE_FLOOR = 5;
const EDU_XP_GATE_MISS_TOTAL = 8;
const EDU_XP_GATE_HIT_BASE = 20;
const EDU_XP_AXIS_BONUS_EACH = 2;
const EDU_XP_AXIS_BONUS_MAX = 6;
const EDU_XP_COMPLETE_CAP = 28;

/** @return array{ko: string, en: string}|null */
function eduCoachGaugeGateInfo(int $coachLevel): ?array
{
    $level = eduCoachLevelNormalize($coachLevel);
    if ($level >= EDU_COACH_LEVEL_L5) {
        return null;
    }

    return match ($level) {
        EDU_COACH_LEVEL_L1 => ['ko' => '양면 보기', 'en' => 'both sides'],
        EDU_COACH_LEVEL_L2 => ['ko' => '반론 듣기', 'en' => 'counter'],
        EDU_COACH_LEVEL_L3 => ['ko' => '근거 엮기', 'en' => 'evidence'],
        EDU_COACH_LEVEL_L4 => ['ko' => '메타 통찰', 'en' => 'meta'],
        default => ['ko' => '탐구 질', 'en' => 'quality'],
    };
}

/**
 * @param array<string, mixed> $diag
 * @param array<string, mixed> $blueprint
 */
function eduCoachGateSatisfied(array $diag, int $coachLevel, array $blueprint = []): bool
{
    $level = eduCoachLevelNormalize($coachLevel);
    if ($level >= EDU_COACH_LEVEL_L5) {
        return ((int) ($diag['exploration_depth_level'] ?? 0)) >= 5;
    }

    $tension = (string) ($diag['tension_engaged'] ?? '');
    $evidence = (string) ($diag['evidence_linked'] ?? '');
    $depth = (int) ($diag['exploration_depth_level'] ?? 0);
    $engaged = 0;
    foreach ($diag['axes_covered'] ?? [] as $axis) {
        if (is_array($axis) && !empty($axis['covered'])) {
            $engaged++;
        }
    }

    return match ($level) {
        EDU_COACH_LEVEL_L1 => $tension === '양면',
        EDU_COACH_LEVEL_L2 => !empty($blueprint['counter_handled'])
            || ($tension === '양면' && $engaged >= 1),
        EDU_COACH_LEVEL_L3 => $evidence === 'yes',
        EDU_COACH_LEVEL_L4 => $depth >= 4
            || ($evidence === 'yes' && $tension === '양면' && $engaged >= 2),
        default => false,
    };
}

/**
 * @param array<string, mixed> $diag eduStructureDiagnoseSession 출력
 * @param array<string, mixed> $blueprint
 * @return array{
 *   xp: int,
 *   gate_hit: bool,
 *   gate_label_ko: string|null,
 *   coach_level: int,
 *   breakdown: list<array{label: string, xp: int}>
 * }
 */
function eduXpAwardFromDiagnose(array $diag, int $coachLevel = EDU_COACH_LEVEL_L1, array $blueprint = []): array
{
    $level = eduCoachLevelNormalize($coachLevel);
    $gateInfo = eduCoachGaugeGateInfo($level);
    $gateHit = eduCoachGateSatisfied($diag, $level, $blueprint);
    $breakdown = [
        ['label' => '완주', 'xp' => EDU_XP_COMPLETE_FLOOR],
    ];
    $xp = EDU_XP_COMPLETE_FLOOR;

    if ($gateHit && $gateInfo !== null) {
        $gateBonus = EDU_XP_GATE_HIT_BASE - EDU_XP_COMPLETE_FLOOR;
        $xp += $gateBonus;
        $breakdown[] = ['label' => $gateInfo['ko'] . ' ✓', 'xp' => $gateBonus];

        $engaged = 0;
        foreach ($diag['axes_covered'] ?? [] as $axis) {
            if (is_array($axis) && !empty($axis['covered'])) {
                $engaged++;
            }
        }
        $axisBonus = min(EDU_XP_AXIS_BONUS_MAX, $engaged * EDU_XP_AXIS_BONUS_EACH);
        if ($axisBonus > 0) {
            $xp += $axisBonus;
            $breakdown[] = ['label' => '축 탐구', 'xp' => $axisBonus];
        }
    } else {
        $missBonus = max(0, EDU_XP_GATE_MISS_TOTAL - EDU_XP_COMPLETE_FLOOR);
        if ($missBonus > 0) {
            $xp += $missBonus;
            $label = $gateInfo !== null ? '관문 미달 (천천히)' : '탐구 기록';
            $breakdown[] = ['label' => $label, 'xp' => $missBonus];
        }
    }

    $xp = min(EDU_XP_COMPLETE_CAP, max(EDU_XP_COMPLETE_FLOOR, $xp));

    return [
        'xp' => $xp,
        'gate_hit' => $gateHit,
        'gate_label_ko' => $gateInfo['ko'] ?? null,
        'coach_level' => $level,
        'breakdown' => $breakdown,
    ];
}

/**
 * @param array<string, mixed> $diag
 * @param array<string, mixed> $blueprint
 */
function eduXpFromStructureDiagnose(array $diag, int $coachLevel = EDU_COACH_LEVEL_L1, array $blueprint = []): int
{
    return eduXpAwardFromDiagnose($diag, $coachLevel, $blueprint)['xp'];
}
