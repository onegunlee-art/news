<?php
/**
 * Step 2 — draft 퀘스트 → approved (검수 후 라이브)
 *
 * Usage:
 *   php tools/edu_quest_generate_approve.php --dry-run --quest-code=Q-GIST-220
 *   php tools/edu_quest_generate_approve.php --apply --quest-code=Q-GIST-220
 *   php tools/edu_quest_generate_approve.php --apply --all-drafts --source=p2-step2-batch
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestGenerate.php';

use Agents\Services\SupabaseService;

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;
$allDrafts = in_array('--all-drafts', $argv ?? [], true);
$source = EDU_QUEST_GENERATE_SOURCE;
$questCodes = [];

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCodes[] = substr($arg, 13);
    }
    if (str_starts_with($arg, '--source=')) {
        $source = substr($arg, 9);
    }
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

if ($allDrafts) {
    $rows = $supabase->select(
        'edu_daily_quests',
        'status=eq.draft&order=created_at.desc',
        200
    ) ?? [];
    foreach ($rows as $row) {
        $code = (string) ($row['quest_code'] ?? '');
        $scores = is_string($row['scores'] ?? null) ? json_decode($row['scores'], true) : ($row['scores'] ?? []);
        if (!str_starts_with($code, 'Q-GIST-')) {
            continue;
        }
        if (($scores['source'] ?? '') !== $source) {
            continue;
        }
        $questCodes[] = $code;
    }
    $questCodes = array_values(array_unique($questCodes));
}

if ($questCodes === []) {
    fwrite(STDERR, "Usage: --quest-code=Q-GIST-... or --all-drafts\n");
    exit(1);
}

echo "=== Quest approve (draft → approved) ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n\n";

$approved = 0;
foreach ($questCodes as $code) {
    if (!str_starts_with($code, 'Q-GIST-')) {
        echo "SKIP {$code} — only Q-GIST-* allowed\n";
        continue;
    }
    if (eduQuestGenerateIsProtectedQuestCode($code) && $code !== 'Q-GIST-' . preg_replace('/\D/', '', $code)) {
        echo "SKIP {$code} — protected\n";
        continue;
    }

    $rows = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
    $quest = $rows[0] ?? null;
    if ($quest === null) {
        echo "MISSING {$code}\n";
        continue;
    }
    if (($quest['status'] ?? '') === 'approved') {
        echo "ALREADY approved {$code}\n";
        continue;
    }

    if ($dryRun) {
        echo "WOULD approve {$code}\n";
        $approved++;
        continue;
    }

    $updated = $supabase->update('edu_daily_quests', 'id=eq.' . ($quest['id'] ?? ''), [
        'status' => 'approved',
        'updated_at' => date('c'),
    ]);
    if ($updated === null) {
        echo "FAIL {$code}: " . $supabase->getLastError() . "\n";
        continue;
    }
    echo "APPROVED {$code}\n";
    $approved++;
}

echo "\nApproved: {$approved}/" . count($questCodes) . "\n";
