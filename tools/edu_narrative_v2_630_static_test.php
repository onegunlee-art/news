<?php
/**
 * 630 narrative_bridge_v2 — 6층 + 생각판 static test
 * Usage: php tools/edu_narrative_v2_630_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeV2.php';

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

$questV2 = ['quest_code' => 'Q-AUTO-NUKE-630', 'hammer_hints' => ['coach_mode' => 'narrative_bridge_v2']];
$questOff = [
    'quest_code' => 'Q-AUTO-NUKE-630',
    'hammer_hints' => [],
];
$questV1 = ['quest_code' => 'Q-AUTO-NUKE-630', 'hammer_hints' => ['coach_mode' => 'narrative_bridge_v1']];
$quest150 = ['quest_code' => 'Q-AUTO-DC-150', 'hammer_hints' => []];

echo "=== 630 narrative v2 static test ===\n\n";

$script = eduNarrativeV2LoadScript($questV2);
ok('script loaded', ($script['version'] ?? '') === 'narrative_bridge_v2');
ok('6 layers defined', count($script['layers'] ?? []) === 6);

ok('v2 ON', eduQuestUsesNarrativeV2($questV2));
ok('v2 ON via draft overlay', eduQuestUsesNarrativeV2($questOff));
ok('v1 OFF when v2', !eduQuestUsesNarrativeBridge($questV2));
ok('v2 OFF for 150', !eduQuestUsesNarrativeV2($quest150));
ok('axis OFF for v2', !eduQuestUsesAxisGuide($questV2));

$golden = [
    'nuclear_fear', 'want_nuclear', 'cant_touch', 'fear_revenge', 'see_board',
    'wont_work', 'conditional', 'depth_ok', 'nuke_different', 'destruction',
    'true_hard', 'counter_ok', 'limits', 'refine_ok', '__text__', 'go_compose',
];

$sim = eduNarrativeV2SimulatePath(eduBlueprintDefaults(), $questV2, $golden);
$bp = $sim['blueprint'];
$turns = (int) ($bp['narrative_turn_count'] ?? 0);
ok('golden path turns 10-15', $turns >= 10 && $turns <= 16);
ok('golden path compose ready', !empty($bp['ready_for_compose']));
ok('6 board cards filled', eduNarrativeV2FilledLayerCount($bp['thought_board'] ?? []) === 6);

$scqa = eduNarrativeV2ScqaFromBoard($bp['thought_board'] ?? []);
ok('scqa S filled', ($scqa['S'] ?? '') !== '');
ok('scqa conclusion filled', ($scqa['conclusion'] ?? '') !== '');

$structure = eduNarrativeV2EssayStructureFromBoard($bp['thought_board'] ?? [], $questV2);
ok('essay structure from board', ($structure['generated_by'] ?? '') === 'thought_board_v2');
ok('structure sections >= 6', count($structure['sections'] ?? []) >= 6);
ok('composer accepts prebuilt', eduNarrativeV2HasPrebuiltStructure(eduNarrativeV2BuildComposeBlueprint($bp, $questV2)));

$bpSynth = eduNarrativeV2InitBlueprint(eduBlueprintDefaults(), $script);
$bpSynth['narrative_v2_node'] = 'n_synth_0';
$bpSynth['phase'] = EDU_NARRATIVE_V2_PHASE;
$restore = eduNarrativeV2RestoreMeta($bpSynth, $questV2, [['role' => 'assistant', 'content' => 'test']]);
ok('state restore text input mode', is_array($restore) && ($restore['narrative_v2_input_mode'] ?? '') === 'text');

$paths = eduNarrativeV2EnumeratePaths($script, (string) $script['start_node']);
ok('paths enumerated', count($paths) >= 1);
echo 'INFO path_count=' . count($paths) . "\n";

$ui = is_file($root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2.tsx')
    ? (string) file_get_contents($root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2.tsx')
    : '';
ok('V2 UI exists', $ui !== '');
ok('thought board panel', str_contains($ui, 'EduThoughtBoardPanel'));
ok('mobile keyboard inset', str_contains($ui, 'useVisualViewportLayout'));
ok('card snippet parsing', str_contains($ui, 'parseCoachCardContent'));
ok('article snippet card', str_contains($ui, 'EduArticleSnippetCard'));

$page = is_file($root . '/src/frontend/src/pages/edu/QuestFlowPage.tsx')
    ? (string) file_get_contents($root . '/src/frontend/src/pages/edu/QuestFlowPage.tsx')
    : '';
ok('QuestFlowPage routes v2', str_contains($page, 'QuestFlowNarrativeV2'));
ok('QuestFlowPage URL coach_mode hint', str_contains($page, 'coach_mode'));
ok('QuestFlowPage no mobile branch', !preg_match('/isMobile|matchMedia|innerWidth\s*[<>=]/', $page));

$legacyBp = eduBlueprintDefaults();
$legacyBp['phase'] = 'guide_axis';
$legacyDialogue = [
    ['role' => 'assistant', 'content' => '어떤 쪽이 더 와닿아?', 'turn_id' => 'guide_axis'],
];
ok('polluted: legacy phase', eduNarrativeV2SessionIsPolluted($legacyBp, $legacyDialogue));
ok('polluted: legacy turn_id', eduNarrativeV2SessionIsPolluted(
    ['phase' => EDU_NARRATIVE_V2_PHASE],
    [['role' => 'assistant', 'content' => 'old', 'turn_id' => 'guide_axis']]
));
ok('clean: empty dialogue', !eduNarrativeV2SessionIsPolluted(['phase' => 'guide_axis'], []));
ok('clean: v2 dialogue', !eduNarrativeV2SessionIsPolluted(
    ['phase' => EDU_NARRATIVE_V2_PHASE],
    [['role' => 'assistant', 'content' => '1945', 'turn_id' => 't-1', 'agent' => 'narrative_v2']]
));
ok('clean: v2 agent marker', !eduNarrativeV2SessionIsPolluted(
    ['phase' => EDU_NARRATIVE_V2_PHASE],
    [['role' => 'assistant', 'content' => '1945', 'turn_id' => 't-2', 'agent' => 'narrative_v2']]
));

$resetInit = eduNarrativeV2HandleInit($legacyBp, $questV2, true);
ok('force reset clears legacy phase', ($resetInit['blueprint']['phase'] ?? '') === EDU_NARRATIVE_V2_PHASE);
ok('force reset opening mentions 1945', str_contains((string) ($resetInit['message'] ?? ''), '1945'));
ok('force reset session_reset flag', !empty($resetInit['session_reset']));

$v2Ui = is_file($root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2.tsx')
    ? (string) file_get_contents($root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2.tsx')
    : '';
ok('V2 auto reset on pollution', str_contains($v2Ui, 'narrativeV2SessionIsPolluted'));
ok('V2 force_reset payload', str_contains($v2Ui, 'force_reset'));
ok('V2 coach prompt fallback', str_contains($v2Ui, 'choice_question_text'));
ok('V2 mobile compact board', str_contains($v2Ui, 'useMobileCompactLayout'));
ok('V2 board peek on pulse', str_contains($v2Ui, 'boardPeekTimerRef'));

$boardUi = is_file($root . '/src/frontend/src/components/edu/EduThoughtBoardPanel.tsx')
    ? (string) file_get_contents($root . '/src/frontend/src/components/edu/EduThoughtBoardPanel.tsx')
    : '';
ok('thought board compact mode', str_contains($boardUi, 'compact?: boolean'));
ok('thought board horizontal scroll', str_contains($boardUi, 'overflow-x-auto'));

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
