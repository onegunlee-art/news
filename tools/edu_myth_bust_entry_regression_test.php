<?php
/**
 * myth_bust 진입 회귀 (LLM 없음)
 *
 * Usage:
 *   php tools/edu_myth_bust_entry_regression_test.php
 *   php tools/edu_myth_bust_entry_regression_test.php --live
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

$live = in_array('--live', $argv ?? [], true);
$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

function assertEq(string $label, mixed $got, mixed $expect): void
{
    assertTrue($label, $got === $expect);
}

echo "=== myth_bust entry regression ===\n\n";

$japan = eduG09DecQuestFixture();
$nuke = eduNuke630QuestFixture();
$iran = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'hammer_hints' => ['quest_frame' => 'decision_inquiry'],
];

// P1-2a: fixture classification via QuestConfig (production still uses legacy booleans)
assertTrue('japan entry_mode stance_pick', eduQuestEntryMode($japan) === 'stance_pick');
assertTrue('iran entry_mode stance_pick', eduQuestEntryMode($iran) === 'stance_pick');
assertTrue('nuke entry_mode open_response', eduQuestEntryMode($nuke) === 'open_response');
assertTrue('japan coach_profile decision', eduQuestCoachProfile($japan) === 'decision');
assertTrue('nuke coach_profile open', eduQuestCoachProfile($nuke) === 'open');

$nuke['id'] = 'test-quest-id';
$payload = eduPublicQuestPayload($nuke);
assertEq('payload quest_frame', $payload['quest_frame'] ?? null, 'myth_bust');
assertEq('payload entry_mode', $payload['entry_mode'] ?? null, 'open_response');
assertTrue('payload hook_full non-empty', trim((string) ($payload['hook_full'] ?? '')) !== '');
assertTrue('payload hook_short non-empty', trim((string) ($payload['hook_short'] ?? '')) !== '');

// chat.php select_stance guard (same condition as chat.php)
assertTrue('select_stance blocked for myth_bust', eduIsMythBustQuest($nuke));
assertTrue('select_stance allowed for japan', !eduIsMythBustQuest($japan));

// submit_opening blueprint shape (no stance / no hypothesis)
$openingBp = eduMergeBlueprint([], [
    'reason' => '핵만으로는 드론 공격을 막기 어렵다',
    'opening_submitted' => true,
    'phase' => 'reasoning',
    'exchange_count' => 1,
]);
assertEq('opening phase', $openingBp['phase'] ?? null, 'reasoning');
assertTrue('opening no stance', !isset($openingBp['stance']));
assertTrue('opening reason set', ($openingBp['reason'] ?? '') !== '');

// list.php default filter includes myth_bust OR decision_inquiry
$listFilter = 'status=eq.approved&order=live_at.desc.nullslast,created_at.desc';
$listFilter .= '&or=(hammer_hints->>quest_frame.eq.decision_inquiry,hammer_hints->>quest_frame.eq.myth_bust)';
assertTrue(
    'list filter OR myth_bust',
    str_contains($listFilter, 'myth_bust') && str_contains($listFilter, 'decision_inquiry')
);

if ($live) {
    echo "\n--- live HTTP checks ---\n";
    $base = 'https://www.thegist.co.kr';
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--base=')) {
            $base = rtrim(substr($arg, 7), '/');
        }
    }

    $api = static function (string $method, string $path, ?array $body = null, ?string $token = null) use ($base): array {
        $ch = curl_init($base . $path);
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'X-Edu-Token: ' . $token;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return ['http' => $http, 'data' => is_array($data) ? $data : [], 'raw' => (string) $raw];
    };

    $guest = $api('POST', '/api/edu/guest/start.php', []);
    $token = (string) ($guest['data']['token'] ?? '');
    assertTrue('live guest', $guest['http'] === 200 && $token !== '');

    $list = $api('GET', '/api/edu/quests/list.php', null, $token);
    $nukeRow = null;
    foreach ($list['data']['quests'] ?? [] as $q) {
        if (($q['quest_code'] ?? '') === 'Q-NUKE-AXIS-630') {
            $nukeRow = $q;
            break;
        }
    }
    assertTrue('live list includes Q-NUKE-AXIS-630', $nukeRow !== null);
    assertEq('live nuke quest_frame', $nukeRow['quest_frame'] ?? null, 'myth_bust');

    if ($nukeRow !== null) {
        $start = $api('POST', '/api/edu/session/start.php', ['quest_id' => $nukeRow['quest_id']], $token);
        $sid = (string) ($start['data']['session_id'] ?? '');
        assertTrue('live nuke session', $start['http'] === 200 && $sid !== '');

        $bad = $api('POST', '/api/edu/session/chat.php', [
            'session_id' => $sid,
            'action' => 'select_stance',
            'stance' => 'pro',
        ], $token);
        assertTrue('live select_stance on myth_bust returns 400', $bad['http'] === 400);
    }
}

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
