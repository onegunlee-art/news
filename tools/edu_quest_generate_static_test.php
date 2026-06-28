<?php
/**
 * Step 2 quest generate — 멱등·매핑 정적 회귀 (DB/LLM 없음)
 *
 * Usage: php tools/edu_quest_generate_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestGenerate.php';

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

echo "=== Quest generate static test ===\n\n";

ok('quest code Q-GIST-{id}', eduQuestGenerateQuestCode(220) === 'Q-GIST-220');
ok('protected Q-NUKE-AXIS-630', eduQuestGenerateIsProtectedQuestCode('Q-NUKE-AXIS-630'));
ok('Q-GIST not protected', !eduQuestGenerateIsProtectedQuestCode('Q-GIST-220'));

$existing = [
    630 => ['quest_code' => 'Q-NUKE-AXIS-630', 'quest_id' => 'x', 'status' => 'approved'],
];
ok('idempotent skip 630', eduQuestGenerateIsSkippable(630, $existing) !== null);
ok('new id not skipped', eduQuestGenerateIsSkippable(999, $existing) === null);

$hinge = [
    'news_id' => 220,
    'title' => '유가 급등',
    'hinge' => '유가가 오르면 좋지만 동시에 경기가 나빠질 수 있다',
    'side_a' => '유가 상승은 에너지 수출국에 이득',
    'side_b' => '수입국 경기에 부담',
    'hook_student' => '유가 오르면 누가 이득일까?',
    'shake_prompt' => '2024년 OPEC 감산 이후 유가 변동을 보면?',
    'confidence' => 'high',
];
$axisExtraction = [
    'axes' => [
        [
            'point' => '공급 축',
            'core_question' => '공급이 줄면 가격은?',
            'article_fact' => 'OPEC+ 감산',
        ],
        [
            'point' => '수요 축',
            'core_question' => '경기 둔화면 수요는?',
            'article_fact' => '중국 수요 둔화',
        ],
    ],
];
$meta = ['title' => '유가', 'category' => 'economy', 'topic_label' => ''];
$draft = eduQuestGenerateBuildDraft($hinge, $meta, $axisExtraction, []);
ok('draft Q-GIST code', ($draft['quest_code'] ?? '') === 'Q-GIST-220');
ok('coach_mode axis_guide', ($draft['hammer_hints']['coach_mode'] ?? '') === 'axis_guide_v1');
ok('axes >= 2', count($draft['hammer_hints']['_guide_axes'] ?? []) >= 2);
ok('status draft in _db', ($draft['_db']['status'] ?? '') === 'draft');
ok('source batch', ($draft['_db']['scores']['source'] ?? '') === EDU_QUEST_GENERATE_SOURCE);

$tool = is_file($root . '/tools/edu_quest_generate.php')
    ? (string) file_get_contents($root . '/tools/edu_quest_generate.php')
    : '';
ok('CLI exists', $tool !== '');
ok('CLI default draft', str_contains($tool, "'status' => 'draft'") || str_contains($tool, 'draft only'));
ok('CLI idempotent note', str_contains($tool, '멱등'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
