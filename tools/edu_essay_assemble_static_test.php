<?php
/**
 * Essay assemble — thought_board 실데이터 연결 + 오염 방지 static test
 * Usage: php tools/edu_essay_assemble_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
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

echo "=== edu essay assemble static test ===\n\n";

/** PHP mirror of frontend connectorForSlot / piecesFromThoughtBoard */
function testConnectorForSlot(array $slot): string
{
    $byIndex = [1 => '', 2 => '왜냐하면 ', 3 => '그런데 ', 4 => '한편 ', 5 => '그래서 ', 6 => '따라서 '];
    $idx = (int) ($slot['index'] ?? 0);
    if ($idx >= 1 && $idx <= 6) {
        return $byIndex[$idx];
    }
    return '';
}

function testPiecesFromBoard(array $board): array
{
    $filled = array_values(array_filter($board, static fn ($s) => !empty($s['filled']) && trim((string) ($s['text'] ?? '')) !== ''));
    usort($filled, static fn ($a, $b) => ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0)));
    $pieces = [];
    foreach ($filled as $slot) {
        $pieces[] = [
            'fullText' => trim((string) ($slot['text'] ?? '')),
            'connector' => testConnectorForSlot($slot),
        ];
    }
    return $pieces;
}

function testAssembleDraft(array $board): string
{
    $pieces = testPiecesFromBoard($board);
    if ($pieces === []) {
        return '생각판 내용을 불러오는 중…';
    }
    $parts = [];
    foreach ($pieces as $p) {
        $parts[] = $p['connector'] . $p['fullText'];
    }
    return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
}

$laborBoard = [
    ['layer_id' => 'stance', 'index' => 1, 'label' => '입장', 'filled' => true, 'text' => '청년 실업은 구조적 문제다'],
    ['layer_id' => 'reason', 'index' => 2, 'label' => '근거', 'filled' => true, 'text' => '채용 시장이 좁아졌기 때문이다'],
    ['layer_id' => 'depth', 'index' => 3, 'label' => '깊이', 'filled' => true, 'text' => '다만 지역마다 차이는 있다'],
    ['layer_id' => 'counter', 'index' => 4, 'label' => '반론', 'filled' => true, 'text' => '개인 노력도 중요하다는 말이 있다'],
    ['layer_id' => 'refine', 'index' => 5, 'label' => '재정립', 'filled' => true, 'text' => '구조와 개인 노력 둘 다 봐야 한다'],
    ['layer_id' => 'synthesis', 'index' => 6, 'label' => '종합', 'filled' => true, 'text' => '정책과 개인 준비가 함께 필요하다'],
];

$franceBoard = [
    ['layer_id' => 'stance', 'index' => 1, 'filled' => true, 'text' => '프랑스 연금 개혁은 불가피하다'],
    ['layer_id' => 'reason', 'index' => 2, 'filled' => true, 'text' => '고령화로 재정 부담이 커졌다'],
    ['layer_id' => 'synthesis', 'index' => 6, 'filled' => true, 'text' => '사회적 합의가 필요하다'],
];

$nukeBoard = [
    ['layer_id' => 'stance', 'index' => 1, 'filled' => true, 'text' => '핵 억지력이 안전을 지킨다'],
    ['layer_id' => 'reason', 'index' => 2, 'filled' => true, 'text' => '상호 확증 파괴 때문이다'],
];

$laborDraft = testAssembleDraft($laborBoard);
$franceDraft = testAssembleDraft($franceBoard);
$nukeDraft = testAssembleDraft($nukeBoard);

ok('labor draft contains labor keyword', str_contains($laborDraft, '청년') || str_contains($laborDraft, '실업'));
ok('labor draft has no hardcoded nuke-only default', !str_contains($laborDraft, '핵 억지'));
ok('france draft contains france keyword', str_contains($franceDraft, '프랑스'));
ok('france draft distinct from labor', $franceDraft !== $laborDraft);
ok('nuke draft from nuke board only', str_contains($nukeDraft, '핵'));
ok('connectors applied structurally', str_contains($laborDraft, '왜냐하면') && str_contains($laborDraft, '따라서'));

$questLabor = ['quest_title' => '청년 노동시장'];
$structureLabor = eduNarrativeV2EssayStructureFromBoard($laborBoard, $questLabor);
ok('structure title uses quest_title not nuke fallback', str_contains((string) ($structureLabor['title'] ?? ''), '청년 노동시장'));
ok('structure generated_by thought_board_v2', ($structureLabor['generated_by'] ?? '') === 'thought_board_v2');
ok('structure sections from slot text', ($structureLabor['sections'][0]['bullets'][0] ?? '') === '청년 실업은 구조적 문제다');

