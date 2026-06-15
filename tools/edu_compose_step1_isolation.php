<?php
/**
 * GIST EDU — Step1 격리 테스트 (실패 세션 replay)
 * STRUCTURE_MAX_TOKENS=2000 + retry 적용 후 previewStructure → compose E2E
 *
 * Usage: php tools/edu_compose_step1_isolation.php [session_id]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduRagService;
use Services\Edu\GistNarrationReader;

final class Step1IsolationRag extends EduRagService
{
    public function __construct()
    {
    }

    public function findArcArticles(array $newsIds, string $query = ''): array
    {
        return [
            'alignment' => '이란 전쟁은 미국이 원하는 방식으로 깔끔하게 끝나기 어렵다.',
            'conflict' => $query,
            'articles' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getWritingPatterns(string $title, int $limit = 3): array
    {
        return [['pattern' => '갈등 인정 후 학생 시각 수렴', 'example' => '~거든요 해설체']];
    }

    /** @return list<array<string, mixed>> */
    public function getJudgementPatterns(int $limit = 3): array
    {
        return [['pattern' => '다른 시각을 듣고도 입장 유지', 'example' => '하지만 저는 ~']];
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

final class Step1IsolationNarration extends GistNarrationReader
{
    public function readExcerpts(array $newsIds, int $maxCharsPerArticle = 400): array
    {
        return [
            [
                'news_id' => 528,
                'title' => '이란은 베트남처럼, 우크라이나는 한국처럼',
                'excerpt' => '베트남전은 미국의 정치적 계산이 현지 국민 반감을 키우며 오래 이어졌다.',
            ],
        ];
    }
}

$sessionId = $argv[1] ?? '852bfa06-084c-465e-ac02-91d6ef4fd7d6';

$supabase = eduSupabase();
$session = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1)[0] ?? null;
if ($session === null) {
    fwrite(STDERR, "session not found: {$sessionId}\n");
    exit(1);
}

$blueprint = eduLoadBlueprint($session);
$dialogue = eduLoadDialogue($session);
$quest = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1)[0] ?? [];
$quest['articles'] = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $session['quest_id'], 20) ?? [];

$structConst = (new ReflectionClass(GistStyleComposer::class))->getConstant('STRUCTURE_MAX_TOKENS');

echo "=== Step1 isolation test ===\n";
echo "session: {$sessionId}\n";
echo 'STRUCTURE_MAX_TOKENS=' . ($structConst ?: '?') . "\n";
echo 'stance=' . ($blueprint['stance'] ?? '') . ' phase=' . ($blueprint['phase'] ?? '') . "\n";
echo 'ready_for_compose=' . (!empty($blueprint['ready_for_compose']) ? 'Y' : 'N') . "\n";
echo 'essay_structure_saved=' . (empty($blueprint['essay_structure']['sections']) ? 'NO' : 'YES') . "\n";
echo 'reason: ' . ($blueprint['reason'] ?? '') . "\n";
echo 'evidence: ' . mb_substr((string) ($blueprint['evidence'] ?? ''), 0, 120) . "\n\n";

$llm = eduLlm();
$composer = new GistStyleComposer($llm, new Step1IsolationRag(), new Step1IsolationNarration());

echo ">>> previewStructure (Step1)\n";
$t0 = microtime(true);
$structure = $composer->previewStructure($blueprint, $quest, $dialogue);
$step1Sec = round(microtime(true) - $t0, 1);

if (!empty($structure['success']) && $structure['success'] === false) {
    echo "STEP1 FAIL: " . ($structure['message'] ?? 'unknown') . " ({$step1Sec}s)\n";
    echo json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "STEP1 OK ({$step1Sec}s)\n";
echo 'title: ' . ($structure['title'] ?? '') . "\n";
echo 'sections: ' . count($structure['sections'] ?? []) . "\n";
echo 'generated_by: ' . ($structure['generated_by'] ?? '?') . "\n\n";

$blueprint['essay_structure'] = $structure;

echo ">>> compose (Step2)\n";
$t1 = microtime(true);
$result = $composer->compose($blueprint, $quest, $dialogue);
$composeSec = round(microtime(true) - $t1, 1);

if (!empty($result['success']) && $result['success'] === false) {
    echo "COMPOSE FAIL: " . ($result['message'] ?? 'unknown') . " ({$composeSec}s)\n";
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$fullText = trim((string) ($result['full_text'] ?? ''));
if ($fullText === '') {
    echo "COMPOSE FAIL: empty full_text ({$composeSec}s)\n";
    exit(1);
}

echo "COMPOSE OK ({$composeSec}s)\n";
echo 'full_text_len: ' . mb_strlen($fullText) . "\n";
echo 'hero: ' . ($result['hero_sentence'] ?? '') . "\n\n";

$checks = [
    '여론 피로' => str_contains($fullText, '피로') || str_contains($fullText, '여론'),
    '베트남' => str_contains($fullText, '베트남'),
    'reflection 2인칭 미포함' => !str_contains($fullText, '너는 '),
];

echo "--- 체크리스트 ---\n";
$allOk = true;
foreach ($checks as $label => $hit) {
    $mark = $hit ? 'PASS' : 'FAIL';
    if (!$hit) {
        $allOk = false;
    }
    echo "[{$mark}] {$label}\n";
}

echo "\n========== 완성 글 앞 800자 ==========\n";
echo mb_substr($fullText, 0, 800) . "\n";
echo "========== END ==========\n";

exit($allOk ? 0 : 2);
