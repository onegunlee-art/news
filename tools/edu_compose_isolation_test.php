<?php
/**
 * GIST EDU — Compose 격리 E2E (이란 세션)
 * GistStyleComposer 실코드 + max=6000 + 학생 턴만 dialogue → 완성 글 전문 출력
 *
 * Usage: php tools/edu_compose_isolation_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduRagService;
use Services\Edu\GistNarrationReader;

/** MySQL 없이 프로덕션 유사 RAG/발췌 모의 */
final class ComposeIsolationRag extends EduRagService
{
    public function __construct()
    {
    }

    public function findArcArticles(array $newsIds, string $query = ''): array
    {
        return [
            'alignment' => '이란 전쟁은 미국이 원하는 방식으로 깔끔하게 끝나기 어렵다. 정밀타격이 군사적 성과를 내도 정치적 종결로 이어지지 않는다는 인식이 공통이다.',
            'conflict' => $query,
            'articles' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getWritingPatterns(string $title, int $limit = 3): array
    {
        return [
            ['pattern' => '갈등 인정 후 학생 시각으로 수렴', 'example' => '~거든요, ~있어요 해설체'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getJudgementPatterns(int $limit = 3): array
    {
        return [
            ['pattern' => '다른 시각을 듣고도 입장 유지', 'example' => '물론 ~ 시각도 있어요. 하지만 저는 ~'],
        ];
    }

    public function formatWritingPatterns(array $patterns): string
    {
        $lines = [];
        foreach ($patterns as $p) {
            $lines[] = ($p['pattern'] ?? '') . ' — ' . ($p['example'] ?? '');
        }
        return implode("\n", $lines);
    }
}

final class ComposeIsolationNarration extends GistNarrationReader
{
    public function readExcerpts(array $newsIds, int $maxCharsPerArticle = 400): array
    {
        return [
            [
                'news_id' => 555,
                'title' => '이란과 영원한 전쟁의 함정',
                'excerpt' => '정밀타격이 정치적 승리를 보장하지는 않는다. 전쟁은 현지 민심과 맞물리며 더 복잡해진다.',
            ],
            [
                'news_id' => 528,
                'title' => '이란은 베트남처럼, 우크라이나는 한국처럼',
                'excerpt' => '베트남전은 미국의 정치적 계산이 현지 국민 반감을 키우며 오래 이어졌다. 한국전은 열강의 세력 싸움으로 읽힌다.',
            ],
        ];
    }
}

$quest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'alignment_summary' => '세 명의 전문가 모두 "이란 전쟁은 미국이 원하는 대로 깔끔하게 끝나지 않는다"는 데 동의한다. 군사적 우위가 정치적 승리로 이어지지 않는다는 공통 인식.',
    'conflict_summary' => '공동 결론: 이란 전쟁은 깔끔하게 끝나지 않는다. 그러나 "왜" 안 끝나는지에 대한 이유가 다르다 — 기술의 한계인가, 국내정치의 함정인가, 전쟁 구조 자체의 문제인가.',
    'articles' => [
        ['news_id' => 555, 'role' => 'primary', 'title' => '이란과 영원한 전쟁의 함정'],
        ['news_id' => 422, 'role' => 'context', 'title' => '끝나지 않는 전쟁의 높은 대가'],
        ['news_id' => 528, 'role' => 'context', 'title' => '이란은 베트남처럼, 우크라이나는 한국처럼'],
    ],
];

$counterArgument = <<<'HAMMER'
네가 말한 **"이란 국민이 미국편에서 멀어지고 있다"**, 그리고 **"미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다"**는 포인트는 분명 설득력이 있어요. 다만 그 근거가 **이란이나 베트남처럼 특정 상대의 민심과 반응 때문에** 전쟁이 길어진다는 뜻인지, 아니면 그런 사례들을 통해 **전쟁은 원래 개입하는 쪽의 의도와 다르게 엉키면서 깔끔하게 끝나지 않는다는 점**을 말하고 싶은 건지 한 번 더 구분해 보면 좋겠어요.
HAMMER;

$blueprint = [
    'stance' => 'pro',
    'final_stance' => 'pro',
    'reason' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지',
    'evidence' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지. 기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아',
    'rebuttal' => '전쟁은 원래 의도와 상관없이 얽히는거 같아',
    'counter_argument' => $counterArgument,
    'reflection_lines' => [
        '너는 이란 민심 변화가 중요하다고 봤어',
        '너는 반론 뒤 전쟁의 복잡성을 더 생각했어',
        '너는 pro를 지키며 신념을 더 단단히 했어',
    ],
    'reflection_confirmed' => true,
];

$dialogue = [
    ['role' => 'assistant', 'content' => '정밀타격이 군사적 성과는 낼 수 있어도 정치적 결말까지 보장하진 않잖아...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.'],
    ['role' => 'assistant', 'content' => '좋아요, 그럼 굴복이 정치적 결정의 변화라면...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.'],
    ['role' => 'assistant', 'content' => '그 복잡함이 정밀타격으로는 해결하기 어렵다면...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지'],
    ['role' => 'assistant', 'content' => '기사에서 네 주장과 연결되는 근거를 2~3개 찾아보자...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지'],
    ['role' => 'assistant', 'content' => '좋아, 기사에서 본 구체적인 내용을 한 가지만 더...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아'],
    ['role' => 'assistant', 'content' => $counterArgument . "\n\n이 반론에 대해 어떻게 생각해?", 'agent' => 'hammer'],
    ['role' => 'student', 'content' => '전쟁은 원래 의도와 상관없이 얽히는거 같아'],
    ['role' => 'assistant', 'content' => "지금까지 생각을 정리해볼게:\n너는 이란 민심 변화가 중요하다고 봤어\n너는 반론 뒤 전쟁의 복잡성을 더 생각했어\n너는 pro를 지키며 신념을 더 단단히 했어\n\n맞게 정리됐어?", 'agent' => 'reflection'],
    ['role' => 'student', 'content' => '맞아'],
];

echo "=== Edu Compose 격리 E2E (이란 세션, ARTICLE_MAX_TOKENS=6000) ===\n\n";

$llm = eduLlm();
$composer = new GistStyleComposer($llm, new ComposeIsolationRag(), new ComposeIsolationNarration());

$ref = new ReflectionClass($composer);
$buildContext = $ref->getMethod('buildContext');
$buildContext->setAccessible(true);
$ctx = $buildContext->invoke($composer, $blueprint, $quest, $dialogue);

echo "--- buildContext dialogue_text (학생 턴만) ---\n";
echo $ctx['dialogue_text'];
echo "\n";

$t0 = microtime(true);
$result = $composer->compose($blueprint, $quest, $dialogue);
$elapsed = round(microtime(true) - $t0, 1);

if (!empty($result['success']) && $result['success'] === false) {
    echo "COMPOSE FAILED: " . ($result['message'] ?? 'unknown') . "\n";
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$fullText = trim((string) ($result['full_text'] ?? ''));
if ($fullText === '') {
    echo "COMPOSE FAILED: empty full_text\n";
    exit(1);
}

echo "--- 메타 ---\n";
echo 'elapsed_sec: ' . $elapsed . "\n";
echo 'title: ' . ($result['title'] ?? '') . "\n";
echo 'subtitle: ' . ($result['subtitle'] ?? '') . "\n";
echo 'hero: ' . ($result['hero_sentence'] ?? '') . "\n";
echo 'structure_generated_by: ' . ($result['essay_structure']['generated_by'] ?? '?') . "\n";
echo 'full_text_len: ' . mb_strlen($fullText) . "\n\n";

echo "========== 완성 글 전문 ==========\n\n";
echo $fullText . "\n\n";
echo "========== END ==========\n\n";

// 자동 체크리스트
$conflict = (string) ($quest['conflict_summary'] ?? '');
$checks = [
    '이란 민심' => str_contains($fullText, '민심') || str_contains($fullText, '국민'),
    '베트남 비유' => str_contains($fullText, '베트남'),
    'conflict_summary 원문 복붙' => $conflict !== '' && str_contains($fullText, $conflict),
    'Hammer 반론 전문 복붙' => str_contains($fullText, '한 번 더 구분해 보면 좋겠어요'),
    '코치 질문 문구' => str_contains($fullText, '이 반론에 대해 어떻게 생각해'),
    'reflection 2인칭' => str_contains($fullText, '너는 이란 민심'),
];

echo "--- 체크리스트 ---\n";
foreach ($checks as $label => $hit) {
    $mark = $hit ? 'YES' : 'no';
    echo "[{$mark}] {$label}\n";
}
