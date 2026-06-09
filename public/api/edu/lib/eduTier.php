<?php
/**
 * GIST EDU — tier / XP helpers
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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
        'streak_days' => 0,
    ]);
    return $inserted[0] ?? [
        'student_id' => $studentId,
        'tier_id' => 'observer',
        'xp_current' => 0,
        'streak_days' => 0,
        'status' => 'active',
    ];
}

function eduTierProgressPayload(array $tierRow): array
{
    $tierId = $tierRow['tier_id'] ?? 'observer';
    $xp = (int) ($tierRow['xp_current'] ?? 0);
    $next = eduNextTier($tierId);
    $nextXp = $next !== null ? EDU_TIER_THRESHOLDS[$next] : null;

    $progressPct = 100;
    if ($next !== null && $nextXp !== null) {
        $floor = EDU_TIER_THRESHOLDS[$tierId];
        $span = max(1, $nextXp - $floor);
        $progressPct = (int) round(min(100, max(0, (($xp - $floor) / $span) * 100)));
    }

    return [
        'tier_id' => $tierId,
        'tier_label_en' => eduTierLabelEn($tierId),
        'tier_label_ko' => EDU_TIER_LABELS_KO[$tierId] ?? '',
        'status' => $tierRow['status'] ?? 'active',
        'next_tier_id' => $next,
        'next_tier_label_en' => $next !== null ? eduTierLabelEn($next) : null,
        'xp_current' => $xp,
        'xp_next_tier' => $nextXp,
        'progress_pct' => $progressPct,
        'streak_days' => (int) ($tierRow['streak_days'] ?? 0),
        'show_quest_cta' => ($tierRow['status'] ?? 'active') === 'active',
    ];
}

function eduAwardXp(\Agents\Services\SupabaseService $supabase, string $studentId, int $delta, string $eventType, ?string $sessionId = null, array $meta = []): array
{
    $tierRow = eduFetchTierRow($studentId);
    $oldTier = $tierRow['tier_id'] ?? 'observer';
    $newXp = max(0, (int) ($tierRow['xp_current'] ?? 0) + $delta);
    $newTier = eduTierFromXp($newXp);
    $tierOrder = array_flip(EDU_TIER_ORDER);
    if (($tierOrder[$newTier] ?? 0) < ($tierOrder[$oldTier] ?? 0)) {
        $newTier = $oldTier;
    }

    $today = date('Y-m-d');
    $lastDate = $tierRow['last_quest_date'] ?? null;
    $streak = (int) ($tierRow['streak_days'] ?? 0);
    if ($lastDate === null) {
        $streak = 1;
    } elseif ($lastDate === $today) {
        // keep
    } elseif ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
        $streak++;
    } else {
        $streak = 1;
    }

    $supabase->update('edu_user_tier', 'student_id=eq.' . $studentId, [
        'xp_current' => $newXp,
        'tier_id' => $newTier,
        'streak_days' => $streak,
        'last_quest_date' => $today,
        'status' => 'active',
        'updated_at' => date('c'),
    ]);

    $supabase->insert('edu_xp_events', [
        'student_id' => $studentId,
        'session_id' => $sessionId,
        'event_type' => $eventType,
        'xp_delta' => $delta,
        'meta' => $meta,
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
