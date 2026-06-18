<?php
/**
 * READ ONLY — lens candidate inventory (cross-cut article groupings)
 *
 * Usage:
 *   php tools/edu_quest_lens_inventory.php
 *   php tools/edu_quest_lens_inventory.php --lookback=365 --limit=400
 *   php tools/edu_quest_lens_inventory.php --write-md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
require_once $root . '/src/backend/Services/edu/EduQuestFactory.php';

use Services\Edu\EduQuestFactory;

$lookback = 180;
$limit = 400;
$writeMd = in_array('--write-md', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--lookback=')) {
        $lookback = max(30, (int) substr($arg, 11));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(50, (int) substr($arg, 8));
    }
}

echo "=== EDU Lens Inventory (READ ONLY) ===\n";
echo "lookback={$lookback}d limit={$limit}\n\n";

$supabase = eduSupabase();
$articles = eduQuestLoadArticlesFromJudgement($supabase, $lookback, $limit);
if ($articles === []) {
    echo "No articles loaded\n";
    exit(1);
}

$lenses = eduQuestLensDefinitions();
if ($lenses === []) {
    echo "GIST_EDU_LENSES.json not found or empty\n";
    exit(1);
}

/** @var array<string, list<array<string, mixed>>> */
$byLens = [];
/** @var array<int, list<string>> */
$articleLenses = [];

foreach ($articles as $nid => $article) {
    $haystack = mb_strtolower(implode(' ', [
        (string) ($article['title'] ?? ''),
        (string) ($article['topic_label'] ?? ''),
        (string) ($article['category'] ?? ''),
    ]));

    $matched = [];
    foreach ($lenses as $lensId => $def) {
        foreach ($def['keywords'] ?? [] as $kw) {
            if ($kw !== '' && str_contains($haystack, mb_strtolower($kw))) {
                $matched[] = $lensId;
                break;
            }
        }
    }
    $matched = array_values(array_unique($matched));
    if ($matched === []) {
        continue;
    }
    $articleLenses[$nid] = $matched;
    foreach ($matched as $lensId) {
        $byLens[$lensId][] = $article;
    }
}

$arcMap = EduQuestFactory::arcTopicKeywords();
$multiLens = 0;
foreach ($articleLenses as $lensList) {
    if (count($lensList) > 1) {
        $multiLens++;
    }
}

echo 'Articles scanned: ' . count($articles) . "\n";
echo 'Articles with ≥1 lens: ' . count($articleLenses) . "\n";
echo "Articles with ≥2 lenses (cross-cut candidates): {$multiLens}\n\n";

echo "| Lens | Label | Articles | Quest-ready (≥3) |\n";
echo "|------|-------|----------|------------------|\n";

$reportLines = ["# EDU Lens Inventory\n", "> Generated: lookback {$lookback}d\n\n"];
$reportLines[] = "| Lens | Label | Articles | ≥3 | Top titles |\n";
$reportLines[] = "|------|-------|----------|-----|-------------|\n";

foreach ($lenses as $lensId => $def) {
    $items = $byLens[$lensId] ?? [];
    $count = count($items);
    $ready = $count >= 3 ? 'Y' : 'need_more';
    $label = (string) ($def['label'] ?? $lensId);
    echo "| {$lensId} | {$label} | {$count} | {$ready} |\n";

    $titles = array_slice(array_map(static fn ($a) => (string) ($a['title'] ?? ''), $items), 0, 3);
    $titleCell = implode('; ', $titles);
    $reportLines[] = "| {$lensId} | {$label} | {$count} | {$ready} | {$titleCell} |\n";
}

echo "\n--- Cross-cut examples (article → multiple lenses) ---\n";
$shown = 0;
foreach ($articleLenses as $nid => $lensList) {
    if (count($lensList) < 2) {
        continue;
    }
    $title = (string) ($articles[$nid]['title'] ?? $nid);
    echo "  [{$nid}] {$title}\n";
    echo '    lenses: ' . implode(', ', $lensList) . "\n";
    $arcs = eduQuestMatchArcsForArticle($articles[$nid], $arcMap);
    if ($arcs !== []) {
        echo '    surface arcs: ' . implode(', ', $arcs) . "\n";
    }
    if (++$shown >= 12) {
        break;
    }
}

if ($writeMd) {
    $path = $root . '/docs/GIST_EDU_LENS_INVENTORY.md';
    file_put_contents($path, implode('', $reportLines));
    echo "\nWrote {$path}\n";
}
