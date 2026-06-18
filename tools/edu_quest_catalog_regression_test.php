<?php
/**
 * Quest catalog lib regression (no DB)
 *
 * Usage: php tools/edu_quest_catalog_regression_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';
require_once $root . '/src/backend/Services/edu/EduQuestFactory.php';

use Services\Edu\EduQuestFactory;

$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

echo "=== eduQuestCatalog regression ===\n\n";

assertTrue('iran arc category', eduQuestCategoryForArc('ARC-IRAN-REGION') === 'middle_east_iran');
assertTrue('japan arc category', eduQuestCategoryForArc('ARC-JAPAN-DEFENSE') === 'east_asia_security');
assertTrue('new dprk arc mapped', eduQuestCategoryForArc('ARC-DPRK-PENINSULA') === 'east_asia_security');
assertTrue('category label', eduQuestCategoryLabel('ai_tech') === 'AI·기술');

$score = eduQuestScoreArticle([
    'title' => 'AI 일자리와 청소년 교육',
    'category' => 'society',
    'topic_label' => 'AI 규제',
]);
assertTrue('quest score ready', ($score['total'] ?? 0) >= 12);
assertTrue('quest score safety', ($score['safety'] ?? '') === 'Y');

$arcs = eduQuestMatchArcsForArticle(
    ['title' => '북한 핵실험과 한반도', 'topic_label' => '', 'category' => 'security_conflict'],
    EduQuestFactory::arcTopicKeywords()
);
assertTrue('dprk keyword match', in_array('ARC-DPRK-PENINSULA', $arcs, true));

$defs = eduQuestCategoryDefinitions();
assertTrue('10 categories', count($defs) === 10);

$shelves = eduQuestShelfDefinitions();
assertTrue('6 student shelves', count($shelves) === 6);
assertTrue('war shelf maps iran', in_array('middle_east_iran', eduQuestCategoriesForShelf('war_security'), true));
assertTrue('shelf for category', eduQuestShelfForCategory('ai_tech') === 'ai_tech');

$lenses = eduQuestLensDefinitions();
assertTrue('8 lenses defined', count($lenses) >= 8);
assertTrue('lens label', eduQuestLensLabel('trump_consistency') !== 'trump_consistency');

$mockQuest = [
    'id' => 'test-id',
    'quest_code' => 'Q-TEST',
    'quest_title' => 'Test',
    'hammer_hints' => json_encode([]),
    'scores' => json_encode(['category' => 'ai_tech', 'lens' => 'ai_jobs_youth']),
    'manual_arc' => 'ARC-AI-JOBS',
];
assertTrue('null frame → decision_inquiry', eduQuestResolvedFrame([]) === 'decision_inquiry');
assertTrue('frame filter all', eduQuestMatchesFrameFilter($mockQuest, 'all'));
$item = eduQuestToListItem($mockQuest);
assertTrue('list item category', ($item['category'] ?? '') === 'ai_tech');
assertTrue('list item lens', ($item['lens'] ?? '') === 'ai_jobs_youth');
assertTrue('list mock entry_mode stance_pick', ($item['entry_mode'] ?? '') === 'stance_pick');

$japanList = eduQuestToListItem(array_merge($japan = eduG09DecQuestFixture(), ['id' => 'japan-id']));
assertTrue('list japan entry_mode', ($japanList['entry_mode'] ?? '') === 'stance_pick');
$nukeList = eduQuestToListItem(array_merge($nuke = eduNuke630QuestFixture(), ['id' => 'nuke-id']));
assertTrue('list nuke entry_mode', ($nukeList['entry_mode'] ?? '') === 'open_response');

/** @param array<string, mixed> $quest */
function eduQuestMatchesFrameFilterLegacy(array $quest, string $frame): bool
{
    $hints = eduQuestRawHammerHints($quest);
    $resolved = eduQuestResolvedFrame($hints);
    if ($frame === 'all') {
        return true;
    }
    if ($frame === 'myth_bust') {
        return $resolved === 'myth_bust';
    }
    if ($frame === 'decision_inquiry') {
        return $resolved === 'decision_inquiry';
    }

    return $resolved === $frame;
}

echo "\n--- frame filter (QuestConfig vs legacy) ---\n";

$japan = eduG09DecQuestFixture();
$nuke = eduNuke630QuestFixture();
$iran = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'hammer_hints' => ['quest_frame' => 'decision_inquiry'],
];
$autoFrame = [
    'quest_code' => 'Q-AUTO',
    'hammer_hints' => [],
];

$fixtures = ['japan' => $japan, 'nuke' => $nuke, 'iran' => $iran, 'auto' => $autoFrame];
$filters = ['all', 'myth_bust', 'decision_inquiry'];

foreach ($fixtures as $name => $quest) {
    foreach ($filters as $filter) {
        $legacy = eduQuestMatchesFrameFilterLegacy($quest, $filter);
        $current = eduQuestMatchesFrameFilter($quest, $filter);
        assertTrue("{$name}/{$filter} legacy parity", $legacy === $current);
    }
}

assertTrue('myth_bust filter: nuke only', eduQuestMatchesFrameFilter($nuke, 'myth_bust'));
assertTrue('myth_bust filter: not japan', !eduQuestMatchesFrameFilter($japan, 'myth_bust'));
assertTrue('myth_bust filter: not iran', !eduQuestMatchesFrameFilter($iran, 'myth_bust'));
assertTrue('decision filter: japan', eduQuestMatchesFrameFilter($japan, 'decision_inquiry'));
assertTrue('decision filter: iran', eduQuestMatchesFrameFilter($iran, 'decision_inquiry'));
assertTrue('decision filter: not nuke', !eduQuestMatchesFrameFilter($nuke, 'decision_inquiry'));
assertTrue('decision filter: auto empty frame', eduQuestMatchesFrameFilter($autoFrame, 'decision_inquiry'));
assertTrue('all filter: nuke', eduQuestMatchesFrameFilter($nuke, 'all'));

echo "\n=== Result: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
