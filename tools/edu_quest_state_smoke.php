<?php
/**
 * Quest state payload smoke (start.php / state.php 경로)
 * Usage: php tools/edu_quest_state_smoke.php Q-GIST-566
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';

$code = $argv[1] ?? 'Q-GIST-566';
$sb = eduSupabase();

$quests = $sb->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
$quest = $quests[0] ?? null;
if ($quest === null) {
    fwrite(STDERR, "Quest not found: {$code}\n");
    exit(1);
}

$quest['articles'] = $sb->select(
    'edu_quest_articles',
    'quest_id=eq.' . ($quest['id'] ?? '') . '&order=sort_order.asc',
    20
) ?? [];

$session = [
    'id' => '00000000-0000-0000-0000-000000000001',
    'quest_id' => $quest['id'],
    'stage' => 'commit',
    'blueprint_json' => null,
    'hammer_payload' => null,
];

try {
    $blueprint = eduLoadBlueprint($session);
    $dialogue = eduLoadDialogue($session, true);
    $publicQuest = eduPublicQuestPayload(array_merge($quest, ['articles' => $quest['articles'] ?? []]));

    $payload = [
        'success' => true,
        'session_id' => $session['id'],
        'stage' => $session['stage'] ?? 'commit',
        'quest' => $publicQuest,
        'blueprint' => $blueprint,
        'dialogue' => $dialogue,
        'progress_pct' => eduQuestUsesAxisGuide($quest)
            ? max(eduCoachGuideProgress($blueprint), eduBlueprintProgress($blueprint))
            : eduBlueprintProgress($blueprint),
        'essay' => null,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fwrite(STDERR, 'JSON encode failed: ' . json_last_error_msg() . "\n");
        exit(1);
    }

    echo "OK {$code} json_bytes=" . strlen($json) . " progress=" . $payload['progress_pct'] . "\n";
    echo "entry_mode=" . ($publicQuest['entry_mode'] ?? '') . " frame=" . ($publicQuest['quest_frame'] ?? '') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n");
    exit(1);
}
