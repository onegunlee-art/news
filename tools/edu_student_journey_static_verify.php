<?php
/**
 * EDU 학생 여정 v1 — 정적 회귀 (DB/LLM 없음)
 *
 * 카드+프로필+코치 axis_guide 회귀를 파일·로컬 PHP 테스트로 확인.
 * Usage: php tools/edu_student_journey_static_verify.php
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
    if (!is_file($path)) {
        return '';
    }

    return (string) file_get_contents($path);
}

echo "=== EDU Student Journey v1 static verify ===\n\n";

// A/C — 카드: 질문 전문, why 콜라보, footer dead zone
$cards = read('src/frontend/src/pages/edu/QuestFlowCards.tsx');
check(!str_contains($cards, 'pinNarrativePrompt'), 'cards: no pinNarrativePrompt (one-line clamp removed)');
check(!str_contains($cards, 'narrativePromptOneLine'), 'cards: no narrativePromptOneLine import usage');
check(!preg_match('/line-clamp-1.*displayQuestion|displayQuestion.*line-clamp-1/s', $cards), 'cards: no line-clamp-1 on coach question');
check(str_contains($cards, 'displayQuestionParagraphs.map'), 'cards: full question paragraphs in card body');
check(str_contains($cards, 'showNarrativeLayout'), 'cards: input yields when keyboard (snippet shrink)');
check(str_contains($cards, 'handleChoiceSelect'), 'cards: choice button handler');
check(str_contains($cards, 'useVisualViewportLayout'), 'cards: visualViewport keyboard shell');
check(str_contains($cards, 'CardStructureBar'), 'cards: structure bar with animations');
check(str_contains($cards, 'structureNudgeForAxisPass'), 'cards: axis pass nudge wired');
check(str_contains($cards, "phase === 'hammer'"), 'cards: hammer question font reduced');
check(str_contains($cards, 'EduEssayCompletionPanel'), 'cards: essay completion toggle panel');
check(str_contains($cards, 'isWaiting'), 'cards: waiting state on submit');
check(str_contains($cards, 'CoachMessageText'), 'cards: coach bold highlight');

$completionPanel = read('src/frontend/src/components/edu/EduEssayCompletionPanel.tsx');
check(str_contains($completionPanel, '구조 보기'), 'completion: structure view toggle');

$chat = read('src/frontend/src/pages/edu/QuestFlowChat.tsx');
check(str_contains($chat, 'EduEssayCompletionPanel'), 'chat: essay completion toggle panel');
check(str_contains($chat, 'isWaiting'), 'chat: waiting state on submit');
check(str_contains($chat, 'CoachMessageText'), 'chat: coach bold highlight');
check(str_contains($chat, "resolveQuestFooterMode"), 'chat: footer mode resolver');
check(str_contains($chat, 'dialogueLength > 0'), 'chat: stance dead zone fix (dialogue > 0 -> chat)');
check(str_contains($chat, '/edu/profile'), 'chat: profile link after completion');

// B — 프로필
$profile = read('src/frontend/src/pages/edu/EduProfilePage.tsx');
check(str_contains($profile, 'EduStudentProfileHero'), 'profile: streak hero component');
check(str_contains($profile, 'studentSessions'), 'profile: completed sessions list');
check(str_contains($profile, '다시 보기'), 'profile: reread button');

$hero = read('src/frontend/src/components/edu/EduStudentProfileHero.tsx');
check(str_contains($hero, 'edu-game-streak-live'), 'profile: streak animation class');
check(str_contains($hero, 'streak_days'), 'profile: streak from tier data');

$profilePhp = read('public/api/edu/student/profile.php');
check(str_contains($profilePhp, 'topics_count'), 'api: profile topics_count');
check(str_contains($profilePhp, 'eduFetchTierRow'), 'api: tier from edu_user_tier');

$home = read('src/frontend/src/pages/edu/EduHomePage.tsx');
check(str_contains($home, '/edu/profile'), 'home: profile navigation');
check(str_contains($home, '내 프로필'), 'home: profile label');

$coachLevel = read('public/api/edu/lib/eduCoachLevel.php');
check(str_contains($coachLevel, 'EDU_COACH_LEVEL_ADVANCED'), 'coach: level 7 constant');
check(str_contains($coachLevel, 'EDU_COACH_LEVEL_DEFAULT'), 'coach: default level constant');

$chatPhp = read('public/api/edu/session/chat.php');
check(str_contains($chatPhp, 'eduResolveCoachLevel'), 'coach: level resolved in chat');
check(str_contains($chatPhp, 'eduBlueprintFreezeCoachLevel'), 'coach: level frozen in blueprint');

// D — 코치 why (local PHP)
echo "\n--- edu_coach_guide_test.php ---\n";
$coachOut = [];
$coachCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_coach_guide_test.php') . ' 2>&1', $coachOut, $coachCode);
$coachText = implode("\n", $coachOut);
echo $coachText . "\n";
check($coachCode === 0 && str_contains($coachText, '0 failed'), 'coach guide regression (630/150/196/288 + why collab)');

echo "\n--- edu_coach_guide_level7_parity_test.php ---\n";
$parityOut = [];
$parityCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_coach_guide_level7_parity_test.php') . ' 2>&1', $parityOut, $parityCode);
$parityText = implode("\n", $parityOut);
echo $parityText . "\n";
check($parityCode === 0 && str_contains($parityText, '0 failed'), 'coach level 7 parity (v1 preserve)');

echo "\n--- edu_coach_guide_elementary_test.php ---\n";
$elemOut = [];
$elemCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_coach_guide_elementary_test.php') . ' 2>&1', $elemOut, $elemCode);
$elemText = implode("\n", $elemOut);
echo $elemText . "\n";
check($elemCode === 0 && str_contains($elemText, '0 failed'), 'coach level 1 elementary guide');

echo "\n--- edu_compose_narration_static_verify.php ---\n";
$narrOut = [];
$narrCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_compose_narration_static_verify.php') . ' 2>&1', $narrOut, $narrCode);
$narrText = implode("\n", $narrOut);
echo $narrText . "\n";
check($narrCode === 0 && str_contains($narrText, '0 failed'), 'composer narration weave static');

echo "\n--- edu_ux_waiting_highlight_static_verify.php ---\n";
$uxOut = [];
$uxCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_ux_waiting_highlight_static_verify.php') . ' 2>&1', $uxOut, $uxCode);
$uxText = implode("\n", $uxOut);
echo $uxText . "\n";
check($uxCode === 0 && str_contains($uxText, '0 failed'), 'ux waiting + bold highlight static');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
