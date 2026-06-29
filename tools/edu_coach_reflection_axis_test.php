<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

$quest = [
    'quest_code' => 'Q-NUKE-AXIS-630',
    'hammer_hints' => ['_guide_axes' => eduNuke630Axes()],
    'articles' => [['news_id' => 630, 'role' => 'primary']],
];
$blueprint = [
    'guide_opening' => '핵이 있으면 안전하다고 생각해.',
    'guide_axis_answers' => [
        'military' => '드론 공격은 못 막는다고 봤어.',
        'norms' => '약속이 필요하다고 생각해.',
        'defense' => '방공에 투자해야 한다고 봤어.',
    ],
    'guide_student_conclusion' => '나는 핵만으로는 부족하다고 생각해.',
];

$lines = eduCoachGuideReflectionLines($blueprint, $quest);
echo "=== 630 reflection lines ===\n";
foreach ($lines as $i => $line) {
    echo ($i + 1) . ") {$line}\n";
    if (preg_match('/축\s*[0-9]/u', $line)) {
        fwrite(STDERR, "FAIL: internal axis label in: {$line}\n");
        exit(1);
    }
}
if (!str_contains($lines[1] ?? '', '군사') && !str_contains($lines[1] ?? '', '막기')) {
    // point label for military axis
    if (!str_contains($lines[1] ?? '', '핵·억지') && !str_contains($lines[1] ?? '', '드론')) {
        fwrite(STDERR, "FAIL: expected axis context in line 2: " . ($lines[1] ?? '') . "\n");
        exit(1);
    }
}
echo "OK\n";
