<?php
/**
 * P1-2g — GistStyleComposer coach_profile derive: legacy parity + buildContext golden
 *
 * Usage: php tools/edu_compose_coach_profile_parity_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduRagService;
use Services\Edu\GistNarrationReader;

final class ComposeParityRag extends EduRagService
{
    public function __construct()
    {
    }

    public function findArcArticles(array $newsIds, string $query = ''): array
    {
        return ['alignment' => 'test alignment', 'conflict' => $query, 'articles' => []];
    }

    /** @return list<array<string, mixed>> */
    public function getWritingPatterns(string $title, int $limit = 3): array
    {
        return [];
    }

    /** @return list<array<string, mixed>> */
    public function getJudgementPatterns(int $limit = 3): array
    {
        return [];
    }

    public function formatWritingPatterns(array $patterns): string
    {
        return '';
    }
}

final class ComposeParityNarration extends GistNarrationReader
{
    /** @return list<array<string, mixed>> */
    public function readExcerpts(array $newsIds, int $maxCharsPerArticle = 400): array
    {
        return [];
    }
}

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

/** @return array<string, mixed> */
function composeContext(array $quest, array $blueprint = []): array
{
    static $composer = null;
    if ($composer === null) {
        $composer = new GistStyleComposer(new stdClass(), new ComposeParityRag(), new ComposeParityNarration());
    }
    $ref = new ReflectionClass($composer);
    $method = $ref->getMethod('buildContext');
    $method->setAccessible(true);

    return $method->invoke($composer, $blueprint, $quest, []);
}

echo "=== GistStyleComposer coach_profile parity (P1-2g) ===\n\n";

$fixtures = [
    'japan' => eduG09DecQuestFixture(),
    'iran_dec' => eduIranDecQuestFixture(),
    'nuke' => eduNuke630QuestFixture(),
    'iran_minimal' => [
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'pro_line' => 'pro',
        'con_line' => 'con',
        'hammer_hints' => ['quest_frame' => 'decision_inquiry', 'mode' => 'convergent'],
        'articles' => [],
    ],
    'empty_frame' => [
        'quest_code' => 'Q-EMPTY',
        'pro_line' => '찬성',
        'con_line' => '반대',
        'hammer_hints' => [],
        'articles' => [],
    ],
    'adversarial' => [
        'quest_code' => 'Q-ADV',
        'pro_line' => '찬성 라인',
        'con_line' => '반대 라인',
        'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => 'adversarial'],
        'articles' => [],
    ],
];

foreach ($fixtures as $name => $quest) {
    $legacy = (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'decision_inquiry';
    $derived = eduQuestCoachProfile($quest) === 'decision';
    ok("{$name} legacy ↔ coach_profile decision", $legacy === $derived);
}

echo "\n--- buildContext golden (deterministic, LLM/RAG mocked) ---\n";

$japanCtx = composeContext($fixtures['japan'], ['stance' => 'con', 'final_stance' => 'con', 'student_axis' => 'tech']);
ok('japan is_decision_inquiry', ($japanCtx['is_decision_inquiry'] ?? false) === true);
ok('japan stance_label exact', ($japanCtx['stance_label'] ?? '') === '그 결정이 너무 과하거나 위험하다고 본 입장');
ok('japan perspective_label exact', ($japanCtx['perspective_label'] ?? '') === '주변 나라·대만 위험');

$iranCtx = composeContext($fixtures['iran_dec'], ['stance' => 'con', 'final_stance' => 'con']);
ok('iran_dec is_decision_inquiry', ($iranCtx['is_decision_inquiry'] ?? false) === true);
ok('iran_dec stance_label exact', ($iranCtx['stance_label'] ?? '') === '군대를 보내거나, 다른 방법이 나았다고 본 입장');

$nukeCtx = composeContext($fixtures['nuke'], ['stance' => 'pro', 'final_stance' => 'pro']);
ok('nuke not decision_inquiry', ($nukeCtx['is_decision_inquiry'] ?? false) === false);
ok('nuke stance_label adversarial', ($nukeCtx['stance_label'] ?? '') === '찬성');

$advCtx = composeContext($fixtures['adversarial'], ['stance' => 'pro', 'final_stance' => 'pro']);
ok('adversarial not decision_inquiry', ($advCtx['is_decision_inquiry'] ?? false) === false);
ok('adversarial stance_label', ($advCtx['stance_label'] ?? '') === '찬성');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
