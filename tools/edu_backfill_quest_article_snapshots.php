<?php
/**
 * GIST EDU — edu_quest_articles snapshot backfill (all quests or one)
 *
 * Usage:
 *   php tools/edu_backfill_quest_article_snapshots.php --dry-run
 *   php tools/edu_backfill_quest_article_snapshots.php --quest-code=Q-AUTO-...
 *   php tools/edu_backfill_quest_article_snapshots.php --status=draft
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestArticleSnapshot.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$questCode = null;
$status = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = substr($arg, 13);
    }
    if (str_starts_with($arg, '--status=')) {
        $status = substr($arg, 9);
    }
}

echo "=== EDU quest article snapshot backfill ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n\n";

$supabase = eduSupabase();
$pdo = null;
try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    echo "MySQL unavailable — judgement_records only\n";
}

$filter = 'order=created_at.desc';
if ($questCode !== null) {
    $quests = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 5) ?? [];
} elseif ($status !== null) {
    $quests = $supabase->select('edu_daily_quests', 'status=eq.' . rawurlencode($status) . '&' . $filter, 50) ?? [];
} else {
    $quests = $supabase->select('edu_daily_quests', $filter, 50) ?? [];
}

if ($quests === []) {
    fwrite(STDERR, "No quests matched\n");
    exit(1);
}

$totalUpdated = 0;
$totalSkipped = 0;

foreach ($quests as $quest) {
    $questId = (string) ($quest['id'] ?? '');
    $code = (string) ($quest['quest_code'] ?? '');
    echo "\n## {$code} ({$questId})\n";

    $articles = $supabase->select(
        'edu_quest_articles',
        'quest_id=eq.' . $questId . '&order=sort_order.asc',
        20
    ) ?? [];

    foreach ($articles as $article) {
        $result = eduBackfillQuestArticleSnapshot($supabase, $pdo, $article, $dryRun);
        echo '  news_id=' . ($article['news_id'] ?? '?') . ': ' . $result['status'] . "\n";
        if ($result['status'] === 'updated' || ($dryRun && $result['status'] === 'would_update')) {
            $totalUpdated++;
        } else {
            $totalSkipped++;
        }
    }
}

echo "\n=== Done: updated={$totalUpdated} skipped={$totalSkipped} ===\n";
