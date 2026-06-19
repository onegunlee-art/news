<?php
/**
 * Check MySQL news row + backfill source availability for news_ids
 *
 * Usage: php tools/edu_check_news_snapshot_sources.php 196 152
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestArticleSnapshot.php';

$ids = array_map('intval', array_slice($argv, 1));
if ($ids === []) {
    fwrite(STDERR, "Usage: php tools/edu_check_news_snapshot_sources.php <news_id>...\n");
    exit(1);
}

$pdo = null;
try {
    $pdo = eduMysql();
    echo "MySQL: connected\n\n";
} catch (Throwable $e) {
    echo "MySQL: UNAVAILABLE ({$e->getMessage()})\n";
    echo "→ EC2에서 이 스크립트를 돌려야 196/152 backfill 가능 여부 확인\n\n";
}

$supabase = eduSupabase();

foreach ($ids as $nid) {
    echo "=== news_id={$nid} ===\n";

    $news = eduSnapshotLoadNewsRow($pdo, $nid);
    if ($news === null) {
        echo "  MySQL news row: missing or unavailable\n";
    } else {
        $nar = mb_strlen(trim(strip_tags((string) ($news['narration'] ?? $news['description'] ?? ''))));
        $why = mb_strlen(trim(strip_tags((string) ($news['why_important'] ?? ''))));
        $content = mb_strlen(trim(strip_tags((string) ($news['content'] ?? ''))));
        echo "  MySQL news: title=" . ($news['title'] ?? '?') . "\n";
        echo "  narration_len={$nar} why_len={$why} content_len={$content}\n";
    }

    $judgement = eduSnapshotLoadJudgementRow($supabase, $nid);
    if ($judgement === null) {
        echo "  judgement_records: none\n";
    } else {
        $human = eduSnapshotDecodeJson($judgement['human_output'] ?? null);
        $why = mb_strlen(trim((string) ($human['why_important'] ?? '')));
        $nar = mb_strlen(trim((string) ($human['narration'] ?? '')));
        echo "  judgement_records: why={$why} narration={$nar}\n";
    }

    $canBackfill = $news !== null && (
        mb_strlen(trim((string) ($news['narration'] ?? $news['description'] ?? $news['content'] ?? ''))) > 0
        || mb_strlen(trim((string) ($news['why_important'] ?? ''))) > 0
    );
    if ($judgement !== null) {
        $human = eduSnapshotDecodeJson($judgement['human_output'] ?? null);
        $canBackfill = $canBackfill || mb_strlen(trim((string) ($human['narration'] ?? ''))) > 0;
    }

    echo '  backfill_possible: ' . ($canBackfill ? 'YES (with MySQL+judgement path)' : 'NO — case (b) candidate') . "\n\n";
}
