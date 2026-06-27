<?php
/**
 * GIST EDU — tier / XP helpers
 *
 * B-2: coach_gauge_xp = 현재 코치 레벨(L1~5) 진척 게이지. 7단 메달 tier_id는 레거시(동결).
 * 스트릭은 eduStreakOnCompletion — XP와 분리.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/eduGamification.php';
require_once __DIR__ . '/eduCoachLevel.php';

const EDU_TIER_THRESHOLDS = [
    'observer' => 0,
    'iron' => 300,
    'bronze' => 600,
    'silver' => 900,
    'gold' => 1200,
    'platinum' => 1800,
    'gist_challenger' => 2500,
];

const EDU_TIER_ORDER = [
    'observer', 'iron', 'bronze', 'silver', 'gold', 'platinum', 'gist_challenger',
];

const EDU_TIER_LABELS_KO = [
    'observer' => '관찰자',
    'iron' => '아이언',
    'bronze' => '브론즈',
    'silver' => '실버',
    'gold' => '골드 사상가',
    'platinum' => '플래티넘',
    'gist_challenger' => 'GIST 챌린저',
];

function eduTierLabelEn(string $tierId): string
{
    if ($tierId === 'gist_challenger') {
        return 'GIST Challenger';
    }
    if ($tierId === 'iron') {
        return 'Iron';
    }
    return ucfirst($tierId);
}

function eduTierFromXp(int $xp): string
{
    $current = 'observer';
    foreach (EDU_TIER_THRESHOLDS as $tier => $min) {
        if ($xp >= $min) {
            $current = $tier;
        }
    }
    return $current;
}

function eduNextTier(string $tierId): ?string
{
    $idx = array_search($tierId, EDU_TIER_ORDER, true);
    if ($idx === false || $idx >= count(EDU_TIER_ORDER) - 1) {
        return null;
    }
    return EDU_TIER_ORDER[$idx + 1];
}

function eduFetchTierRow(string $studentId): array
{
    $supabase = eduSupabase();
    $rows = $supabase->select('edu_user_tier', 'student_id=eq.' . $studentId, 1);
    if (!empty($rows[0])) {
        return $rows[0];
    }

    $inserted = $supabase->insert('edu_user_tier', [
        'student_id' => $studentId,
        'tier_id' => 'observer',
        'xp_current' => 0,
        'coach_gauge_xp' => 0,
        'streak_days' => 0,
        'streak_freeze_available' => 1,
    ]);
    return $inserted[0] ?? [
        'student_id' => $studentId,
        'tier_id' => 'observer',
        'xp_current' => 0,
        'coach_gauge_xp' => 0,
        'streak_days' => 0,
        'streak_freeze_available' => 1,
        'status' => 'active',
    ];
}

/** @param array<string, mixed> $tierRow */
function eduCoachGaugeXpFromRow(array $tierRow): int
{
    return max(0, (int) ($tierRow['coach_gauge_xp'] ?? 0));
}

/**
 * B-2 코치 레벨 게이지 — API·UI 공용
 *
 * @param array<string, mixed> $tierRow
 * @return array<string, mixed>
 */
function eduCoachGaugeProgressPayload(int $coachLevel, array $tierRow): array
{
    $level = eduCoachLevelNormalize($coachLevel);
    $gaugeXp = eduCoachGaugeXpFromRow($tierRow);
    $target = EDU_COACH_GAUGE_TARGET;
    $progressPct = $level >= EDU_COACH_LEVEL_L5
        ? 100
        : (int) round(min(100, max(0, ($gaugeXp / max(1, $target)) * 100)));
    $gaugeFull = $level < EDU_COACH_LEVEL_L5 && $gaugeXp >= $target;
    $nextLevel = $level < EDU_COACH_LEVEL_L5 ? $level + 1 : null;
    $nextLabels = $nextLevel !== null ? eduCoachLevelLabels($nextLevel) : null;
    $gateInfo = eduCoachGaugeGateInfo($level);

    return [
        'coach_gauge_xp' => $gaugeXp,
        'coach_gauge_target' => $target,
        'coach_gauge_progress_pct' => $progressPct,
        'coach_gauge_full' => $gaugeFull,
        'coach_gauge_gate_ko' => $gateInfo['ko'] ?? null,
        'next_coach_level' => $nextLevel,
        'next_coach_label_ko' => $nextLabels['ko'] ?? null,
        'next_coach_label_en' => $nextLabels['en'] ?? null,
    ];
}

