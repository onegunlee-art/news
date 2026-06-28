<?php
/**
 * Step 1 거름망 — 점수·분류 정적 회귀 (MySQL/LLM 없음)
 *
 * Usage: php tools/edu_quest_filter_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';

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

echo "=== Quest filter static test ===\n\n";

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
    'title' => '이란 핵과 억지',
    'category' => 'security',
    'topic_label' => '이란',
    'published_at' => date('Y-m-d', strtotime('-30 days')),
];

$strong = eduQuestFilterClassify($strongExt, $strongMeta);
ok('strong hinge → 가능 or 경계', in_array($strong['verdict'], ['가능', '경계'], true));
ok('strong score >= 55', ($strong['score'] ?? 0) >= 55);

$weakExt = [
    'news_id' => 999,
    'hinge' => null,
    'side_a' => '',
    'side_b' => '',
    'confidence' => 'low',
];
$weakMeta = ['title' => '오늘의 날씨 총정리', 'category' => 'life', 'published_at' => date('Y-m-d')];
$weak = eduQuestFilterClassify($weakExt, $weakMeta);
ok('no hinge → 불가', ($weak['verdict'] ?? '') === '불가');

$staleMeta = [
    'title' => '지난주 선거 결과 발표',
    'category' => 'politics',
    'published_at' => date('Y-m-d', strtotime('-400 days')),
];
$staleTime = eduQuestFilterTimeliness($staleMeta);
ok('stale election → 시의성 지남', ($staleTime['kind'] ?? '') === 'stale');

$everMeta = [
    'title' => '이란 핵 협상의 딜레마',
    'category' => 'security',
    'published_at' => date('Y-m-d', strtotime('-500 days')),
];
$everTime = eduQuestFilterTimeliness($everMeta);
ok('evergreen topic → 주제형', ($everTime['kind'] ?? '') === 'evergreen');

$tool = is_file($root . '/tools/edu_quest_filter_verify.php')
    ? (string) file_get_contents($root . '/tools/edu_quest_filter_verify.php')
    : '';
ok('verify CLI exists', $tool !== '');
ok('verify: no edu_daily_quests write', !str_contains($tool, "insert('edu_daily_quests'"));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