$questEmpty = [];
$structureEmpty = eduNarrativeV2EssayStructureFromBoard($laborBoard, $questEmpty);
ok('empty quest title fallback neutral', str_contains((string) ($structureEmpty['title'] ?? ''), '오늘의 탐구'));
ok('empty quest title not nuke', !str_contains((string) ($structureEmpty['title'] ?? ''), '핵 억지'));

$assembleUtilPath = $root . '/src/frontend/src/utils/eduEssayAssemble.ts';
$assembleUtil = is_file($assembleUtilPath) ? (string) file_get_contents($assembleUtilPath) : '';
ok('assemble util exists', $assembleUtil !== '');
ok('assemble util no nuke fallback string', !str_contains($assembleUtil, '핵 억지'));
ok('assemble util no hardcoded piece text', !str_contains($assembleUtil, '핵이') && !str_contains($assembleUtil, '630'));

$panelPath = $root . '/src/frontend/src/components/edu/EduComposeWaitPanel.tsx';
$panel = is_file($panelPath) ? (string) file_get_contents($panelPath) : '';
ok('compose wait panel exists', $panel !== '');
ok('wait panel uses piecesFromThoughtBoard', str_contains($panel, 'piecesFromThoughtBoard'));
ok('wait panel no fake draft assembler', !str_contains($panel, 'assembleDraftFromBoard'));
ok('wait panel reflection before after', str_contains($panel, '처음:') && str_contains($panel, '지금:'));
ok('wait panel cycling status lines', str_contains($panel, '논증 구조를 세우는 중'));
ok('wait panel no empty silhouette box', !str_contains($panel, 'aria-hidden'));
ok('wait panel single AnimatePresence wait', str_contains($panel, 'mode="wait"'));
ok('wait panel no boxShadow glow', !str_contains($panel, 'boxShadow'));

$v2Path = $root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2.tsx';
$v2 = is_file($v2Path) ? (string) file_get_contents($v2Path) : '';
ok('v2 parallel compose flow', str_contains($v2, 'startParallelCompose') && str_contains($v2, 'scheduleComposeReveal'));
ok('v2 compose response drives transition', str_contains($v2, 'revealComposeResult') && str_contains($v2, 'applyComposeResult'));
ok('v2 no animDoneRef gate', !str_contains($v2, 'animDoneRef'));
ok('v2 no handleAnimComplete', !str_contains($v2, 'handleAnimComplete'));

$mobilePath = $root . '/src/frontend/src/components/edu/QuestFlowNarrativeV2Mobile.tsx';
$mobile = is_file($mobilePath) ? (string) file_get_contents($mobilePath) : '';
ok('v2 uses compose wait panel', str_contains($mobile, 'EduComposeWaitPanel') || str_contains($v2, 'EduComposeWaitPanel'));
ok('v2 mobile pc split', str_contains($v2, 'QuestFlowNarrativeV2Mobile') && str_contains($v2, 'QuestFlowNarrativeV2Pc'));
ok('v2 no assembleDraftFromBoard import', !str_contains($v2, 'assembleDraftFromBoard'));

$pcThemePath = $root . '/src/frontend/src/constants/eduPcRedesignTheme.ts';
$pcTheme = is_file($pcThemePath) ? (string) file_get_contents($pcThemePath) : '';
ok('eduPcRedesignTheme exists', $pcTheme !== '');
ok('eduPcRedesignTheme isolated', str_contains($pcTheme, '#070707') && !str_contains($pcTheme, "bg: '#ffffff'"));

$gameThemePath = $root . '/src/frontend/src/constants/eduGameTheme.ts';
$gameTheme = is_file($gameThemePath) ? (string) file_get_contents($gameThemePath) : '';
ok('eduGameTheme bg still white mobile', str_contains($gameTheme, "bg: '#ffffff'"));

ok('mobile view frozen component exists', $mobile !== '');

$scriptDir = $root . '/docs/coach_scripts';
$scriptFiles = glob($scriptDir . '/*_narrative_v2.json') ?: [];
ok('narrative_v2 script count >= 45', count($scriptFiles) >= 45);

$labelOk = 0;
foreach ($scriptFiles as $path) {
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        continue;
    }
    $node = $raw['nodes']['n_synth_compose'] ?? null;
    if (!is_array($node)) {
        continue;
    }
    foreach ($node['choices'] ?? [] as $choice) {
        if (!is_array($choice) || ($choice['id'] ?? '') !== 'go_compose') {
            continue;
        }
        if (($choice['label'] ?? '') === '글로 엮기') {
            $labelOk++;
        }
    }
}
ok('all go_compose labels are 글로 엮기', $labelOk === count($scriptFiles));

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
