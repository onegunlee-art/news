<?php
/**
 * GIST EDU — SocraticCoach 질문 난이도 격리 테스트
 * 이란 세션(Q-IRAN-FOREVER-001) 재현 → askWhy + refinePrompt 실제 출력
 *
 * Usage:
 *   php tools/edu_coach_isolation_test.php
 *   php tools/edu_coach_isolation_test.php --live
 *   php tools/edu_coach_isolation_test.php --live --extended
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

use Services\Edu\Agents\ConversationDirector;
use Services\Edu\Agents\SocraticCoach;

eduLoadAgents();

$useLive = in_array('--live', $argv ?? [], true);
$extended = in_array('--extended', $argv ?? [], true);

echo "=== SocraticCoach 질문 난이도 격리 (이란 세션) ===\n";
echo 'mode: ' . ($useLive ? 'LIVE OpenAI' : 'MOCK (실제 확인은 --live)') . "\n";
echo 'suite: ' . ($extended ? 'extended (깊이·adversarial 포함)' : 'basic') . "\n\n";

$iranQuest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'pro_line' => '기술적 관점: 정밀타격 기술의 한계가 정치적 결말을 막는다 (Freedman)',
    'con_line' => '구조적 관점: 불안정한 봉합이 전쟁의 귀검이다 (Rose)',
    'alignment_summary' => '세 명의 전문가 모두 "이란 전쟁은 미국이 원하는 대로 깔끔하게 끝나지 않는다"는 데 동의한다. 군사적 우위가 정치적 승리로 이어지지 않는다는 공통 인식.',
    'conflict_summary' => '공동 결론: 이란 전쟁은 깔끔하게 끝나지 않는다. 그러나 "왜" 안 끝나는지에 대한 이유가 다르다 — 기술의 한계인가, 국내정치의 함정인가, 전쟁 구조 자체의 문제인가.',
    'hammer_hints' => ['mode' => 'convergent'],
];

$adversarialQuest = [
    'quest_code' => 'Q-G01',
    'quest_title' => '정부는 AI 일자리 대란 전에 안전망을 깔아야 할까?',
    'pro_line' => '찬성: 일자리가 바뀌기 전에 정부가 미리 안전망·재교육을 깔아야 한다',
    'con_line' => '반대: 과도한 규제는 혁신을 막고, 시장이 알아서 조정해야 한다',
    'alignment_summary' => '전문가들은 AI가 일자리에 큰 변화를 줄 수 있다는 점에 동의한다. 다만 정부가 미리 개입해야 하는지, 시장에 맡겨야 하는지는 갈린다.',
    'conflict_summary' => '공통: AI가 일자리를 바꿀 수 있다. 대립: 선제적 안전망·규제 vs 혁신·시장 조정, 위험을 먼저 막을지 성장을 우선할지.',
    'hammer_hints' => [],
];

$previousSessionQuestions = [
    'S1 찬성 직후' => '정밀타격이 군사적 성과는 낼 수 있어도 정치적 결말까지 보장하진 않잖아. 그럼 네가 말하는 "끝낼 수 있다"는 건, 군사적으로 이기는 것과 정치적으로 마무리하는 걸 어떻게 구분하고 있어?',
    'S2 구체 답 후' => '좋아요, 그럼 굴복이 정치적 결정의 변화라면, 정밀타격만으로는 왜 그 변화를 못 이끌어내는지 한 가지 이유를 더 말해줄 수 있어?',
    'S3 vague 답 후' => '그 복잡함이 정밀타격으로는 해결하기 어렵다면, 군사적 성공을 불안정한 봉합으로 바꾸는 구조적 조건은 뭐라고 볼 수 있을까? 정치적 합의보다 반복적 충돌이 이어지는 이유는?',
];

$cases = [
    [
        'label' => 'S1 찬성(pro) 직후 — 첫 질문',
        'quest' => $iranQuest,
        'quest_type' => 'convergent',
        'stance' => 'pro',
        'reason' => '',
        'progress' => 10,
        'prev_key' => 'S1 찬성 직후',
    ],
    [
        'label' => 'S2 구체 답 후 — 후속 질문',
        'quest' => $iranQuest,
        'quest_type' => 'convergent',
        'stance' => 'pro',
        'reason' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.',
        'progress' => 25,
        'prev_key' => 'S2 구체 답 후',
    ],
    [
        'label' => 'S3 vague 답 후 — 후속 질문 (막힘 케이스)',
        'quest' => $iranQuest,
        'quest_type' => 'convergent',
        'stance' => 'pro',
        'reason' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.',
        'progress' => 25,
        'prev_key' => 'S3 vague 답 후',
    ],
];

if ($extended) {
    $cases = array_merge($cases, [
        [
            'label' => 'S4 반대(con) 직후 — 첫 질문',
            'quest' => $iranQuest,
            'quest_type' => 'convergent',
            'stance' => 'con',
            'reason' => '',
            'progress' => 10,
        ],
        [
            'label' => 'S5 구체 답(민심) 후 — 후속',
            'quest' => $iranQuest,
            'quest_type' => 'convergent',
            'stance' => 'pro',
            'reason' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지',
            'progress' => 25,
        ],
        [
            'label' => 'S6 엉뚱한 답 후 — 후속',
            'quest' => $iranQuest,
            'quest_type' => 'convergent',
            'stance' => 'pro',
            'reason' => '솔직히 잘 모르겠어요. 그냥 뉴스에서 봤어요.',
            'progress' => 25,
        ],
        [
            'label' => 'S7 짧은 답 후 — 후속',
            'quest' => $iranQuest,
            'quest_type' => 'convergent',
            'stance' => 'pro',
            'reason' => '음... 그냥요.',
            'progress' => 25,
        ],
        [
            'label' => 'A1 adversarial 찬성 직후 — 첫 질문 (mode 없음)',
            'quest' => $adversarialQuest,
            'quest_type' => 'adversarial',
            'stance' => 'pro',
            'reason' => '',
            'progress' => 10,
        ],
        [
            'label' => 'A2 adversarial vague 답 후',
            'quest' => $adversarialQuest,
            'quest_type' => 'adversarial',
            'stance' => 'con',
            'reason' => 'AI 좋은 거니까 규제 말자',
            'progress' => 25,
        ],
        [
            'label' => 'A3 adversarial 엉뚱한 답 후',
            'quest' => $adversarialQuest,
            'quest_type' => 'adversarial',
            'stance' => 'pro',
            'reason' => '친구 아빠가 실직해서 걱정돼요',
            'progress' => 25,
        ],
        [
            'label' => 'A4 adversarial 구체 답 후',
            'quest' => $adversarialQuest,
            'quest_type' => 'adversarial',
            'stance' => 'pro',
            'reason' => '일자리가 바뀌면 정부가 미리 재교육이랑 안전망을 깔아야 해요. 지금 실업이 없다고 해도 준비는 먼저 해야죠.',
            'progress' => 25,
        ],
    ]);
}

$jargonPatterns = [
    '구조적', '봉합', '결말', '귀결', '반복적 충돌', '정치적 합의',
    '군사적 성공', '정치적 결정', '패턴', '메커니즘', '함의',
];

function coachCheckQuestion(string $text, array $patterns): array
{
    $issues = [];
    $qCount = substr_count($text, '?') + mb_substr_count($text, '？');
    if ($qCount > 1) {
        $issues[] = "물음표 {$qCount}개 (1개만 허용)";
    }
    $sentences = preg_split('/[.!?？]\s*/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($sentences) > 2) {
        $issues[] = '문장 ' . count($sentences) . '개 (2문장 이내)';
    }
    foreach ($patterns as $word) {
        if (mb_strpos($text, $word) !== false) {
            $issues[] = "전문/추상어: {$word}";
        }
    }
    return $issues;
}

