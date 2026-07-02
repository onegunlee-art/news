<?php
/**
 * 630 narrative_bridge_v1 — JSON 스크립트 + FSM 정적 검증 (LLM/DB 없음)
 *
 * Usage: php tools/edu_narrative_bridge_630_static_test.php
 *
 * 수동 E2E 체크리스트 (630 live):
 * 1. 630 시작 → STEP 0 서사(1945~)로 시작, 툭 질문 아님
 * 2. STEP 3 드론 흔들기 — "답은 B" 느낌 없음, 3버튼 모두 동일 무게
 * 3. STEP 4 — 갈래마다 코치 반응 문구가 다름
 * 4. STEP 5 — 코치 결론 없음, [글 쓰러 가기] → compose
 * 5. 다른 퀘스트는 QuestFlowCards/Chat 그대로 (narrative UI 없음)
 * 6. coach_mode OFF 시 630 → 기존 axis_guide FSM 복귀
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeBridge.php';

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

/**
 * @return list<list<string>>
 */
function eduNarrativeBridgeEnumeratePaths(array $script, string $nodeId, array $trail = []): array
{
    $node = eduNarrativeBridgeGetNode($script, $nodeId);
    if ($node === null) {
        return [];
    }
    if (!empty($node['terminal'])) {
        return [$trail];
    }
    $choices = $node['choices'] ?? [];
    if (!is_array($choices) || $choices === []) {
        return [$trail];
    }
    $paths = [];
    foreach ($choices as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $id = (string) ($choice['id'] ?? '');
        $next = (string) ($choice['next'] ?? '');
        if ($id === '' || $next === '') {
            continue;
        }
        foreach (eduNarrativeBridgeEnumeratePaths($script, $next, array_merge($trail, [$id])) as $path) {
            $paths[] = $path;
        }
    }

    return $paths;
}

echo "=== 630 narrative bridge static test ===\n\n";

$script = eduNarrativeBridgeLoadScript();
ok('script quest_code', ($script['quest_code'] ?? '') === 'Q-AUTO-NUKE-630');
ok('script has start_node', ($script['start_node'] ?? '') === 'step_0');
ok('script has shake_suffix', trim((string) ($script['shake_suffix'] ?? '')) !== '');

$questOn = [
    'quest_code' => 'Q-AUTO-NUKE-630',
    'hammer_hints' => ['coach_mode' => 'narrative_bridge_v1'],
];
$questOff = [
    'quest_code' => 'Q-AUTO-NUKE-630',
    'hammer_hints' => [],
];
$questOther = [
    'quest_code' => 'Q-AUTO-DC-150',
    'hammer_hints' => ['coach_mode' => 'axis_guide_v1'],
];

ok('narrative bridge ON for 630+flag', eduQuestUsesNarrativeBridge($questOn));
ok('narrative bridge ON for 630 via draft overlay', eduQuestUsesNarrativeBridge($questOff));
ok('narrative bridge OFF for 150', !eduQuestUsesNarrativeBridge($questOther));
ok('axis guide OFF when narrative ON', !eduQuestUsesAxisGuide($questOn));
ok('axis guide OFF for 630 when narrative draft overlay', !eduQuestUsesAxisGuide($questOff));

$init = eduNarrativeBridgeHandleInit(eduBlueprintDefaults(), $questOn);
ok('init phase', ($init['blueprint']['phase'] ?? '') === 'narrative_bridge');
ok('init step 0 message', str_contains($init['message'], '1945'));
ok('init has 3 choices', count($init['choices']) === 3);

$paths = eduNarrativeBridgeEnumeratePaths($script, (string) $script['start_node']);
ok('paths enumerated', count($paths) >= 3);
$pathCount = count($paths);
echo "INFO path_count={$pathCount}\n";

$composePaths = 0;
foreach ($paths as $path) {
    $bp = eduBlueprintDefaults();
    $result = eduNarrativeBridgeHandleInit($bp, $questOn);
    $bp = $result['blueprint'];
    $last = null;
    foreach ($path as $choiceId) {
        $last = eduNarrativeBridgeHandleChoice($bp, $questOn, $choiceId);
        $bp = $last['blueprint'];
    }
    if (!empty($last['should_compose']) && ($bp['phase'] ?? '') === 'compose') {
        $composePaths++;
    }
}
ok('all paths reach compose', $composePaths === $pathCount);

$samplePath = ['nuclear_fear', 'want_nuclear', 'might_shake', 'changed_mind', 'go_compose'];
$bp = eduBlueprintDefaults();
$bp = eduNarrativeBridgeHandleInit($bp, $questOn)['blueprint'];
foreach ($samplePath as $choiceId) {
    $turn = eduNarrativeBridgeHandleChoice($bp, $questOn, $choiceId);
    $bp = $turn['blueprint'];
}
ok('sample path compose', !empty($turn['should_compose']));
ok('sample stance pro', ($bp['stance'] ?? '') === 'pro');
ok('hammer skipped counter_handled', !empty($bp['counter_handled']));
ok('reason populated', trim((string) ($bp['reason'] ?? '')) !== '');

$branchA = eduNarrativeBridgePresentNode(eduBlueprintDefaults(), $questOn, $script, 'step_4_might_shake');
$branchB = eduNarrativeBridgePresentNode(eduBlueprintDefaults(), $questOn, $script, 'step_4_still_strong');
ok('step4 branch A text differs B', $branchA['message'] !== $branchB['message']);

$uiFile = $root . '/src/frontend/src/components/edu/QuestFlowNarrativeBridge.tsx';
$pageFile = $root . '/src/frontend/src/pages/edu/QuestFlowPage.tsx';
$uiSrc = is_file($uiFile) ? (string) file_get_contents($uiFile) : '';
$pageSrc = is_file($pageFile) ? (string) file_get_contents($pageFile) : '';
ok('UI component exists', $uiSrc !== '');
ok('QuestFlowPage routes narrative', str_contains($pageSrc, 'QuestFlowNarrativeBridge'));
ok('Cards untouched', !str_contains((string) file_get_contents($root . '/src/frontend/src/pages/edu/QuestFlowCards.tsx'), 'narrative_bridge'));

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
