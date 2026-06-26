<?php
/**
 * UX waiting + bold highlight static checks (frontend only)
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
$typing = read('src/frontend/src/components/edu/TypingIndicator.tsx');
$parse = read('src/frontend/src/utils/eduCoachMessageParse.ts');
$cards = read('src/frontend/src/pages/edu/QuestFlowCards.tsx');
$chat = read('src/frontend/src/pages/edu/QuestFlowChat.tsx');
$structure = read('src/frontend/src/components/edu/CardStructureBar.tsx');

check(str_contains($parse, 'parseCoachBoldSegments'), 'parse: bold segments');
check(str_contains($coachText, 'primaryLight'), 'highlight: orange background');
check(str_contains($coachText, 'primaryDark'), 'highlight: orange text');
check(str_contains($coachText, 'stripIncompleteCoachBold'), 'highlight: hide partial **');

check(str_contains($typing, '생각 중'), 'waiting: default label');
check(str_contains($waitingPanel, 'EduCoachWaitingPanel'), 'waiting: shared panel');
check(str_contains($cards, 'isWaiting'), 'cards: waiting state');
check(str_contains($cards, 'EduCoachWaitingPanel'), 'cards: waiting panel wired');
check(str_contains($cards, 'CoachMessageText'), 'cards: bold highlight render');

check(str_contains($chat, 'isWaiting'), 'chat: waiting state');
check(str_contains($chat, 'CoachMessageText'), 'chat: bold highlight render');
check(str_contains($chat, '!isWaiting'), 'chat: hide pinned coach while waiting');

check(str_contains($structure, 'waiting'), 'structure bar: waiting prop');
check(str_contains($structure, '채우는 중'), 'structure bar: filling label');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
