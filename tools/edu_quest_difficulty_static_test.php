<?php
/**
 * eduQuestDifficulty — L1~L5 판정 정적 회귀
 *
 * Usage: php tools/edu_quest_difficulty_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';
require_once $root . '/public/api/edu/lib/eduQuestDifficulty.php';
require_once $root . '/public/api/edu/lib/eduCoachLevel.php';

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

echo "=== eduQuestDifficulty static test ===\n\n";

$labels = eduQuestDifficultyLabel(EDU_COACH_LEVEL_L3);
ok('L3 label matches coach', ($labels['ko'] ?? '') === '논객');

$strongExt = [
    'news_id' => 630,
    'hinge' => '이란은 핵을 포기해야 하지만, 억지로 막으면 더 위험해질 수 있다',
    'side_a' => '핵 확산은 막아야 한다',
    'side_b' => '억지는 역효과를 낳는다',
    'hook_student' => '핵은 무조건 나쁜 걸까?',
    'shake_prompt' => '과거 이란 핵 협상에서 어떤 일이 있었는지 보면?',
    'confidence' => 'high',
];
$strongMeta = [
    'news_id' => 630,
    'title' => '이란 핵과 억지',
    'category' => 'security',
    'topic_label' => '이란',
    'published_at' => date('Y-m-d', strtotime('-30 days')),
];
$strongQuest = [
    'quest_title' => '이란 핵 딜레마',
    'pro_line' => '핵 확산은 막아야 한다',
    'con_line' => '억지는 역효과',
    'conflict_summary' => '',
    'scores' => ['category' => 'middle_east_iran', 'lens' => 'security'],
    'status' => 'approved',
];
$strong = eduQuestDeriveDifficultyLevel($strongQuest, $strongExt, $strongMeta);
ok('strong hinge → L4 or L5', $strong['level'] >= EDU_COACH_LEVEL_L4);
ok('strong score >= 75', ($strong['score'] ?? 0) >= 75);

$weakQuest = [
    'quest_title' => '약한 경첩',
    'pro_line' => '찬성',
    'con_line' => '반대',
    'conflict_summary' => '그냥 의견이 다르다',
    'scores' => [],
];
$weakExt = [
    'news_id' => 1,
    'hinge' => '그냥 의견이 다르다',
    'side_a' => '찬성',
    'side_b' => '반대',
    'confidence' => 'low',
];
$weakMeta = ['title' => '약한 글', 'category' => 'life', 'published_at' => date('Y-m-d')];
$weak = eduQuestDeriveDifficultyLevel($weakQuest, $weakExt, $weakMeta);
ok('weak tension caps at L2', $weak['level'] <= EDU_COACH_LEVEL_L2);

ok('score 55 → L2', eduQuestDifficultyLevelFromScore(58, null, 'medium') === EDU_COACH_LEVEL_L2);
ok('score 65 → L3', eduQuestDifficultyLevelFromScore(68, null, 'medium') === EDU_COACH_LEVEL_L3);
ok('score 75 → L4', eduQuestDifficultyLevelFromScore(78, null, 'medium') === EDU_COACH_LEVEL_L4);
ok('score 85 → L5', eduQuestDifficultyLevelFromScore(90, null, 'high') === EDU_COACH_LEVEL_L5);

$fallback = eduQuestDifficultyFallbackExtraction([
    'pro_line' => 'A side long enough here',
    'con_line' => 'B side also long enough',
    'conflict_summary' => '',
]);
ok('fallback extraction has hinge', ($fallback['hinge'] ?? '') !== '');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