function coachEvaluateDepth($llm, array $quest, string $stance, string $studentAnswer, string $question): array
{
    $system = <<<PROMPT
중학생 소크라테스 코치 질문 품질 평가. JSON만:
{
  "ease_score": 1-5 (5=14세가 쉽게 이해),
  "depth_score": 1-5 (5=학생을 한 단계 더 깊이 끌어냄),
  "verdict": "easy_deep" | "easy_shallow" | "hard" | "mixed",
  "reason": "한 줄"
}
easy_shallow = "왜 그럴까?" 수준, 학생 답/퀘스트 맥락을 거의 안 짚음
easy_deep = 쉬운 말인데 학생 답의 핵심을 짚어 생각을 확장
PROMPT;

    $user = "퀘스트: {$quest['quest_title']}\n학생 입장: {$stance}\n";
    if ($studentAnswer !== '') {
        $user .= "학생 답: {$studentAnswer}\n";
    }
    $user .= "코치 질문: {$question}";

    $response = $llm->haiku($system, [['role' => 'user', 'content' => $user]], 200);
    if (!empty($response['error'])) {
        return ['verdict' => 'unknown', 'reason' => 'eval failed'];
    }
    if (preg_match('/\{[\s\S]*\}/', $response['content'] ?? '', $match)) {
        return json_decode($match[0], true) ?? ['verdict' => 'unknown', 'reason' => 'parse failed'];
    }
    return ['verdict' => 'unknown', 'reason' => 'no json'];
}

