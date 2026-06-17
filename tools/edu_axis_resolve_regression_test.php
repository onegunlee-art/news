<?php
/**
 * eduResolveStudentAxis / eduMatchStudentAxisFromText 회귀 (이란·일본·핵억지)
 *
 * Usage: php tools/edu_axis_resolve_regression_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

// 이란 FOREVER (tech/politics/structure)
$iranQuest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'pro_line' => '기술적 관점',
    'con_line' => '구조적 관점',
    'hammer_hints' => [
        'mode' => 'convergent',
        'axes' => [
            ['axis_id' => 'tech', 'axis_label' => '기술적 한계', 'contrast_prompt' => ['names_axis' => '무기와 기술의 한계']],
            ['axis_id' => 'politics', 'axis_label' => '국내정치 함정', 'contrast_prompt' => ['names_axis' => '미국 국내정치의 함정']],
            ['axis_id' => 'structure', 'axis_label' => '전쟁의 구조', 'contrast_prompt' => ['names_axis' => '전쟁이라는 것 자체의 구조']],
        ],
    ],
];

$japanQuest = eduG09DecQuestFixture();
$nukeQuest = eduNuke630QuestFixture();

$cases = [
    // --- 이란 ---
    ['quest' => 'iran', 'fn' => 'text', 'input' => '미사일만으로는 이란을 완전히 이길 수 없다고 봐요', 'expect' => 'tech'],
    ['quest' => 'iran', 'fn' => 'text', 'input' => '이란 국민이 미국편에서 멀어지고 있다는 게 중요해요', 'expect' => 'politics'],
    ['quest' => 'iran', 'fn' => 'text', 'input' => '전쟁은 원래 의도와 상관없이 얽히는거 같아', 'expect' => 'structure'],
    ['quest' => 'iran', 'fn' => 'text', 'input' => '트럼프 정권 때문에 전략이 일관되지 않아요', 'expect' => 'politics'],
    ['quest' => 'iran', 'fn' => 'bp', 'blueprint' => ['rebuttal' => '전쟁은 원래 의도와 상관없이 얽히는거 같아'], 'expect' => 'structure'],
    // --- 일본 G09 ---
    ['quest' => 'japan', 'fn' => 'text', 'input' => '중국·대만 때문에 일본 주변도 위험해져서 미사일이 필요하다고 봐요', 'expect' => 'tech'],
    ['quest' => 'japan', 'fn' => 'text', 'input' => '미국이 바로 안 와줄 수도 있어서 일본이 스스로 버티려는 거 같아요', 'expect' => 'politics'],
    ['quest' => 'japan', 'fn' => 'text', 'input' => '예전 일본은 방어만 했는데, 이제는 강한 나라로 바뀌는 흐름이라 미사일도 그 일부인 것 같아요', 'expect' => 'structure'],
    ['quest' => 'japan', 'fn' => 'bp', 'blueprint' => ['student_axis' => 'tech', 'reason' => '대만'], 'expect' => 'tech'],
    // --- 핵억지 ---
    ['quest' => 'nuke', 'fn' => 'text', 'input' => '새 약속이 필요하다', 'expect' => 'norms'],
    ['quest' => 'nuke', 'fn' => 'text', 'input' => '핵이 있어도 드론 공격은 못 막는다', 'expect' => 'military'],
    ['quest' => 'nuke', 'fn' => 'text', 'input' => '방공이랑 기지 방호를 더 키워야 한다', 'expect' => 'defense'],
    ['quest' => 'nuke', 'fn' => 'bp', 'blueprint' => ['rebuttal' => '새 약속이 필요하다'], 'expect' => 'norms'],
];

$quests = ['iran' => $iranQuest, 'japan' => $japanQuest, 'nuke' => $nukeQuest];

echo "=== eduResolveStudentAxis 회귀 테스트 ===\n\n";

$pass = 0;
$fail = 0;

foreach ($cases as $i => $case) {
    $quest = $quests[$case['quest']];
    $label = ($case['quest'] ?? '') . ' #' . ($i + 1);

    if ($case['fn'] === 'text') {
        $result = eduMatchStudentAxisFromText($case['input'], $quest);
    } else {
        $result = eduResolveStudentAxis($case['blueprint'], $quest);
    }

    $got = $result['axis_id'] ?? '(null)';
    $ok = $got === $case['expect'];
    $snippet = $case['input'] ?? json_encode($case['blueprint'], JSON_UNESCAPED_UNICODE);

    echo ($ok ? 'PASS' : 'FAIL') . " [{$label}] expect={$case['expect']} got={$got}\n";
    echo '  ' . mb_substr($snippet, 0, 70) . "\n";
    if (!$ok && $result !== null) {
        echo '  label: ' . ($result['axis_label'] ?? '') . "\n";
    }
    echo "\n";

    $ok ? $pass++ : $fail++;
}

echo "=== 요약: PASS {$pass} / FAIL {$fail} ===\n";
exit($fail > 0 ? 1 : 0);
