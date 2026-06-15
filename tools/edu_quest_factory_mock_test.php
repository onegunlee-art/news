<?php
/**
 * GIST EDU — EduQuestFactory 수렴형 추출 Mock 테스트 (MySQL 불필요)
 *
 * pdo_mysql 없는 로컬 환경에서 extractConvergentAxes 검증
 *
 * Usage:
 *   php tools/edu_quest_factory_mock_test.php
 *   php tools/edu_quest_factory_mock_test.php --live
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/agents/autoload.php';

eduLoadAgents();

use Services\Edu\EduQuestFactory;

$useLive = in_array('--live', $argv ?? [], true);

echo "=== EduQuestFactory Mock Extract Test ===\n";
echo 'mode: ' . ($useLive ? 'LIVE OpenAI' : 'SKIP (no --live)') . "\n\n";

$mockArticles = [
    [
        'news_id' => 555,
        'title' => '이란과 영원한 전쟁의 함정',
        'why_important' => '첨단 정밀타격으로도 원하는 정치적 결과를 얻지 못한다. 체제 생존 의지가 강한 이란은 군사적 열세에서도 협상력을 유지한다.',
        'judgement_thesis' => '정밀타격 기술의 한계가 정치적 결말을 막는다',
    ],
    [
        'news_id' => 422,
        'title' => '끝나지 않는 전쟁의 높은 대가',
        'why_important' => '전쟁에서 이겨도 국내정치 때문에 지속 가능한 질서를 못 만든다. 미국 내 여론과 정권 교체가 장기 전략을 불가능하게 한다.',
        'judgement_thesis' => '미국 국내정치가 장기 전쟁 전략을 불가능하게 한다',
    ],
    [
        'news_id' => 528,
        'title' => '이란은 베트남처럼, 우크라이나는 한국처럼',
        'why_important' => '완전 해결은 불가능하고 불안정한 봉합으로 끝나는 게 현대 전쟁의 구조적 귀결이다.',
        'judgement_thesis' => '전쟁은 원래 깔끔하게 끝나지 않는 구조적 패턴이다',
    ],
];

if (!$useLive) {
    echo "Mock articles loaded (3건). --live 플래그로 LLM 추출 실행.\n";
    echo "articles:\n";
    foreach ($mockArticles as $a) {
        echo "  - [{$a['news_id']}] {$a['title']}\n";
    }
    echo "\n=== SKIP (use --live for LLM test) ===\n";
    exit(0);
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

// PDO stub — extractConvergentAxes는 PDO 미사용
$pdoStub = new class extends PDO {
    public function __construct()
    {
    }
};

$llm = eduLlm();
$factory = new EduQuestFactory($pdoStub, $supabase, $llm);

$ref = new ReflectionClass($factory);
$extract = $ref->getMethod('extractConvergentAxes');
$extract->setAccessible(true);
$normalize = $ref->getMethod('normalizeConvergentAxes');
$normalize->setAccessible(true);

echo "LLM extractConvergentAxes 호출 중...\n";
$data = $extract->invoke($factory, $mockArticles);

if ($data === null) {
    echo "FAIL: LLM 응답 파싱 실패\n";
    exit(1);
}

$mode = $data['mode'] ?? 'unknown';
echo "mode: {$mode}\n";

if ($mode !== 'convergent') {
    echo "reason: " . ($data['reason'] ?? 'n/a') . "\n";
    echo "WARN: convergent 아님 — 기사 묶음이 수렴형 조건 미충족일 수 있음\n";
    exit(2);
}

$axes = $normalize->invoke($factory, $data['axes'] ?? [], $mockArticles);
echo "shared_conclusion: " . ($data['shared_conclusion'] ?? '') . "\n";
echo "normalized_axes: " . count($axes) . "\n";
foreach ($axes as $ax) {
    echo "  - {$ax['axis_id']}: {$ax['axis_label']} (news_id={$ax['news_id']})\n";
}

$ok = count($axes) >= 2;
echo "\n=== " . ($ok ? 'PASS' : 'FAIL') . " ===\n";
exit($ok ? 0 : 1);