/** @param array<string, mixed> $tierRow */
function eduTierProgressPayload(array $tierRow, int $coachLevel = EDU_COACH_LEVEL_L1): array
{
    $tierId = $tierRow['tier_id'] ?? 'observer';
    $legacyXp = (int) ($tierRow['xp_current'] ?? 0);
    $gauge = eduCoachGaugeProgressPayload($coachLevel, $tierRow);
    $level = eduCoachLevelNormalize($coachLevel);

    $gaugeXp = $gauge['coach_gauge_xp'];
    $gaugeTarget = $gauge['coach_gauge_target'];
    $progressPct = (int) $gauge['coach_gauge_progress_pct'];

    return array_merge([
        'tier_id' => $tierId,
        'tier_label_en' => eduTierLabelEn($tierId),
        'tier_label_ko' => EDU_TIER_LABELS_KO[$tierId] ?? '',
        'status' => $tierRow['status'] ?? 'active',
        'next_tier_id' => null,
        'next_tier_label_en' => null,
        'xp_current' => $gaugeXp,
        'xp_next_tier' => $level < EDU_COACH_LEVEL_L5 ? $gaugeTarget : null,
        'progress_pct' => $progressPct,
        'streak_days' => (int) ($tierRow['streak_days'] ?? 0),
        'streak_freeze_available' => (int) ($tierRow['streak_freeze_available'] ?? 1),
        'show_quest_cta' => ($tierRow['status'] ?? 'active') === 'active',
        'legacy_xp_total' => $legacyXp,
    ], $gauge);
}

function eduAwardXp(\Agents\Services\SupabaseService $supabase, string $studentId, int $delta, string $eventType, ?string $sessionId = null, array $meta = []): array
{
    $tierRow = eduFetchTierRow($studentId);
    $coachGaugeEvent = in_array($eventType, ['structure_quest', 'coach_gauge'], true);

    if ($coachGaugeEvent) {
        $gaugeXp = eduCoachGaugeXpFromRow($tierRow);
        $newGauge = min(EDU_COACH_GAUGE_TARGET, $gaugeXp + max(0, $delta));
        $supabase->update('edu_user_tier', 'student_id=eq.' . $studentId, [
            'coach_gauge_xp' => $newGauge,
            'status' => 'active',
            'updated_at' => date('c'),
        ]);
    } else {
        $oldTier = $tierRow['tier_id'] ?? 'observer';
        $newXp = max(0, (int) ($tierRow['xp_current'] ?? 0) + $delta);
        $newTier = eduTierFromXp($newXp);
        $tierOrder = array_flip(EDU_TIER_ORDER);
        if (($tierOrder[$newTier] ?? 0) < ($tierOrder[$oldTier] ?? 0)) {
            $newTier = $oldTier;
        }
        $supabase->update('edu_user_tier', 'student_id=eq.' . $studentId, [
            'xp_current' => $newXp,
            'tier_id' => $newTier,
            'status' => 'active',
            'updated_at' => date('c'),
        ]);
    }

    $supabase->insert('edu_xp_events', [
        'student_id' => $studentId,
        'session_id' => $sessionId,
        'event_type' => $eventType,
        'xp_delta' => $delta,
        'meta' => $meta,
    ]);

    return eduFetchTierRow($studentId);
}

/**
 * 완주(세션 종료) 시 스트릭 — XP와 분리. 하루 1회 +1, freeze 1회 후 리셋.
 */
function eduStreakOnCompletion(\Agents\Services\SupabaseService $supabase, string $studentId): array
{
    $tierRow = eduFetchTierRow($studentId);
    $today = date('Y-m-d');
    $lastDate = $tierRow['last_quest_date'] ?? null;
    $streak = (int) ($tierRow['streak_days'] ?? 0);
    $freeze = (int) ($tierRow['streak_freeze_available'] ?? 1);

    if ($lastDate === $today) {
        return $tierRow;
    }

    if ($lastDate === null) {
        $streak = 1;
    } elseif ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
        $streak++;
    } else {
        $gapDays = (int) floor((strtotime($today) - strtotime((string) $lastDate)) / 86400);
        if ($gapDays >= 2 && $freeze > 0) {
            $freeze--;
            $streak++;
        } else {
            $streak = 1;
        }
    }

    $supabase->update('edu_user_tier', 'student_id=eq.' . $studentId, [
        'streak_days' => $streak,
        'streak_freeze_available' => $freeze,
        'last_quest_date' => $today,
        'updated_at' => date('c'),
    ]);

    return eduFetchTierRow($studentId);
}

function eduExtractHeroSentence(array $v2Sentences): ?string
{
    foreach ($v2Sentences as $line) {
        $line = trim((string) $line);
        if ($line !== '' && mb_strlen($line) >= 12) {
            return $line;
        }
    }
    return null;
}
