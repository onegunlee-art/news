<?php
/**
 * GIST EDU — Quest catalog batch (draft 30~50 candidates)
 *
 * Usage:
 *   php tools/edu_quest_catalog_batch.php --dry-run
 *   php tools/edu_quest_catalog_batch.php --dry-run --limit=50 --lookback=180
 *   php tools/edu_quest_catalog_batch.php --apply --limit=20
 *   php tools/edu_quest_catalog_batch.php --dry-run --category=middle_east_iran
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/backend/Services/edu/EduQuestFactory.php';

use Services\Edu\EduQuestFactory;

$dryRun = !in_array('--apply', $argv ?? [], true);
$noLlm = in_array('--no-llm', $argv ?? [], true);
$limit = 50;
$lookback = 180;
$articleLimit = 500;
$category = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--lookback=')) {
        $lookback = max(30, (int) substr($arg, 11));
    }
    if (str_starts_with($arg, '--category=')) {
        $category = substr($arg, 11);
    }
}

$variantsPerArc = 3;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--variants=')) {
        $variantsPerArc = max(1, (int) substr($arg, 11));
    }
}

echo "=== EDU Quest Catalog Batch ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo "limit={$limit} lookback={$lookback}d category=" . ($category ?? 'all') . " variants={$variantsPerArc}\n\n";

eduLoadAgents();
$supabase = eduSupabase();
$pdo = null;
$published = [];
$source = 'mysql';

try {
    $pdo = eduMysql();
    $factory = new EduQuestFactory($pdo, $supabase, null);
    $published = $factory->loadPublishedArticlesForCatalog($lookback, $articleLimit);
} catch (Throwable $e) {
    echo "MySQL unavailable — judgement_records fallback\n";
    $source = 'judgement_records';
    $published = eduQuestLoadArticlesFromJudgement($supabase, $lookback, $articleLimit);
    $llm = null;
    if (!$noLlm) {
        try {
            $llm = eduLlm();
        } catch (Throwable $le) {
            echo "LLM unavailable: {$le->getMessage()}\n";
        }
    }
    $factory = new EduQuestFactory(null, $supabase, $llm);
}

if ($published === []) {
    fwrite(STDERR, "No articles to scan\n");
    exit(1);
}

echo "Articles loaded: " . count($published) . " ({$source})\n";

$candidates = $factory->discoverCatalogVariants(
    $published,
    $limit,
    $variantsPerArc,
    $category
);

echo 'Candidates: ' . count($candidates) . "\n\n";

$created = 0;
foreach ($candidates as $i => $draft) {
    $arc = $draft['manual_arc'] ?? '?';
    $cat = eduQuestCategoryLabel(eduQuestCategoryForArc((string) $arc) ?? 'unmapped');
    $codes = array_map(fn($a) => (int) ($a['news_id'] ?? 0), $draft['articles'] ?? []);
    echo sprintf(
        "[%d] %s | %s | %s | articles=%s\n",
        $i + 1,
        $draft['quest_code'] ?? '?',
        $arc,
        $cat,
        implode(',', $codes)
    );
    echo '  title: ' . ($draft['quest_title'] ?? '') . "\n";
    echo '  pro: ' . mb_substr((string) ($draft['pro_line'] ?? ''), 0, 60) . "\n";
    echo '  con: ' . mb_substr((string) ($draft['con_line'] ?? ''), 0, 60) . "\n";

    if (!$dryRun) {
        $result = $factory->persistDraft($draft, false);
        if ($result !== null) {
            echo '  => persisted ' . ($result['quest_id'] ?? '') . "\n";
            $created++;
        } else {
            echo "  => SKIP persist failed\n";
        }
    }
    echo "\n";
}

echo "=== Summary: " . count($candidates) . " candidates";
if (!$dryRun) {
    echo ", {$created} persisted";
}
echo " ===\n";

exit(count($candidates) > 0 ? 0 : 1);
