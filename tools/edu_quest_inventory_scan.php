<?php
/**
 * GIST EDU — published 기사 + topic_label 인벤토리 (READ ONLY)
 *
 * Usage:
 *   php tools/edu_quest_inventory_scan.php
 *   php tools/edu_quest_inventory_scan.php --lookback=365 --limit=500
 *   php tools/edu_quest_inventory_scan.php --write-docs
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/src/backend/Services/edu/EduQuestFactory.php';

use Services\Edu\EduQuestFactory;

$lookback = 180;
$limit = 500;
$writeDocs = in_array('--write-docs', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--lookback=')) {
        $lookback = max(30, (int) substr($arg, 11));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(50, (int) substr($arg, 8));
    }
}

echo "=== EDU Quest Inventory Scan (READ ONLY) ===\n";
echo "lookback={$lookback}d limit={$limit}\n\n";

$supabase = eduSupabase();
$published = [];
$source = 'mysql';

try {
    $pdo = eduMysql();
    $factory = new EduQuestFactory($pdo, $supabase, null);
    $published = $factory->loadPublishedArticlesForCatalog($lookback, $limit);
} catch (Throwable $e) {
    echo "MySQL unavailable ({$e->getMessage()}) — fallback judgement_records\n";
    $source = 'judgement_records';
    $published = eduQuestLoadArticlesFromJudgement($supabase, $lookback, $limit);
    $factory = null;
}

if ($published === []) {
    fwrite(STDERR, "No published articles found\n");
    exit(1);
}
$arcKeywords = EduQuestFactory::arcTopicKeywords();
$sprint0 = eduQuestSprint0NewsIds();

$byCategory = [];
$unclassified = [];
$questReady = [];
$lowScore = [];
$sensitive = [];

foreach ($published as $id => $article) {
    $score = eduQuestScoreArticle($article);
    $arcs = eduQuestMatchArcsForArticle($article, $arcKeywords);
    $inSprint0 = isset($sprint0[$id]);

    $row = [
        'news_id' => $id,
        'title' => $article['title'] ?? '',
        'category' => $article['category'] ?? '',
        'topic_label' => $article['topic_label'] ?? '',
        'published_at' => $article['published_at'] ?? '',
        'arcs' => $arcs,
        'score' => $score,
        'sprint0' => $inSprint0,
    ];

    if (($score['safety'] ?? '') === 'N') {
        $sensitive[] = $row;
        continue;
    }

    if ($arcs === []) {
        $unclassified[] = $row;
    }

    if (($score['note'] ?? '') === 'quest_ready') {
        $questReady[] = $row;
    } elseif (($score['note'] ?? '') === 'low') {
        $lowScore[] = $row;
    }

    foreach ($arcs as $arc) {
        $cat = eduQuestCategoryForArc($arc) ?? 'unmapped';
        $byCategory[$cat][] = $row;
    }
}

// arc별 기사 수 집계
$byArc = [];
foreach ($published as $id => $article) {
    foreach (eduQuestMatchArcsForArticle($article, $arcKeywords) as $arc) {
        $byArc[$arc][$id] = true;
    }
}

echo 'Published scanned: ' . count($published) . "\n";
echo 'Sprint0 pool overlap: ' . count(array_filter(array_keys($published), fn($id) => isset($sprint0[$id]))) . "\n";
echo 'Quest-ready (score>=12): ' . count($questReady) . "\n";
echo 'Unclassified (no arc match): ' . count($unclassified) . "\n";
echo 'Sensitive excluded: ' . count($sensitive) . "\n\n";

echo "--- Arc article counts (3+ = quest candidate) ---\n";
uksort($byArc, fn($a, $b) => count($byArc[$b]) <=> count($byArc[$a]));
foreach ($byArc as $arc => $ids) {
    $cnt = count($ids);
    $cat = eduQuestCategoryLabel(eduQuestCategoryForArc($arc) ?? 'unmapped');
    $flag = $cnt >= 3 ? 'OK' : 'LOW';
    echo sprintf("[%s] %s (%s) — %d articles\n", $flag, $arc, $cat, $cnt);
}

if (!$writeDocs) {
    echo "\nTip: --write-docs to generate docs/GIST_EDU_QUEST_CATALOG_v1.md\n";
    exit(0);
}

$md = <<<MD
# GIST EDU Quest Catalog v1 (auto-generated)

> Generated: {$lookback}d lookback · {count} published articles scanned (source: {$source})
> Convention: [`GIST_EDU_QUEST_CATEGORIES.json`](GIST_EDU_QUEST_CATEGORIES.json)

## Summary

| Metric | Count |
|--------|-------|
| Published scanned | {count} |
| Quest-ready (score ≥12) | {quest_ready} |
| Unclassified | {unclassified} |
| Sensitive excluded | {sensitive} |
| Sprint 0 overlap | {sprint0_overlap} |

## Arc coverage (3+ articles = factory candidate)

| Arc | Category | Articles | Status |
|-----|----------|----------|--------|
MD;

$md = str_replace('{source}', $source, $md);
$md = str_replace('{count}', (string) count($published), $md);
$md = str_replace('{quest_ready}', (string) count($questReady), $md);
$md = str_replace('{unclassified}', (string) count($unclassified), $md);
$md = str_replace('{sensitive}', (string) count($sensitive), $md);
$md = str_replace(
    '{sprint0_overlap}',
    (string) count(array_filter(array_keys($published), fn($id) => isset($sprint0[$id]))),
    $md
);

foreach ($byArc as $arc => $ids) {
    $cnt = count($ids);
    $cat = eduQuestCategoryLabel(eduQuestCategoryForArc($arc) ?? 'unmapped');
    $status = $cnt >= 3 ? 'candidate' : 'need_more';
    $md .= "| {$arc} | {$cat} | {$cnt} | {$status} |\n";
}

$md .= "\n## Top quest-ready articles (unclassified priority)\n\n";
$unclassifiedReady = array_filter($unclassified, fn($r) => ($r['score']['note'] ?? '') === 'quest_ready');
usort($unclassifiedReady, fn($a, $b) => ($b['score']['total'] ?? 0) <=> ($a['score']['total'] ?? 0));
$unclassifiedReady = array_slice($unclassifiedReady, 0, 30);
foreach ($unclassifiedReady as $r) {
    $md .= sprintf(
        "- [%d] %s · %s · score=%d\n",
        $r['news_id'],
        $r['title'],
        $r['topic_label'] ?: $r['category'],
        $r['score']['total'] ?? 0
    );
}

$md .= "\n## Category distribution\n\n";
foreach (eduQuestCategoryDefinitions() as $catId => $def) {
    $cnt = count($byCategory[$catId] ?? []);
    $md .= sprintf("- **%s** (%s): %d article matches\n", $def['label'], $catId, $cnt);
}

$outPath = $root . '/docs/GIST_EDU_QUEST_CATALOG_v1.md';
file_put_contents($outPath, $md);
echo "\nWrote {$outPath}\n";
