<?php
/**
 * GIST EDU — 수렴형 Mix-up E2E 격리 테스트
 *
 * chat.php 없이 eduBuildMixupContext + Hammer 경로 검증
 * 이란 퀘스트(Q-IRAN-FOREVER-001) fixture 사용
 *
 * Usage:
 *   php tools/edu_convergent_e2e_test.php
 *   php tools/edu_convergent_e2e_test.php --live
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduConfig.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/backend/Services/edu/Agents/Hammer.php';

use Services\Edu\Agents\Hammer;

$useLive = in_array('--live', $argv ?? [], true);

echo "=== GIST EDU Convergent E2E (격리) ===\n";
echo 'mode: ' . ($useLive ? 'LIVE OpenAI' : 'MOCK') . "\n\n";

$quest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'pro_line' => '기술적 관점',
    'con_line' => '구조적 관점',
    'conflict_summary' => '이란 전쟁은 깔끔하게 끝나지 않는다',
    'hammer_hints' => [
        'mode' => 'convergent',
        'shared_conclusion' => '이란 전쟁은 깔끔하게 끝나지 않는다',
        'counter_map' => ['tech' => 'structure', 'politics' => 'tech', 'structure' => 'politics'],
        'axes' => [
            [
                'axis_id' => 'tech',
                'axis_label' => '기술적 한계',
                'thesis' => '정밀타격으로도 정치적 결말 못 얻는다',
                'author' => '로런스 프리드먼',
                'news_id' => 555,
                'contrast_prompt' => [
                    'names_axis' => '무기와 기술의 한계',
                    'distinguishes_from' => [
                        'politics' => '이건 정치인 탓이 아니야',
                        'structure' => '이건 전쟁은 원래 그래가 아니야',
                    ],
                    'pivot_question' => '네 근거는 무기의 한계 때문이야, 아니면 이란이라는 상대가 특별해서야?',
                ],
            ],
            [
                'axis_id' => 'politics',
                'axis_label' => '국내정치 함정',
                'thesis' => '미국 국내정치가 장기 전략을 불가능하게 한다',
                'author' => '이코노미스트',
                'news_id' => 422,
                'contrast_prompt' => [
                    'names_axis' => '미국 국내정치의 함정',
                    'distinguishes_from' => [
                        'tech' => '이건 무기 문제가 아니야',
                        'structure' => '이건 전쟁의 본질이 아니야',
                    ],
                    'pivot_question' => '네 근거는 미국 정치가 특히 불안정해서야, 아니면 어느 나라든 정치는 다 그래서야?',
                ],
            ],
            [
                'axis_id' => 'structure',
                'axis_label' => '전쟁의 구조',
                'thesis' => '불안정한 봉합이 전쟁의 구조적 귀결이다',
                'author' => '기데온 로즈',
                'news_id' => 528,
                'contrast_prompt' => [
                    'names_axis' => '전쟁이라는 것 자체의 구조',
                    'distinguishes_from' => [
                        'tech' => '이건 무기 탓이 아니야',
                        'politics' => '이건 정치인 탓이 아니야',
                    ],
                    'pivot_question' => '네 근거는 이란이 특별해서야, 아니면 전쟁이라는 게 원래 그래서야?',
                ],
            ],
        ],
    ],
];

$cases = [
    ['input' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.', 'expect_mode' => 'convergent', 'expect_axis' => 'tech'],
    ['input' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.', 'expect_mode' => 'convergent_meta_ask', 'expect_axis' => null],
];

$pass = 0;
$fail = 0;

foreach ($cases as $i => $case) {
    $n = $i + 1;
    echo "--- Case {$n} ---\n";
    echo "학생: \"{$case['input']}\"\n";

    $mixup = eduBuildMixupContext($quest, null);
    $ragSkipped = $mixup['mixup_context'] === '' && $mixup['mixup_sources'] === [];
    echo 'RAG 스킵: ' . ($ragSkipped ? 'O' : 'X') . "\n";

    if ($useLive) {
        $llm = eduLlm();
        $hammer = new Hammer($llm);
    } else {
        $hammer = new Hammer(new class {
            public function chat(string $system, array $messages, int $maxTokens = 400, float $temp = 0.7): array
            {
                return ['success' => true, 'content' => '네가 말한 표현을 보면, 네 근거는 이란이 특별해서야, 아니면 전쟁이라는 게 원래 그래서야?'];
            }
            public function haiku(string $system, array $messages, int $maxTokens = 512): array
            {
                $user = $messages[0]['content'] ?? '';
                if (str_contains($user, '복잡')) {
                    return ['success' => true, 'content' => '{"axis_id":"structure","scores":{"tech":0.2,"politics":0.2,"structure":0.9},"confidence":"high","cue":"복잡"}'];
                }
                if (str_contains($user, '폭격') || str_contains($user, '미사일')) {
                    return ['success' => true, 'content' => '{"axis_id":"tech","scores":{"tech":0.95,"politics":0.02,"structure":0.03},"confidence":"high","cue":"폭격"}'];
                }
                return ['success' => true, 'content' => '{"axis_id":"tech","scores":{"tech":0.6,"politics":0.2,"structure":0.2},"confidence":"medium","cue":"?"}'];
            }
        });
    }

    $strike = $hammer->strike('pro', $case['input'], $quest);
    $mode = $strike['mode'] ?? '';
    $axis = $strike['student_axis'] ?? null;

    echo "hammer_mode: {$mode}\n";
    echo 'student_axis: ' . ($axis ?? '(meta)') . "\n";

    $ok = $mode === $case['expect_mode']
        && ($case['expect_axis'] === null || $axis === $case['expect_axis'])
        && $ragSkipped;

    if ($ok) {
        echo "PASS\n\n";
        $pass++;
    } else {
        echo "FAIL (expect mode={$case['expect_mode']}, axis=" . ($case['expect_axis'] ?? 'null') . ")\n\n";
        $fail++;
    }
}

echo "=== E2E 결과: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
