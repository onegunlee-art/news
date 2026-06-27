<?php
/**
 * L2~L5 compose 경로 — 정적 회귀 (DB/LLM/코치 FSM 없음)
 *
 * Usage: php tools/edu_newlevel_compose_path_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';

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

echo "=== EDU new-level compose path static test ===\n\n";

/** axis_guide myth_bust — stance 없음, L3 세션과 동일 패턴 */
$axisGuideBlueprint = [
    'coach_level' => 3,
    'phase' => 'reflection',
    'reason' => '전기를 더 만들어야 한다',
    'evidence' => '데이터센터가 늘어난다',
    'counter_handled' => true,
    'reflection_confirmed' => true,
    'guide_student_conclusion' => '그래도 발전 투자가 먼저다',
    'reflection_lines' => ['배경', '입장', '반론'],
];

ok('axis_guide reflection 80% (stance 없음)', eduBlueprintProgress($axisGuideBlueprint) === 80);

$axisGuideBlueprint['ready_for_compose'] = true;
$axisGuideBlueprint['phase'] = 'compose';
ok('ready_for_compose → progress 100', eduBlueprintProgress($axisGuideBlueprint) === 100);
ok('ready_for_compose → compose ready', eduBlueprintReadyForCompose($axisGuideBlueprint));

$legacyBlueprint = [
    'stance' => 'pro',
    'phase' => 'reflection',
    'reason' => 'r',
    'evidence' => 'e',
    'counter_handled' => true,
    'reflection_confirmed' => true,
    'coach_level' => 1,
];
ok('L1 legacy reflection 90%', eduBlueprintProgress($legacyBlueprint) === 90);
$legacyBlueprint['ready_for_compose'] = true;
ok('L1 legacy ready → 100%', eduBlueprintProgress($legacyBlueprint) === 100);

$cardsTs = (string) file_get_contents($root . '/src/frontend/src/components/edu/cardStructureBarState.ts');
ok('structure bar compose → all slots done', str_contains($cardsTs, "case 'compose':") && str_contains($cardsTs, 'completed: 5, current: -1'));

$triggerTs = (string) file_get_contents($root . '/src/frontend/src/utils/eduComposeTrigger.ts');
ok('compose trigger helper exists', str_contains($triggerTs, 'shouldTriggerEduCompose'));
ok('trigger checks ready_for_compose', str_contains($triggerTs, 'ready_for_compose'));

$cardsFlow = (string) file_get_contents($root . '/src/frontend/src/pages/edu/QuestFlowCards.tsx');
ok('Cards compose before sync', strpos($cardsFlow, 'await handleCompose(sid)') < strpos($cardsFlow, 'await syncSessionState(sid)'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
