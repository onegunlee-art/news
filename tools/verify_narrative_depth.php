<?php
/**
 * Narrative Depth Contract — golden sample / regression checks (CLI)
 *
 * Usage: php tools/verify_narrative_depth.php
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__) . '/';
require_once $projectRoot . 'src/agents/autoload.php';
require_once $projectRoot . 'src/backend/autoload.php';

use App\Services\NarrativeDepthService;

$configPath = $projectRoot . 'config/narrative_depth.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "FAIL: config/narrative_depth.php missing\n");
    exit(1);
}
$config = require $configPath;
$depth = new NarrativeDepthService();

$pass = 0;
$fail = 0;

function check(bool $ok, string $label): void
{
    global $pass, $fail;
    if ($ok) {
        echo "OK  {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

// ── Unit: depth scoring with mock payloads ──

$shallowScqa = [
    'synthesis_narrative' => '짧은 요약.',
    'executive_summary' => '한 줄.',
    'situation' => ['narrative' => '짧음'],
    'complication' => ['narrative_collisions' => [['view_a' => 'a', 'view_b' => 'b', 'collision' => 'c']]],
    'answer' => ['implication' => '짧음'],
];
$shallowResult = $depth->scoreScqaDepth($shallowScqa);
check($shallowResult['passed'] === false, 'shallow SCQA should fail depth gate');

$deepSyn = str_repeat('검색 수준의 깊이 있는 분석입니다. ', 80);
$deepScqa = [
    'synthesis_narrative' => "첫 문단.\n\n둘째 문단.\n\n셋째 문단.\n\n" . $deepSyn,
    'executive_summary' => str_repeat('경영진 요약 문장. ', 40),
    'situation' => ['narrative' => "문단1.\n\n문단2.\n\n문단3.\n\n" . str_repeat('상황 서술. ', 120)],
    'complication' => [
        'narrative_collisions' => [[
            'view_a' => str_repeat('관점 A 서술. ', 25),
            'view_b' => str_repeat('관점 B 서술. ', 25),
            'collision' => str_repeat('충돌 지점. ', 25),
        ]],
    ],
    'answer' => ['implication' => str_repeat('시사점. ', 25)],
];
$deepResult = $depth->scoreScqaDepth($deepScqa);
check($deepResult['passed'] === true, 'deep mock SCQA should pass depth gate');

$shallowGist = [
    'synthesis_narrative' => '짧음',
    'macro_so_what' => '짧음',
    'clusters' => [['narrative' => '짧은 클러스터', 'perspectives' => [['viewpoint' => 'a'], ['viewpoint' => 'b']]]],
];
check($depth->scoreGistDepth($shallowGist)['passed'] === false, 'shallow gist should fail depth gate');

$deepGist = [
    'synthesis_narrative' => "첫.\n\n둘.\n\n셋.\n\n" . str_repeat('주간 종합. ', 200),
    'macro_so_what' => str_repeat('매크로 so what. ', 25),
    'clusters' => [[
        'narrative' => "p1.\n\np2.\n\np3.\n\n" . str_repeat('클러스터 서술. ', 80),
        'perspectives' => [['viewpoint' => 'a'], ['viewpoint' => 'b']],
    ]],
];
check($depth->scoreGistDepth($deepGist)['passed'] === true, 'deep mock gist should pass depth gate');

// ── Config sanity ──
check(($config['min_chars']['synthesis_narrative'] ?? 0) >= 1000, 'synthesis min_chars >= 1000');
check(($config['min_chars']['cluster_narrative'] ?? 0) >= 500, 'cluster min_chars >= 500');

echo "\n--- Manual golden checklist (Admin / production) ---\n";
echo "1. Search: 클러스터 분석 → 3단 평문, 충분한 분량 (고객 /search)\n";
echo "2. Strategic: Admin → 레포트 생성 → synthesis_narrative ≥ {$config['min_chars']['synthesis_narrative']}자\n";
echo "3. Weekly: Admin → 위클리 Gist 생성 → cluster narrative ≥ {$config['min_chars']['cluster_narrative']}자\n";
echo "4. Strategic detail: verification.depth_score / depth_passed UI 표시 확인\n";
echo "5. Weekly: 저장된 리포트 → 편집 → update_gist 저장 확인\n";

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
