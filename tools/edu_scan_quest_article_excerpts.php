<?php
/**
 * GIST EDU — scan edu_quest_articles for missing excerpt/why_important
 *
 * Usage:
 *   php tools/edu_scan_quest_article_excerpts.php
 *   php tools/edu_scan_quest_article_excerpts.php --quest-code=Q-LENS-NUKE-001
 *   php tools/edu_scan_quest_article_excerpts.php --apply   # backfill all gaps
 *   php tools/edu_scan_quest_article_excerpts.php --apply --quest-code=Q-LENS-NUKE-001
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestArticleSnapshot.php';

$apply = in_array('--apply', $argv ?? [], true);
$questCode = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = substr($arg, 13);
    }
}

$supabase = eduSupabase();
$pdo = null;
try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    echo "MySQL unavailable — judgement_records path only\n";
}

/** @return list<array<string, mixed>> */
function loadAllQuests($supabase, ?string $questCode): array
{
    if ($questCode !== null) {
        return $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 10) ?? [];
    }

    $all = [];
    $offset = 0;
    $pageSize = 100;
    while (true) {
        $batch = $supabase->select(
            'edu_daily_quests',
            'order=created_at.desc&limit=' . $pageSize . '&offset=' . $offset,
            $pageSize
        ) ?? [];
        if ($batch === []) {
            break;
        }
        foreach ($batch as $row) {
            $all[] = $row;
        }
        if (count($batch) < $pageSize) {
            break;
        }
        $offset += $pageSize;
    }

    return $all;
}

echo "=== EDU quest article excerpt scan ===\n";
echo 'mode: ' . ($apply ? 'APPLY backfill' : 'scan only') . "\n\n";

$quests = loadAllQuests($supabase, $questCode);
if ($quests === []) {
    fwrite(STDERR, "No quests matched\n");
    exit(1);
}

$gapRows = [];
$totalArticles = 0;
$filled = 0;

foreach ($quests as $quest) {
    $questId = (string) ($quest['id'] ?? '');
    $code = (string) ($quest['quest_code'] ?? '');
    $status = (string) ($quest['status'] ?? '');

    $articles = $supabase->select(
        'edu_quest_articles',
        'quest_id=eq.' . $questId . '&order=sort_order.asc',
        30
    ) ?? [];

    foreach ($articles as $article) {
        $totalArticles++;
        $excerptLen = mb_strlen(trim((string) ($article['excerpt'] ?? '')));
        $whyLen = mb_strlen(trim((string) ($article['why_important'] ?? '')));
        $newsId = (int) ($article['news_id'] ?? 0);
        $title = (string) ($article['title'] ?? '');

        if ($excerptLen > 0 && $whyLen > 0) {
            $filled++;
            continue;
        }

        $gapRows[] = [
            'quest_code' => $code,
            'quest_status' => $status,
            'news_id' => $newsId,
            'title' => $title,
            'excerpt_len' => $excerptLen,
            'why_len' => $whyLen,
            'article' => $article,
        ];

        echo "GAP {$code} news_id={$newsId} excerpt={$excerptLen} why={$whyLen}\n";
        echo "     {$title}\n";

        if ($apply) {
            $result = eduBackfillQuestArticleSnapshot($supabase, $pdo, $article, false);
            echo '     backfill: ' . $result['status'] . "\n";
            if (($result['status'] ?? '') === 'updated' && isset($result['patch']['excerpt'])) {
                $newLen = mb_strlen((string) $result['patch']['excerpt']);
                echo "     excerpt now: {$newLen} chars\n";
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo 'quests scanned: ' . count($quests) . "\n";
echo "articles total: {$totalArticles}\n";
echo "fully filled: {$filled}\n";
echo 'gaps found: ' . count($gapRows) . "\n";

if (!$apply && count($gapRows) > 0) {
    echo "\nRun with --apply to backfill all gaps, or:\n";
    echo "  php tools/edu_backfill_quest_article_snapshots.php --quest-code=...\n";
}

exit(count($gapRows) > 0 && !$apply ? 1 : 0);
