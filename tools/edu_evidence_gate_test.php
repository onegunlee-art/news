<?php
/**
 * GIST EDU — evidence phase gate (ConversationDirector, single-turn)
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

// Too short → nudge (only retry path)
$bp = ['phase' => 'evidence', 'evidence' => '짧아요', 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 4, 'has_evidence' => true];
assertGate('short_len', $director->decide($bp, $quest, $eval), 'nudge_evidence');

// 15+ chars + has_evidence → hammer (single turn, no second nudge)
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 4, 'has_evidence' => true];
assertGate('has_evidence_once', $director->decide($bp, $quest, $eval), 'strike');

// 15+ chars, LLM flaky has_evidence but depth ≥ 2 → hammer
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 2, 'has_evidence' => false];
assertGate('depth2_no_has_evidence', $director->decide($bp, $quest, $eval), 'strike');

// 15+ chars but depth 1 and no evidence → one nudge only (too thin)
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 25), 'evidence_nudge_count' => 0];
$eval = ['depth_score' => 1, 'has_evidence' => false];
assertGate('thin_once', $director->decide($bp, $quest, $eval), 'nudge_evidence');

// After nudge, substantive retry → hammer (no third-turn loop)
$bp = ['phase' => 'evidence', 'evidence' => str_repeat('가', 20), 'evidence_nudge_count' => 1];
$eval = ['depth_score' => 2, 'has_evidence' => false];
assertGate('retry_substantive', $director->decide($bp, $quest, $eval), 'strike');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
