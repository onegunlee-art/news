<?php
/**
 * GIST EDU — QuestConfig read layer (derived from hammer_hints, read-only)
 *
 * P1-1: parallel read layer. P1-2h: eduIsMythBustQuest delegates to entry_mode here.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';

/**
 * @param array<string, mixed> $quest
 * @return array{
 *   entry_mode: 'stance_pick'|'open_response',
 *   coach_profile: 'decision'|'open'|'default',
 *   hammer_mode: string,
 *   quest_frame: string,
 *   hook_full: string,
 *   hook_short: string,
 *   time_anchor: string,
 *   shared_conclusion: string,
 *   axes: list<mixed>,
 *   counter_map: array<string, mixed>
 * }
 */
function eduResolveQuestConfig(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $frame = trim((string) ($hints['quest_frame'] ?? ''));

    if ($frame === 'myth_bust') {
        $entryMode = 'open_response';
        $coachProfile = 'open';
    } elseif ($frame === 'decision_inquiry') {
        $entryMode = 'stance_pick';
        $coachProfile = 'decision';
    } else {
        $entryMode = 'stance_pick';
        $coachProfile = 'default';
    }

    $hammerMode = trim((string) ($hints['mode'] ?? ''));
    if ($hammerMode === '') {
        $hammerMode = 'adversarial';
    }

    return [
        'entry_mode' => $entryMode,
        'coach_profile' => $coachProfile,
        'hammer_mode' => $hammerMode,
        'quest_frame' => $frame,
        'hook_full' => (string) ($hints['hook_full'] ?? ''),
        'hook_short' => (string) ($hints['hook_short'] ?? ''),
        'time_anchor' => (string) ($hints['time_anchor'] ?? ''),
        'shared_conclusion' => (string) ($hints['shared_conclusion'] ?? ''),
        'axes' => is_array($hints['axes'] ?? null) ? $hints['axes'] : [],
        'counter_map' => is_array($hints['counter_map'] ?? null) ? $hints['counter_map'] : [],
    ];
}

/** @param array<string, mixed> $quest */
function eduQuestEntryMode(array $quest): string
{
    return eduResolveQuestConfig($quest)['entry_mode'];
}

/** @param array<string, mixed> $quest */
function eduQuestCoachProfile(array $quest): string
{
    return eduResolveQuestConfig($quest)['coach_profile'];
}
