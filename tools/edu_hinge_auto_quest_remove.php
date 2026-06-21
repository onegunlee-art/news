<?php
/**
 * P2-A3 롤백 — Q-AUTO-NUKE-630 제거 (edu_* only)
 *
 * Usage:
 *   php tools/edu_hinge_auto_quest_remove.php --dry-run
 *   php tools/edu_hinge_auto_quest_remove.php --apply
 *   php tools/edu_hinge_auto_quest_remove.php --apply --quest-code=Q-AUTO-NUKE-630
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\SupabaseService;

$dryRun = !in_array('--apply', $argv ?? [], true);
$questCode = 'Q-AUTO-NUKE-630';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = substr($arg, 13);
    }
}

echo "=== P2-A3 Auto Quest Rollback ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo "quest_code: {$questCode}\n";
echo "DELETE: edu_quest_articles (cascade) + edu_daily_quests row\n";
echo "NEVER touches: Q-NUKE-AXIS-630, news, judgement_records\n\n";

$supabase = new SupabaseService([]);
if (!$dryRun && !$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$rows = $dryRun ? [] : ($supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1) ?? []);
if ($dryRun) {
    echo "[dry-run] would delete quest {$questCode} + articles\n";
    exit(0);
}

if ($rows === []) {
    echo "Quest {$questCode} not found — nothing to do\n";
    exit(0);
}

$questId = $rows[0]['id'];
$articles = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $questId, 50) ?? [];
echo 'Found quest id=' . $questId . ' articles=' . count($articles) . "\n";

$supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
$supabase->delete('edu_daily_quests', 'id=eq.' . $questId);

$check = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
if ($check === []) {
    echo "Removed {$questCode} OK\n";
} else {
    fwrite(STDERR, "Delete may have failed\n");
    exit(1);
}