if ($useLive) {
    $llm = eduLlm();
    $coach = new SocraticCoach($llm);
    $director = new ConversationDirector($llm);
} else {
    $coach = new SocraticCoach(new class {
        public function chat(string $system, array $messages, int $maxTokens = 400, float $temp = 0.7): array
        {
            return ['success' => true, 'content' => '전쟁이 끝난 것 같은데 또 싸우게 되는 이유가 뭘까?'];
        }
        public function haiku(string $system, array $messages, int $maxTokens = 512): array
        {
            return ['success' => true, 'content' => '{}'];
        }
    });
    $director = new ConversationDirector(new class {
        public function haiku(string $system, array $messages, int $maxTokens = 512): array
        {
            return ['success' => true, 'content' => '{}'];
        }
    });
}

$pass = 0;
$fail = 0;
$shallow = 0;
$depthPass = 0;

foreach ($cases as $i => $case) {
    $n = $i + 1;
    $quest = $case['quest'];
    $questType = $case['quest_type'] ?? 'convergent';
    echo "--- Case {$n}: {$case['label']} ---\n";
    echo "퀘스트: {$quest['quest_code']} ({$questType})\n";
    if ($case['reason'] !== '') {
        echo "학생 답: \"{$case['reason']}\"\n";
    }

    if (!empty($case['prev_key'])) {
        $prev = $previousSessionQuestions[$case['prev_key']] ?? '';
        echo "\n[이전 세션 실제 질문]\n{$prev}\n";
        $prevIssues = coachCheckQuestion($prev, $jargonPatterns);
        echo '이전 검사: ' . (empty($prevIssues) ? 'OK' : implode(', ', $prevIssues)) . "\n";
    }

    $raw = $coach->askWhy($quest, $case['stance'], $case['reason']);
    $rawQuestion = trim((string) ($raw['question'] ?? ''));
    $final = $director->refinePrompt($rawQuestion, $quest, (int) $case['progress']);
    if ($final === '' || $final === $rawQuestion) {
        $final = $rawQuestion;
    }

    echo "\n[새 askWhy raw]\n{$rawQuestion}\n";
    echo "\n[학생에게 보이는 최종 질문]\n{$final}\n";

    $issues = coachCheckQuestion($final, $jargonPatterns);
    if (empty($issues)) {
        echo "\n형식: PASS";
        $pass++;
    } else {
        echo "\n형식: CHECK — " . implode(', ', $issues);
        $fail++;
    }

    if ($extended && $useLive) {
        $depth = coachEvaluateDepth($llm, $quest, $case['stance'], $case['reason'], $final);
        $verdict = (string) ($depth['verdict'] ?? 'unknown');
        $ease = (int) ($depth['ease_score'] ?? 0);
        $dScore = (int) ($depth['depth_score'] ?? 0);
        echo "\n깊이: {$verdict} (쉬움={$ease}, 깊이={$dScore}) — " . ($depth['reason'] ?? '');
        if ($verdict === 'easy_deep' || ($ease >= 4 && $dScore >= 3)) {
            $depthPass++;
        } elseif ($verdict === 'easy_shallow' || $dScore <= 2) {
            $shallow++;
        }
    }
    echo "\n\n";
}

echo "=== 요약: 형식 PASS {$pass} / CHECK {$fail} ===\n";
if ($extended && $useLive) {
    echo "=== 깊이: easy_deep+ {$depthPass} / shallow- {$shallow} / total " . count($cases) . " ===\n";
    echo "adversarial: SocraticCoach는 hammer_hints.mode 무관 공용 → 대립형도 동일 난이도 제약 적용 (의도: 14세 공통)\n";
}
if (!$useLive) {
    echo "실제 LLM 출력은: php tools/edu_coach_isolation_test.php --live --extended\n";
}
