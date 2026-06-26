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

$chat = read('src/frontend/src/pages/edu/QuestFlowChat.tsx');
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

// D — 코치 why (local PHP)
echo "\n--- edu_coach_guide_test.php ---\n";
$coachOut = [];
$coachCode = 0;
exec('php ' . escapeshellarg($root . '/tools/edu_coach_guide_test.php') . ' 2>&1', $coachOut, $coachCode);
$coachText = implode("\n", $coachOut);
echo $coachText . "\n";
check($coachCode === 0 && str_contains($coachText, '0 failed'), 'coach guide regression (630/150/196/288 + why collab)');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
