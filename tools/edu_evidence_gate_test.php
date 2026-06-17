<?php
/**
 * GIST EDU — evidence phase gate 회귀 (ConversationDirector)
 *
 * Usage: php tools/edu_evidence_gate_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
eduLoadAgents();

use Services\Edu\Agents\ConversationDirector;

class FakeLlm
{
    public function haiku(string $system, array $messages, int $maxTokens = 256): array
    {
        return ['content' => '{}'];
    }
}

$director = new ConversationDirector(new FakeLlm());
$quest = ['quest_title' => 'test'];

$pass = 0;
$fail = 0;

function assertGate(string $label, array $decision, string $expectAction): void
{
    global $pass, $fail;
    $action = $decision['action'] ?? '';
    if ($action === $expectAction) {
        echo "PASS {$label} action={$action}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label} expected={$expectAction} got={$action}\n";
    $fail++;
}

// Short evidence → nudge
$bp = ['phase' => 'evidence', 'evidence' => '짧아요', 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 4, 'has_evidence' => true];
assertGate('short_len', $director->decide($bp, $quest, $eval), 'nudge_evidence');

// No has_evidence → nudge
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 4, 'has_evidence' => false];
assertGate('no_has_evidence', $director->decide($bp, $quest, $eval), 'nudge_evidence');

// Low depth → nudge
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 2, 'has_evidence' => true];
assertGate('low_depth', $director->decide($bp, $quest, $eval), 'nudge_evidence');

// All criteria met → hammer
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 4, 'has_evidence' => true];
assertGate('ready', $director->decide($bp, $quest, $eval), 'strike');

// Still weak after nudge → stay nudge (no hammer skip)
$bp = ['phase' => 'evidence', 'evidence' => '짧아요', 'evidence_nudge_count' => 1];
$eval = ['depth_score' => 2, 'has_evidence' => false];
assertGate('still_weak_after_nudge', $director->decide($bp, $quest, $eval), 'nudge_evidence');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
