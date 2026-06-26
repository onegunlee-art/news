<?php
/**
 * UX waiting v2 + quote highlight static checks (frontend only)
 * php tools/edu_ux_waiting_highlight_static_verify.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function check(bool $ok, string $label): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

function read(string $rel): string
{
    global $root;
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string) file_get_contents($path) : '';
}

$coachText = read('src/frontend/src/components/edu/CoachMessageText.tsx');
$waitingPanel = read('src/frontend/src/components/edu/EduCoachWaitingPanel.tsx');
$fillOverlay = read('src/frontend/src/components/edu/StructureBarFillingOverlay.tsx');
$parse = read('src/frontend/src/utils/eduCoachMessageParse.ts');
$cards = read('src/frontend/src/pages/edu/QuestFlowCards.tsx');
$chat = read('src/frontend/src/pages/edu/QuestFlowChat.tsx');
$structure = read('src/frontend/src/components/edu/CardStructureBar.tsx');
$css = read('src/frontend/src/index.css');

check(str_contains($parse, 'parseCoachHighlightSegments'), 'parse: highlight segments');
check(str_contains($parse, 'QUOTE_MAX_LEN'), 'parse: quote length cap');
check(str_contains($parse, 'normalizeCoachQuotes'), 'parse: smart quote normalize');
check(str_contains($coachText, 'parseCoachHighlightSegments'), 'highlight: unified parser');
check(str_contains($coachText, 'primaryLight'), 'highlight: orange background');
check(str_contains($coachText, 'primaryDark'), 'highlight: orange text');
check(str_contains($parse, 'stripIncompleteCoachMarkers'), 'highlight: hide partial markers');

check(!str_contains($waitingPanel, 'TypingIndicator'), 'waiting: no TypingIndicator box');
check(str_contains($waitingPanel, '코치가 읽는 중'), 'waiting: default reading label');
check(str_contains($waitingPanel, 'studentAnswer'), 'waiting: student answer prop');
check(str_contains($fillOverlay, 'StructureBarFillingOverlay'), 'waiting: fill overlay component');
check(str_contains($css, 'edu-structure-slot-fill'), 'waiting: fill keyframes');

check(str_contains($cards, 'isWaiting'), 'cards: waiting state');
check(str_contains($cards, 'EduCoachWaitingPanel'), 'cards: waiting panel wired');
check(str_contains($cards, '코치가 읽는 중'), 'cards: reading label');
check(str_contains($cards, 'CoachMessageText'), 'cards: highlight render');

check(str_contains($chat, 'isWaiting'), 'chat: waiting state');
check(str_contains($chat, 'EduCoachWaitingPanel'), 'chat: waiting panel wired');
check(str_contains($chat, 'lastStudentAnswer'), 'chat: student answer wired');
check(str_contains($chat, 'CoachMessageText'), 'chat: highlight render');
check(str_contains($chat, '!isWaiting'), 'chat: hide pinned coach while waiting');
check(!str_contains($chat, 'TypingIndicator label={waitingLabel}'), 'chat: no TypingIndicator in waiting');

check(str_contains($structure, 'StructureBarFillingOverlay'), 'structure bar: fill overlay');
check(str_contains($structure, '채우는 중'), 'structure bar: filling label');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
