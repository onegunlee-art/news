<?php
/**
 * GIST EDU — Hammer Convergent 격리 테스트 (Phase 2a)
 *
 * chat.php 수정 없이 Hammer 로직만 단독 호출 (라이브 학생 세션 영향 0)
 *
 * 사용법:
 *   php tools/edu_hammer_convergent_test.php              # Mock (격리 검증)
 *   php tools/edu_hammer_convergent_test.php --live     # OpenAI (기본)
 *   php tools/edu_hammer_convergent_test.php --live --provider=anthropic
 *   php tools/edu_hammer_convergent_test.php --live --only=5,6,7
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/backend/Services/edu/Agents/Hammer.php';

use Services\Edu\Agents\Hammer;

$useLive = in_array('--live', $argv, true);
$provider = 'openai';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--provider=')) {
        $provider = substr($arg, 11);
    }
}
$onlyFilter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $onlyFilter = array_map('intval', explode(',', substr($arg, 7)));
    }
}

echo "=== GIST EDU Hammer Convergent 격리 테스트 (Phase 2a) ===\n";
echo 'mode: ' . ($useLive ? "LIVE ({$provider})" : 'MOCK (키워드 분류)') . "\n";
echo "isolation: Hammer.php only — chat.php 미호출\n\n";

class MockLLMClient
{
    public function chat(string $system, array $messages, int $maxTokens = 400, float $temp = 0.7): array
    {
        $user = $messages[0]['content'] ?? '';
        $quoted = '';
        if (preg_match('/"([^"]+)"/', $user, $m)) {
            $quoted = $m[1];
        }

        $content = "네가 말한 \"{$quoted}\"는 흥미로운 근거야.\n";
        $content .= "같은 결론이어도 전문가들은 '왜'를 다르게 봐.\n";
        if (preg_match('/pivot_question:\s*(.+)$/m', $system, $pm)) {
            $content .= trim($pm[1]);
        }

        return ['content' => $content];
    }

    public function haiku(string $system, array $messages, int $maxTokens = 100): array
    {
        $user = $messages[0]['content'] ?? '';

        if (str_contains($user, '폭격') || str_contains($user, '미사일')) {
            return ['content' => '{"axis_id":"tech","scores":{"tech":0.9,"politics":0.1,"structure":0.2},"confidence":"high","cue":"폭격"}'];
        }
        if (str_contains($user, '정권') || str_contains($user, '트럼프') || str_contains($user, '바이든')) {
            return ['content' => '{"axis_id":"politics","scores":{"tech":0.1,"politics":0.85,"structure":0.2},"confidence":"high","cue":"정권"}'];
        }
        if (str_contains($user, '역사') || str_contains($user, '원래')) {
            return ['content' => '{"axis_id":"structure","scores":{"tech":0.1,"politics":0.1,"structure":0.9},"confidence":"high","cue":"역사"}'];
        }
        if (str_contains($user, '끝내') || str_contains($user, '마음대로')) {
            return ['content' => '{"axis_id":"politics","scores":{"tech":0.2,"politics":0.75,"structure":0.5},"confidence":"high","cue":"끝내"}'];
        }
        if (str_contains($user, '때리') || str_contains($user, '버티')) {
            return ['content' => '{"axis_id":"tech","scores":{"tech":0.8,"politics":0.1,"structure":0.4},"confidence":"high","cue":"때리"}'];
        }
        if (str_contains($user, '의지')) {
            return ['content' => '{"axis_id":"tech","scores":{"tech":0.7,"politics":0.1,"structure":0.55},"confidence":"medium","cue":"의지"}'];
        }

        return ['content' => '{"axis_id":null,"scores":{"tech":0.3,"politics":0.3,"structure":0.3},"confidence":"low","cue":""}'];
    }
}

$iranQuest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'pro_line' => '기술적 관점: 정밀타격 기술의 한계가 정치적 결말을 막는다 (Freedman)',
    'con_line' => '구조적 관점: 불안정한 봉합이 전쟁의 귀결이다 (Rose)',
    'hammer_hints' => [
        'mode' => 'convergent',
        'shared_conclusion' => '이란 전쟁은 깔끔하게 끝나지 않는다',
        'axes' => [
            [
                'axis_id' => 'tech',
                'axis_label' => '기술적 한계',
                'thesis' => '첨단 정밀타격과 AI 작전으로 전쟁을 짧게 끝낼 수는 있어도 원하는 정치적 결과는 얻지 못한다.',
                'author' => '로런스 프리드먼',
                'news_id' => 555,
                'contrast_prompt' => [
                    'names_axis' => '무기와 기술의 한계 — 아무리 정밀해도 정치적 결말은 못 얻는다',
                    'distinguishes_from' => [
                        'politics' => '이건 정치인 탓이 아니야. 프리드먼은 누가 집권하든 기술의 한계는 같다고 봐',
                        'structure' => '이건 "전쟁은 원래 그래"가 아니야. 프리드먼은 이란이 특히 강해서라고 봐',
                    ],
                    'pivot_question' => '네 근거는 "무기의 한계" 때문이야, 아니면 "이란이라는 상대"가 특별해서야?',
                ],
            ],
            [
                'axis_id' => 'politics',
                'axis_label' => '국내정치 함정',
                'thesis' => '전쟁에서 이겨도 국내정치 때문에 지속 가능한 질서를 못 만든다.',
                'author' => '이코노미스트',
                'news_id' => 422,
                'contrast_prompt' => [
                    'names_axis' => '미국 국내정치의 함정 — 이기고도 지속 가능한 질서를 못 만든다',
                    'distinguishes_from' => [
                        'tech' => '이건 무기 문제가 아니야. 이코노미스트는 기술이 아무리 좋아도 정권 교체 때문에 장기 전략이 안 된다고 봐',
                        'structure' => '이건 "전쟁의 본질"이 아니야. 이코노미스트는 미국 정치가 특히 문제라고 봐',
                    ],
                    'pivot_question' => '네 근거는 "미국 정치가 특히 불안정해서"야, 아니면 "어느 나라든 정치는 다 그래서"야?',
                ],
            ],
            [
                'axis_id' => 'structure',
                'axis_label' => '전쟁의 구조',
                'thesis' => '완전 해결은 불가능하고, 불안정한 봉합으로 끝나는 게 현대 전쟁의 구조적 귀결이다.',
                'author' => '기데온 로즈',
                'news_id' => 528,
                'contrast_prompt' => [
                    'names_axis' => '전쟁이라는 것 자체의 구조 — 상대가 누구든, 무기가 뭐든, 전쟁은 원래 깔끔히 안 끝난다',
                    'distinguishes_from' => [
                        'tech' => '이건 무기 탓이 아니야. 로즈는 무기 얘기를 아예 안 해. 기술이 발전해도 마찬가지라고 봐',
                        'politics' => '이건 정치인 탓이 아니야. 로즈는 누가 집권하든 전쟁 구조는 같다고 봐',
                    ],
                    'pivot_question' => '네 근거는 "이란이 특별해서"야, 아니면 "전쟁이라는 게 원래 그래서"야?',
                ],
            ],
        ],
        'fallback_adversarial' => [
            'pro' => '외교적 해결 가능론',
            'con' => '군사적 해결 가능론',
        ],
        'counter_map' => [
            'tech' => 'structure',
            'politics' => 'tech',
            'structure' => 'politics',
        ],
    ],
];

$studentInputs = [
    [
        'id' => 1,
        'tier' => 'baseline',
        'label' => '기술/무기 (키워드 명확)',
        'reason' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.',
        'expected_axis' => 'tech',
    ],
    [
        'id' => 2,
        'tier' => 'baseline',
        'label' => '정치 (키워드 명확)',
        'reason' => '트럼프가 시작해도 바이든이 정권 잡으면 방향이 바뀌니까 일관성이 없어요.',
        'expected_axis' => 'politics',
    ],
    [
        'id' => 3,
        'tier' => 'baseline',
        'label' => '구조/역사 (키워드 명확)',
        'reason' => '역사적으로 보면 전쟁은 원래 깔끔하게 안 끝나요. 베트남도 그랬고.',
        'expected_axis' => 'structure',
    ],
    [
        'id' => 4,
        'tier' => 'safety',
        'label' => '애매함 → 메타 질문 기대',
        'reason' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.',
        'expected_axis' => 'meta_ask',
    ],
    [
        'id' => 5,
        'tier' => 'boundary',
        'label' => '경계선: 의지/체제 (tech vs structure)',
        'reason' => '이란 사람들은 자기 나라를 지키려는 의지가 너무 강해서 어떤 공격에도 버틸 거다.',
        'expected_axis' => 'tech|structure',
        'mirror_phrases' => ['의지', '강하', '버틸'],
    ],
    [
        'id' => 6,
        'tier' => 'boundary',
        'label' => '경계선: 시작 vs 끝 (politics vs structure)',
        'reason' => '미국이 전쟁을 시작하긴 쉬워도 끝내는 건 마음대로 안 된다.',
        'expected_axis' => 'politics|structure',
        'mirror_phrases' => ['시작', '끝내', '마음대로'],
    ],
    [
        'id' => 7,
        'tier' => 'boundary',
        'label' => '경계선: 자연어, 키워드 없음',
        'reason' => '결국 상대가 얼마나 버티느냐가 문제지, 미국이 얼마나 세게 때리느냐만으로는 안 끝나.',
        'expected_axis' => 'tech|structure',
        'mirror_phrases' => ['버티', '때리'],
    ],
];

if ($useLive) {
    if ($provider === 'anthropic') {
        $eduKey = getenv('EDU_ANTHROPIC_API_KEY') ?: getenv('ANTHROPIC_API_KEY');
        if (empty($eduKey)) {
            fwrite(STDERR, "ERROR: EDU_ANTHROPIC_API_KEY 또는 ANTHROPIC_API_KEY 필요\n");
            exit(1);
        }
        putenv('EDU_LLM_PROVIDER=anthropic');
        $llm = eduLlm();
        echo "provider: anthropic\n";
        echo 'remaining_calls_today: ' . $llm->getRemainingCalls() . "\n\n";
    } else {
        $openaiKey = getenv('OPENAI_API_KEY');
        if (empty($openaiKey)) {
            fwrite(STDERR, "ERROR: OPENAI_API_KEY 필요 (.env에 이미 있음)\n");
            exit(1);
        }
        putenv('EDU_LLM_PROVIDER=openai');
        $llm = eduLlm();
        echo "provider: openai\n";
        if ($llm instanceof EduOpenAILlmClient) {
            echo 'hammer_model: ' . $llm->getModel() . "\n";
            echo 'classify_model: ' . $llm->getFastModel() . "\n";
        }
        echo 'remaining_calls_today: ' . $llm->getRemainingCalls() . "\n\n";
    }
} else {
    $llm = new MockLLMClient();
}

$hammer = new Hammer($llm);
$stats = [
    'total' => 0,
    'meta_ask' => 0,
    'mirror_hit' => 0,
    'label_leak' => 0,
    'boundary_meta' => 0,
    'boundary_tech_politics' => 0,
    'boundary_structure_fail' => 0,
    'baseline_pass' => 0,
    'safety_meta' => false,
    'verdict_pass' => true,
];
$results = [];

foreach ($studentInputs as $input) {
    if ($onlyFilter !== null && !in_array($input['id'], $onlyFilter, true)) {
        continue;
    }

    $stats['total']++;

    echo str_repeat('=', 70) . "\n";
    echo "[{$input['id']}] {$input['label']} ({$input['tier']})\n";
    echo str_repeat('=', 70) . "\n";
    echo "학생: \"{$input['reason']}\"\n";
    if (isset($input['expected_axis'])) {
        echo "기대 축: {$input['expected_axis']}\n";
    }
    echo "\n";

    $result = $hammer->strike('pro', $input['reason'], $iranQuest, 'medium');

    $mode = $result['mode'] ?? 'unknown';
    echo "--- 분류/라우팅 ---\n";
    echo "mode: {$mode}\n";
    if (!empty($result['classification_scores'])) {
        $sc = $result['classification_scores'];
        echo 'scores: tech=' . ($sc['tech'] ?? '-') . ' politics=' . ($sc['politics'] ?? '-') . ' structure=' . ($sc['structure'] ?? '-') . "\n";
    }
    if (!empty($result['classification_cue'])) {
        echo 'cue: ' . $result['classification_cue'] . "\n";
    }
    if (isset($result['margin_gate'])) {
        echo 'margin_gate: ' . ($result['margin_gate'] ? 'YES' : 'no');
        if (!empty($result['margin_gate_reason'])) {
            echo ' (' . $result['margin_gate_reason'] . ')';
        }
        echo "\n";
    }
    if (isset($result['student_axis'])) {
        echo "student_axis: {$result['student_axis']}\n";
    }
    if (isset($result['counter_axis'])) {
        echo "counter_axis: {$result['counter_axis']}\n";
    }
    if (isset($result['pivot_question'])) {
        echo "pivot_question: {$result['pivot_question']}\n";
    }
    if (!empty($result['error'])) {
        echo "error: {$result['error']}\n";
    }

    $output = $result['counter_argument'] ?? '';
    echo "\n--- HAMMER 출력 ---\n{$output}\n";

    if ($mode === 'convergent_meta_ask') {
        $stats['meta_ask']++;
    }

    $mirrorPhrases = $input['mirror_phrases'] ?? extractKeyPhrases($input['reason']);
    $mirrorHit = checkMirror($output, $mirrorPhrases);
    $labelLeak = checkLabelLeak($output);

    echo "\n--- 자동 체크 (참고용) ---\n";
    echo '거울 인용: ' . ($mirrorHit ? 'O (학생 표현 반영)' : 'X (일반론/라벨만?)') . "\n";
    echo '라벨 누출: ' . ($labelLeak ? 'X (tech/politics 라벨 노출)' : 'O') . "\n";

    if ($mirrorHit) {
        $stats['mirror_hit']++;
    }
    if ($labelLeak) {
        $stats['label_leak']++;
    }

    $verdict = evaluateInputVerdict($input, $result);
    echo "\n--- 판정 ---\n";
    echo ($verdict['pass'] ? 'PASS' : 'FAIL') . ': ' . $verdict['reason'] . "\n";
    if (!$verdict['pass']) {
        $stats['verdict_pass'] = false;
    }
    if ($input['tier'] === 'baseline' && $verdict['pass']) {
        $stats['baseline_pass']++;
    }
    if ($input['id'] === 4 && $mode === 'convergent_meta_ask') {
        $stats['safety_meta'] = true;
    }
    if ($input['tier'] === 'boundary') {
        if ($mode === 'convergent_meta_ask') {
            $stats['boundary_meta']++;
        } elseif (in_array($result['student_axis'] ?? '', ['tech', 'politics'], true)) {
            $stats['boundary_tech_politics']++;
        }
        if (($result['student_axis'] ?? '') === 'structure') {
            $stats['boundary_structure_fail']++;
        }
    }

    $results[] = ['id' => $input['id'], 'verdict' => $verdict];

    echo "\n";
}

echo str_repeat('=', 70) . "\n";
echo "=== 요약 ===\n";
echo "총 {$stats['total']}건 | 메타질문 {$stats['meta_ask']}건 | 거울인용 {$stats['mirror_hit']}/{$stats['total']} | 라벨누출 {$stats['label_leak']}건\n";
echo "경계선(#5-7): tech/politics 명확 {$stats['boundary_tech_politics']}건 | meta {$stats['boundary_meta']}건 | structure단독 {$stats['boundary_structure_fail']}건\n\n";

$aggregate = evaluateAggregateVerdict($stats, $onlyFilter);
echo "=== Phase 2a 마감 판정 ===\n";
echo ($aggregate['pass'] ? 'PASS' : 'FAIL') . ': ' . $aggregate['reason'] . "\n";

function evaluateInputVerdict(array $input, array $result): array
{
    $mode = $result['mode'] ?? '';
    $axis = $result['student_axis'] ?? null;
    $expected = $input['expected_axis'] ?? '';

    if ($expected === 'meta_ask') {
        if ($mode === 'convergent_meta_ask') {
            return ['pass' => true, 'reason' => 'meta_ask 기대 충족'];
        }
        return ['pass' => false, 'reason' => 'meta_ask 기대했으나 ' . $mode];
    }

    if ($expected === 'tech' || $expected === 'politics' || $expected === 'structure') {
        if ($mode === 'convergent' && $axis === $expected) {
            return ['pass' => true, 'reason' => "{$expected} 명확 분류"];
        }
        return ['pass' => false, 'reason' => "기대 {$expected}, 실제 " . ($axis ?? $mode)];
    }

    if (str_contains($expected, '|')) {
        if ($axis === 'structure') {
            return ['pass' => false, 'reason' => 'structure 단독 = 실패'];
        }
        if ($mode === 'convergent' && in_array($axis, ['tech', 'politics', 'structure'], true)) {
            return ['pass' => true, 'reason' => "경계선 분류: {$axis}"];
        }
        if ($mode === 'convergent_meta_ask') {
            return ['pass' => true, 'reason' => '경계선 meta_ask (집계에서 ≤1 확인)'];
        }
        return ['pass' => false, 'reason' => '분류 실패'];
    }

    return ['pass' => true, 'reason' => 'n/a'];
}

function evaluateAggregateVerdict(array $stats, ?array $onlyFilter): array
{
    if ($onlyFilter !== null && $onlyFilter !== [5, 6, 7]) {
        return ['pass' => true, 'reason' => '부분 실행 — 전체 #1~7로 재실행 권장'];
    }

    if ($stats['total'] < 7) {
        return ['pass' => false, 'reason' => '#1~7 전체 실행 필요 (현재 ' . $stats['total'] . '건)'];
    }

    if ($stats['baseline_pass'] < 3) {
        return ['pass' => false, 'reason' => '#1~3 baseline 미통과'];
    }

    if ($stats['boundary_structure_fail'] > 0) {
        return ['pass' => false, 'reason' => '경계선 structure 단독 ' . $stats['boundary_structure_fail'] . '건'];
    }

    if ($stats['boundary_meta'] > 1) {
        return ['pass' => false, 'reason' => '경계선 meta_ask ' . $stats['boundary_meta'] . '건 (>1)'];
    }

    if ($stats['boundary_tech_politics'] < 2) {
        return ['pass' => false, 'reason' => '경계선 tech/politics 명확 분류 ' . $stats['boundary_tech_politics'] . '건 (<2)'];
    }

    if (!$stats['safety_meta']) {
        return ['pass' => false, 'reason' => '#4 애매 입력이 meta_ask로 가지 않음'];
    }

    return ['pass' => true, 'reason' => '시험1 통과 — Phase 2b 진입 가능'];
}

function extractKeyPhrases(string $reason): array
{
    $phrases = [];
    if (preg_match_all('/[가-힣]{2,}/u', $reason, $m)) {
        $stop = ['이란', '전쟁', '그냥', '것', '같아', '거다', '거예', '해서', '이라서'];
        foreach ($m[0] as $word) {
            if (!in_array($word, $stop, true) && mb_strlen($word) >= 2) {
                $phrases[] = $word;
            }
        }
    }
    return array_slice(array_unique($phrases), 0, 4);
}

function checkMirror(string $output, array $phrases): bool
{
    if ($phrases === []) {
        return false;
    }
    $hit = 0;
    foreach ($phrases as $p) {
        if (mb_strpos($output, $p) !== false) {
            $hit++;
        }
    }
    return $hit >= 1;
}

function checkLabelLeak(string $output): bool
{
    $leaks = ['tech', 'politics', 'structure', '기술적 관점', '국내정치 함정', '전쟁의 구조', '기술적 한계'];
    foreach ($leaks as $l) {
        if (stripos($output, $l) !== false) {
            return true;
        }
    }
    return false;
}
