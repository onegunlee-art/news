<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';

$q = eduLoadTodayQuest(null);
if ($q === null) {
    echo "No live quest\n";
    exit(1);
}
echo 'quest_code: ' . ($q['quest_code'] ?? '') . "\n";
echo 'quest_title: ' . ($q['quest_title'] ?? '') . "\n";
$hints = is_string($q['hammer_hints'] ?? null) ? json_decode($q['hammer_hints'], true) : ($q['hammer_hints'] ?? []);
echo 'frame: ' . ($hints['quest_frame'] ?? '') . "\n";
echo 'time_anchor: ' . ($hints['time_anchor'] ?? '') . "\n";
echo 'articles: ' . count($q['articles'] ?? []) . "\n";

$public = eduPublicQuestPayload($q);
echo "\n=== eduPublicQuestPayload ===\n";
echo 'time_anchor: ' . ($public['time_anchor'] ?? 'null') . "\n";
echo 'quest_frame: ' . ($public['quest_frame'] ?? 'null') . "\n";
echo 'entry_mode payload: ' . ($public['entry_mode'] ?? 'null') . "\n";
echo 'entry_mode derive: ' . eduQuestEntryMode($q) . "\n";

$hammer = eduHammerPayload($q, 'pro');
echo "\n=== eduHammerPayload (pro) ===\n";
echo 'mode: ' . ($hammer['mode'] ?? '') . "\n";
echo 'reflection_question: ' . ($hammer['reflection_question'] ?? '') . "\n";

$isDecision = ($hints['quest_frame'] ?? '') === 'decision_inquiry';
if ($isDecision && str_contains($hammer['reflection_question'] ?? '', '동의해')) {
    echo "FAIL: decision_inquiry reflection still uses old frame\n";
    exit(1);
}
if ($isDecision && ($public['time_anchor'] ?? '') === '') {
    echo "FAIL: time_anchor not exposed\n";
    exit(1);
}
$entryMode = eduQuestEntryMode($q);
if (($public['entry_mode'] ?? '') !== $entryMode) {
    echo "FAIL: payload entry_mode mismatch\n";
    exit(1);
}
if (!in_array($entryMode, ['stance_pick', 'open_response'], true)) {
    echo "FAIL: invalid entry_mode {$entryMode}\n";
    exit(1);
}
echo "\nOK\n";
