<?php
/**
 * 630 narrative bridge 라이브/로컬 진단
 * Usage: php tools/_probe_630_narrative_live.php [--live]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeBridge.php';

$live = in_array('--live', $argv ?? [], true);

echo "=== 630 narrative bridge probe ===\n\n";

$questStub = ['quest_code' => 'Q-AUTO-NUKE-630', 'hammer_hints' => []];
$hints = eduQuestHammerHints($questStub);
echo '[local stub] coach_mode=' . ($hints['coach_mode'] ?? 'NULL') . "\n";
echo '[local stub] narrative=' . (eduQuestUsesNarrativeBridge($questStub) ? 'true' : 'false') . "\n";
echo '[local stub] axis_guide=' . (eduQuestUsesAxisGuide($questStub) ? 'true' : 'false') . "\n";

$scriptPath = eduFindProjectRoot() . 'docs/coach_scripts/630_narrative_bridge.json';
$draftPath = eduFindProjectRoot() . 'docs/hinge_quest_drafts/AUTO-630-min.json';
echo '[local files] script=' . (is_file($scriptPath) ? 'OK' : 'MISSING') . "\n";
echo '[local files] draft=' . (is_file($draftPath) ? 'OK' : 'MISSING') . "\n";
echo '[local files] php_fsm=' . (is_file($root . '/public/api/edu/lib/eduCoachGuideNarrativeBridge.php') ? 'OK' : 'MISSING') . "\n\n";

try {
    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        echo "Supabase not configured — skip DB\n";
    } else {
        $rows = $supabase->select('edu_daily_quests', 'quest_code=eq.Q-AUTO-NUKE-630', 1) ?? [];
        $quest = $rows[0] ?? null;
        if ($quest === null) {
            echo "[db] Q-AUTO-NUKE-630 NOT FOUND in edu_daily_quests\n";
        } else {
            $dbHints = $quest['hammer_hints'] ?? [];
            if (is_string($dbHints)) {
                $dbHints = json_decode($dbHints, true) ?: [];
            }
            $merged = eduQuestHammerHints($quest);
            $public = eduPublicQuestPayload(array_merge($quest, ['articles' => []]));
            echo '[db] quest_id=' . ($quest['id'] ?? '?') . ' status=' . ($quest['status'] ?? '?') . "\n";
            echo '[db] hammer_hints.coach_mode(raw)=' . ($dbHints['coach_mode'] ?? 'NULL') . "\n";
            echo '[db] hammer_hints.coach_mode(merged)=' . ($merged['coach_mode'] ?? 'NULL') . "\n";
            echo '[db] eduQuestUsesNarrativeBridge=' . (eduQuestUsesNarrativeBridge($quest) ? 'true' : 'false') . "\n";
            echo '[db] public coach_mode=' . ($public['coach_mode'] ?? 'NULL') . "\n";
        }
    }
} catch (Throwable $e) {
    echo '[db] error: ' . $e->getMessage() . "\n";
}

if (!$live) {
    exit(0);
}

echo "\n--- live HTTP ---\n";
$base = 'https://www.thegist.co.kr';
$paths = [
    '/version.json',
    '/assets/QuestFlowNarrativeBridge-BELRLTI8.js',
    '/assets/QuestFlowPage-BgR2a7PX.js',
];

foreach ($paths as $path) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => str_ends_with($path, '.js'),
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "{$path} HTTP {$code}\n";
}

$ch = curl_init($base . '/assets/QuestFlowPage-BgR2a7PX.js');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
$js = curl_exec($ch);
curl_close($ch);
if (is_string($js)) {
    echo 'QuestFlowPage has NarrativeBridge: ' . (str_contains($js, 'NarrativeBridge') ? 'yes' : 'no') . "\n";
    echo 'QuestFlowPage has coach_mode: ' . (str_contains($js, 'coach_mode') ? 'yes' : 'no') . "\n";
}
