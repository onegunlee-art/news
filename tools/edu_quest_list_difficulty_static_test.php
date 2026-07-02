<?php
/**
 * list.php difficulty fields + recommended_for_you 정적 검증
 *
 * Usage: php tools/edu_quest_list_difficulty_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
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

echo "=== edu quest list difficulty static test ===\n\n";

$quest = [
    'id' => 'test-id',
    'quest_code' => 'Q-AUTO-DC-150',
    'quest_title' => '전기세',
    'status' => 'approved',
    'difficulty_level' => 1,
    'pro_line' => 'a',
    'con_line' => 'b',
    'scores' => [],
];

$item = eduQuestToListItem($quest, []);
ok('difficulty_level exposed', ($item['difficulty_level'] ?? null) === 1);
ok('difficulty_label_ko', ($item['difficulty_label_ko'] ?? '') === '관찰자');
ok('L1 student_frame default', str_contains((string) ($item['difficulty_student_frame_ko'] ?? ''), '시작하기 좋은'));

$categories = file_get_contents($root . '/public/api/edu/quests/categories.php');
ok('categories has levels', $categories !== false && str_contains($categories, "'levels'"));
ok('categories no frames', $categories !== false && !str_contains($categories, "'frames'"));

$list = file_get_contents($root . '/public/api/edu/quests/list.php');
ok('list has level filter', $list !== false && str_contains($list, 'levelFilter'));
ok('list has recommended_for_you', $list !== false && str_contains($list, 'recommended_for_you'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
