<?php
/**
 * eduQuest session stage helpers (resumable / abandoned)
 *
 * Usage: php tools/edu_session_stage_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

ok('filter uses stage=in', str_contains(eduSessionStageFilterResumable(), 'stage=in.'));
ok('completed filter requires completed_at', str_contains(eduSessionStageFilterCompleted(), 'completed_at=not.is.null'));
ok('reasoning resumable', eduIsSessionResumable(['stage' => 'reasoning']));
ok('completed not resumable', !eduIsSessionResumable(['stage' => 'completed']));
ok('abandoned stage not resumable', !eduIsSessionResumable(['stage' => 'abandoned']));
ok('blueprint abandoned_at not resumable', !eduIsSessionResumable([
    'stage' => 'completed',
    'blueprint_json' => ['abandoned_at' => '2026-06-18T00:00:00+00:00'],
]));
ok('is abandoned via blueprint', eduIsSessionAbandoned([
    'stage' => 'completed',
    'blueprint_json' => ['abandoned_at' => '2026-06-18T00:00:00+00:00'],
]));

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
