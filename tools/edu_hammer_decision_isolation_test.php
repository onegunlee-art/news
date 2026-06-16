<?php
/**
 * GIST EDU — Hammer decision_inquiry 격리 테스트 (Phase 2-2)
 *
 * Usage:
 *   php tools/edu_hammer_decision_isolation_test.php --live
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/backend/Services/edu/Agents/Hammer.php';

use Services\Edu\Agents\Hammer;

$useLive = in_array('--live', $argv ?? [], true);

echo "=== Hammer decision_inquiry 격리 (Phase 2-2) ===\n";
echo 'mode: ' . ($useLive ? 'LIVE OpenAI' : 'MOCK') . "\n\n";

$decisionQuest = [
    'quest_code' => 'Q-IRAN-DEC-202606',
    'quest_title' => '트럼프는 군대를 보내는 대신 미사일로만 공격했어. 왜 그랬을까? 너라면?',
    'pro_line' => '미사일만 쓴 선택이 맞았다고 본다',
    'con_line' => '군대를 보내거나, 다른 방법이 나았다고 본다',
    'hammer_hints' => [
        'mode' => 'convergent',
        'quest_frame' => 'decision_inquiry',
        'time_anchor' => '2026년 6월 기준',
        'shared_conclusion' => '2026년 6월, 트럼프는 이란에 군대를 보내지 않고 미사일과 공격으로 대응하기로 했다',
        'axes' => [
            [
                'axis_id' => 'tech',
                'axis_label' => '무기·공격 방법',
                'author' => '로런스 프리드먼',
                'contrast_prompt' => [
                    'names_axis' => '미사일·공격이 그나마 맞는 방법이었는지',
                    'distinguishes_from' => [
                        'politics' => '이건 트럼프나 미국 사람들 반응이 아니야. 무기·공격 방법 자체를 본다',
                        'structure' => '이건 \'전쟁은 원래 그래\'가 아니야. 이번에 미사일을 쓴 이유를 본다',
                    ],
                    'pivot_question' => '네 생각은 \'미사일이 맞는 방법\'이었단 거야, \'군대 보내는 것보다 덜 나쁜 선택\'이었단 거야?',
                ],
            ],
            [
                'axis_id' => 'politics',
                'axis_label' => '미국·세계 반응',
                'author' => '이코노미스트',
                'contrast_prompt' => [
                    'names_axis' => '미국 사람들·다른 나라 반응 — 왜 지금 이 방법이었는지',
                    'distinguishes_from' => [
                        'tech' => '이건 미사일 성능 문제가 아니야. 사람들·나라들 반응을 본다',
                        'structure' => '이건 나중 일이 아니야. 2026년 당시 미국 안팎 상황을 본다',
                    ],
                    'pivot_question' => '네 생각은 \'미국 사람들 반응\' 때문이었단 거야, \'다른 나라들 부담\' 때문이었단 거야?',
                ],
            ],
            [
                'axis_id' => 'structure',
                'axis_label' => '나중에 어떻게 될지',
                'author' => '기데온 로즈',
                'contrast_prompt' => [
                    'names_axis' => '앞으로 전쟁이 어떻게 이어질지 — 그 선택의 대가',
                    'distinguishes_from' => [
                        'tech' => '이건 이번 공격이 맞았냐가 아니야. 몇 달·몇 년 뒤를 본다',
                        'politics' => '이건 트럼프 한 사람 계산이 아니야. 전쟁이 원래 이렇게 흘러가는지 본다',
                    ],
                    'pivot_question' => '네가 말한 \'대가\'는 \'지금 당장 피해\'야, \'앞으로 전쟁이 더 길어지는 것\'이야?',
                ],
            ],
        ],
        'counter_map' => ['tech' => 'structure', 'politics' => 'tech', 'structure' => 'politics'],
    ],
];

$legacyQuest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'hammer_hints' => [
        'mode' => 'convergent',
        'shared_conclusion' => '이란 전쟁은 깔끔하게 끝나지 않는다',
        'axes' => [
            ['axis_id' => 'tech', 'axis_label' => '기술적 한계', 'author' => '프리드먼', 'contrast_prompt' => ['names_axis' => '무기 한계', 'distinguishes_from' => [], 'pivot_question' => '무기 한계 때문?']],
            ['axis_id' => 'politics', 'axis_label' => '국내정치 함정', 'author' => '이코노미스트', 'contrast_prompt' => ['names_axis' => '정치 함정', 'distinguishes_from' => [], 'pivot_question' => '정치 때문?']],
            ['axis_id' => 'structure', 'axis_label' => '전쟁의 구조', 'author' => '로즈', 'contrast_prompt' => ['names_axis' => '전쟁 구조', 'distinguishes_from' => [], 'pivot_question' => '구조 때문?']],
        ],
        'counter_map' => ['tech' => 'structure', 'politics' => 'tech', 'structure' => 'politics'],
    ],
];

$cases = [
    [
        'label' => 'H1 tech — 미사일·공격',
        'quest' => $decisionQuest,
        'reason' => '미사일만 써도 목표는 맞출 수 있지만, 이란처럼 버티는 나라는 군대 없이는 못 이겨요.',
        'expect_axis' => 'tech',
    ],
    [
        'label' => 'H2 politics — 미국 반응',
        'quest' => $decisionQuest,
        'reason' => '군대 보내면 미국 사람들이 더 반대할 것 같아서 미사일만 쓴 게 맞다고 봐요.',
        'expect_axis' => 'politics',
    ],
    [
        'label' => 'H3 structure — 나중 대가',
        'quest' => $decisionQuest,
        'reason' => '미사일만 쓰면 전쟁이 더 길어질 수도 있어서 나중에 더 큰 대가가 올 것 같아요.',
        'expect_axis' => 'structure',
    ],
    [
        'label' => 'H4 vague — meta_ask',
        'quest' => $decisionQuest,
        'reason' => '그냥 복잡해서 잘 모르겠어요.',
        'expect_mode' => 'convergent_meta_ask',
    ],
    [
        'label' => 'H5 con — 다른 방법',
        'quest' => $decisionQuest,
        'reason' => '군대를 보내거나 협상부터 했어야 했다고 봐요. 미사일만으로는 부족해요.',
        'expect_axis' => 'tech|politics',
    ],
    [
        'label' => 'H6 pro — 덜 위험',
        'quest' => $decisionQuest,
        'reason' => '미사일만 쓴 건 군대 보내는 것보다 덜 위험한 선택이라 괜찮다고 봐요.',
        'expect_axis' => 'tech|politics',
    ],
    [
        'label' => 'R1 회귀 — result_prediction meta_ask',
        'quest' => $legacyQuest,
        'reason' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.',
        'expect_mode' => 'convergent_meta_ask',
        'expect_old_frame' => true,
    ],
];

function hammerCheckOldFrame(string $text, bool $expectOld): array
{
    $issues = [];
    $bad = ['우리 둘 다', '동의해', '라고 썼어'];
    foreach ($bad as $w) {
        if (!$expectOld && mb_strpos($text, $w) !== false) {
            $issues[] = "옛 프레임: {$w}";
        }
    }
    if (!$expectOld) {
        foreach (['안 끝나', '끝나지 않', '끝낼 수'] as $w) {
            if (mb_strpos($text, $w) !== false) {
                $issues[] = "결과예측 잔재: {$w}";
            }
        }
    }
    if ($expectOld && mb_strpos($text, '우리 둘 다') === false && mb_strpos($text, 'convergent_meta_ask') === false) {
        // meta on legacy should contain old framing
    }
    return $issues;
}

if ($useLive) {
    $llm = eduLlm();
    $hammer = new Hammer($llm);
} else {
    fwrite(STDERR, "실제 검증은 --live 필요\n");
    exit(1);
}

$pass = 0;
$fail = 0;

foreach ($cases as $i => $case) {
    $n = $i + 1;
    echo "--- Case {$n}: {$case['label']} ---\n";
    echo "학생: \"{$case['reason']}\"\n";

    $result = $hammer->strike('pro', $case['reason'], $case['quest'], 'medium');
    $mode = $result['mode'] ?? '';
    $axis = $result['student_axis'] ?? '-';
    $output = trim((string) ($result['counter_argument'] ?? ''));

    echo "mode: {$mode} | axis: {$axis}\n";
    if (!empty($result['pivot_question'])) {
        echo 'pivot: ' . $result['pivot_question'] . "\n";
    }
    echo "\n[Hammer 출력]\n{$output}\n";

    $issues = hammerCheckOldFrame($output, !empty($case['expect_old_frame']));

    if (!empty($case['expect_mode'])) {
        if ($mode !== $case['expect_mode']) {
            $issues[] = "기대 mode {$case['expect_mode']}, 실제 {$mode}";
        }
    }
    if (!empty($case['expect_axis']) && $mode === 'convergent') {
        $expected = explode('|', $case['expect_axis']);
        if (!in_array($axis, $expected, true)) {
            $issues[] = "기대 axis {$case['expect_axis']}, 실제 {$axis}";
        }
    }
    if (!empty($case['expect_old_frame']) && $mode === 'convergent_meta_ask') {
        if (mb_strpos($output, '우리 둘 다') === false) {
            $issues[] = '회귀: 옛 meta_ask "우리 둘 다" 없음';
        }
    }
    if (!empty($case['expect_mode']) && $case['expect_mode'] === 'convergent_meta_ask' && empty($case['expect_old_frame'])) {
        if (mb_strpos($output, '이미') === false && mb_strpos($output, '결정') === false) {
            $issues[] = 'decision meta_ask에 결정 사실 프레이밍 없음';
        }
        if (mb_strpos($output, '우리 둘 다') !== false) {
            $issues[] = 'decision meta_ask에 옛 "우리 둘 다" 잔재';
        }
    }

    if (empty($issues)) {
        echo "\n판정: PASS\n\n";
        $pass++;
    } else {
        echo "\n판정: CHECK — " . implode(', ', $issues) . "\n\n";
        $fail++;
    }
}

echo "=== 요약: PASS {$pass} / CHECK {$fail} ===\n";
