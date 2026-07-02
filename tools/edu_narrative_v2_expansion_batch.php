<?php
/**
 * narrative_bridge_v2 expansion — 생성 + approved 배포 + 검수 표
 *
 * Usage:
 *   php tools/edu_narrative_v2_expansion_batch.php --dry-run
 *   php tools/edu_narrative_v2_expansion_batch.php --generate --apply
 *   php tools/edu_narrative_v2_expansion_batch.php --generate --apply --llm
 *   php tools/edu_narrative_v2_expansion_batch.php --deploy --apply
 *   php tools/edu_narrative_v2_expansion_batch.php --generate --deploy --apply
 *
 * Options:
 *   --quest-code=Q-GIST-662
 *   --limit=5
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeV2.php';
require_once $root . '/public/api/edu/lib/eduNarrativeV2Generate.php';

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply || in_array('--dry-run', $argv ?? [], true);
$doGenerate = in_array('--generate', $argv ?? [], true);
$doDeploy = in_array('--deploy', $argv ?? [], true);
$useLlm = in_array('--llm', $argv ?? [], true);
$limit = 0;
$questFilter = '';
$skipGolden = ['Q-AUTO-NUKE-630', 'Q-NUKE-AXIS-630'];

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--quest-code=')) {
        $questFilter = trim(substr($arg, 13));
    }
}

if (!$doGenerate && !$doDeploy) {
    $doGenerate = true;
    $doDeploy = true;
}

echo "=== narrative v2 expansion batch ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'generate: ' . ($doGenerate ? 'yes' : 'no') . "\n";
echo 'deploy: ' . ($doDeploy ? 'yes' : 'no') . "\n";
echo 'llm: ' . ($useLlm ? 'yes' : 'rule-based') . "\n\n";

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$filter = 'status=eq.approved&order=live_at.desc.nullslast';
if ($questFilter !== '') {
    $filter = 'quest_code=eq.' . rawurlencode($questFilter);
}
$quests = $supabase->select('edu_daily_quests', $filter, 200) ?? [];
if ($quests === []) {
    fwrite(STDERR, "No quests found\n");
    exit(1);
}

$candidates = [];
foreach ($quests as $q) {
    $code = (string) ($q['quest_code'] ?? '');
    if ($code === '' || in_array($code, $skipGolden, true)) {
        continue;
    }
    $candidates[] = $q;
}

if ($limit > 0) {
    $candidates = array_slice($candidates, 0, $limit);
}

echo 'candidates: ' . count($candidates) . "\n\n";

$auditRows = [];
$genOk = 0;
$genFail = 0;
$deployOk = 0;

foreach ($candidates as $quest) {
    $code = (string) ($quest['quest_code'] ?? '');
    $title = (string) ($quest['quest_title'] ?? '');
    $level = (int) ($quest['difficulty_level'] ?? 0);
    echo "--- {$code} ---\n";

    $ctx = eduNarrativeV2GenerateContextFromQuest($quest);
    $scriptPath = eduNarrativeV2GenerateScriptPath($code);
    $generated = false;
    $llmUsed = false;
    $audit = ['philosophy_ok' => false, 'flags' => [], 'flag_count' => 0];

    if ($doGenerate) {
        try {
            $result = eduNarrativeV2GenerateScript($quest, $ctx, $useLlm);
            $script = $result['script'];
            $audit = $result['audit'];
            $llmUsed = !empty($result['llm_used']);
            if (!$dryRun) {
                eduNarrativeV2SaveGeneratedScript($script);
            }
            $valid = $dryRun
                ? count($script['layers'] ?? []) === 6
                : eduNarrativeV2ValidateGeneratedScript($quest, $script);

            $generated = $valid;
            echo '  generate: ' . ($valid ? 'OK' : 'FAIL') . ($llmUsed ? ' (llm)' : ' (rule)') . "\n";
            echo '  script: ' . $scriptPath . "\n";
            echo '  philosophy: ' . ($audit['philosophy_ok'] ? 'OK' : 'FLAGS=' . ($audit['flag_count'] ?? 0)) . "\n";
            if (!$audit['philosophy_ok']) {
                foreach ($audit['flags'] as $f) {
                    echo "    flag: {$f}\n";
                }
            }
            $genOk += $valid ? 1 : 0;
            $genFail += $valid ? 0 : 1;
        } catch (Throwable $e) {
            echo '  generate: ERROR ' . $e->getMessage() . "\n";
            $genFail++;
        }
    } else {
        $generated = is_file($scriptPath);
        echo '  script exists: ' . ($generated ? 'yes' : 'no') . "\n";
    }

    if ($doDeploy && $generated) {
        $hints = eduQuestHammerHints($quest);
        $before = (string) ($hints['coach_mode'] ?? '');
        $hints['coach_mode'] = EDU_NARRATIVE_V2_MODE;
        $payload = [
            'hammer_hints' => $hints,
            'status' => 'approved',
        ];
        if ($dryRun) {
            echo "  deploy: DRY coach_mode {$before} → " . EDU_NARRATIVE_V2_MODE . " (approved)\n";
            $deployOk++;
        } else {
            $updated = $supabase->update('edu_daily_quests', 'id=eq.' . ($quest['id'] ?? ''), $payload);
            if ($updated === null) {
                echo '  deploy: FAIL ' . $supabase->getLastError() . "\n";
            } else {
                echo "  deploy: OK coach_mode → v2\n";
                $deployOk++;
            }
        }
    }

    $introPreview = '';
    if (is_file($scriptPath)) {
        $saved = json_decode((string) file_get_contents($scriptPath), true);
        $introPreview = eduNarrativeV2TrimPhrase(
            (string) ($saved['nodes']['n_intro']['coach_text'] ?? ''),
            80
        );
    }

    $auditRows[] = [
        'quest_code' => $code,
        'title' => $title,
        'level' => $level > 0 ? 'L' . $level : '?',
        'side_a' => eduNarrativeV2TrimPhrase((string) ($ctx['side_a'] ?? ''), 50),
        'side_b' => eduNarrativeV2TrimPhrase((string) ($ctx['side_b'] ?? ''), 50),
        'intro_preview' => $introPreview,
        'philosophy_ok' => !empty($audit['philosophy_ok']),
        'flags' => implode('; ', $audit['flags'] ?? []),
    ];
    echo "\n";
}

$auditPath = $root . '/docs/NARRATIVE_V2_EXPANSION_AUDIT.md';
$md = "# narrative_bridge_v2 expansion audit\n\n";
$md .= 'Generated: ' . date('c') . "\n";
$md .= 'Mode: ' . ($dryRun ? 'dry-run' : 'apply') . ' · LLM: ' . ($useLlm ? 'yes' : 'rule-based') . "\n";
$md .= 'Generated OK: ' . $genOk . ' · Fail: ' . $genFail . ' · Deployed: ' . $deployOk . "\n\n";
$md .= "## 검수 포인트\n\n";
$md .= "1. **반론 = 흔들기** — philosophy REVIEW 행 우선\n";
$md .= "2. 서사·6층 자연스러움\n";
$md .= "3. 난이도별 깊이\n";
$md .= "4. 샘플 완주 + 모바일\n\n";
$md .= "| quest_code | L | philosophy | flags | intro | side_a | side_b |\n";
$md .= "|------------|---|------------|-------|-------|--------|--------|\n";
foreach ($auditRows as $row) {
    $md .= '| ' . implode(' | ', [
        $row['quest_code'],
        $row['level'],
        $row['philosophy_ok'] ? 'OK' : '**REVIEW**',
        $row['flags'] !== '' ? $row['flags'] : '-',
        str_replace('|', '/', $row['intro_preview']),
        str_replace('|', '/', $row['side_a']),
        str_replace('|', '/', $row['side_b']),
    ]) . " |\n";
}

if (!$dryRun) {
    file_put_contents($auditPath, $md);
    echo "Audit written: {$auditPath}\n";
}

echo "\nSummary: gen_ok={$genOk} gen_fail={$genFail} deploy={$deployOk}\n";
exit($genFail > 0 ? 1 : 0);
