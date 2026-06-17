<?php
/**
 * GIST EDU — Q-NUKE-AXIS-630 시드 (content 손 축, 테스트용 live_at=null)
 *
 * Usage:
 *   php tools/seed_nuclear_axis_quest.php --dry-run
 *   php tools/seed_nuclear_axis_quest.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

use Agents\Services\SupabaseService;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$fixture = eduNuke630QuestFixture();
$questCode = $fixture['quest_code'];

$row = [
    'quest_code' => $questCode,
    'quest_title' => $fixture['quest_title'],
    'grade_band' => 'middle',
    'status' => 'approved',
    'manual_arc' => 'ARC-NUKE-DETERRENCE',
    'pro_line' => $fixture['pro_line'],
    'con_line' => $fixture['con_line'],
    'alignment_summary' => $fixture['alignment_summary'],
    'conflict_summary' => $fixture['conflict_summary'],
    'hammer_hints' => $fixture['hammer_hints'],
    'pilot_priority' => 'A',
    'live_at' => null,
    'expires_at' => null,
];

$articles = eduNuke630QuestArticles();

$supabase = new SupabaseService([]);
if (!$dryRun && !$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

echo "=== seed {$questCode} (convergent + content axes) ===\n";
echo 'mode: ' . ($dryRun ? 'dry-run' : 'LIVE') . "\n\n";

if ($dryRun) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\nNote: live_at=null — 이란 live 유지\n";
    exit(0);
}

$existing = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
if (!empty($existing[0]['id'])) {
    $questId = $existing[0]['id'];
    $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $row);
    echo "Updated quest {$questCode}\n";
} else {
    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        fwrite(STDERR, 'Insert failed: ' . $supabase->getLastError() . "\n");
        exit(1);
    }
    $questId = $inserted[0]['id'];
    echo "Inserted quest {$questCode}\n";
}

$supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
$sort = 0;
foreach ($articles as $article) {
    $supabase->insert('edu_quest_articles', [
        'quest_id' => $questId,
        'news_id' => (int) $article['news_id'],
        'role' => $article['role'],
        'sort_order' => $sort++,
        'title' => $article['title'],
        'gist_url' => $article['gist_url'],
    ]);
}
echo 'Synced ' . count($articles) . " articles\n";

echo "\nNext: php tools/edu_backfill_iran_article_snapshots.php --quest-code={$questCode}\n";
echo "Test: php tools/edu_nuclear_axis_coach_test.php --live\n";
