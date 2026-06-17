<?php
/**
 * GIST EDU §13-a — Iran quest article snapshot backfill (LLM 0, READ-only)
 *
 * Fills edu_quest_articles.excerpt / why_important from MySQL news + judgement_records.
 * Manual Iran seed omitted these fields; auto quests get them via EduQuestFactory.
 *
 * Usage:
 *   php tools/edu_backfill_iran_article_snapshots.php --dry-run
 *   php tools/edu_backfill_iran_article_snapshots.php
 *   php tools/edu_backfill_iran_article_snapshots.php --quest-code=Q-IRAN-FOREVER-001
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/lib/auth.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$questCode = 'Q-IRAN-FOREVER-001';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = substr($arg, strlen('--quest-code='));
    }
}

$expectedByQuest = [
    'Q-IRAN-FOREVER-001' => [555, 422, 528],
    'Q-G09-DEC-2022' => [546, 452, 558],
    'Q-NUKE-AXIS-630' => [630, 475, 449, 615],
];
$expectedNewsIds = $expectedByQuest[$questCode] ?? [];

function stripPlain(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
}

/** @return list<string> */
function splitSentences(string $text, int $max = 5): array
{
    $text = stripPlain($text);
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $out[] = $p;
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

/** @param mixed $raw */
function decodeJsonField($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        return json_decode($raw, true) ?: [];
    }
    return [];
}

/** @return list<string> */
function keyPointsFromJudgement(array $ai): array
{
    $kps = $ai['key_points'] ?? null;
    if (!is_array($kps) || $kps === []) {
        return [];
    }
    $out = [];
    foreach ($kps as $kp) {
        $line = stripPlain((string) $kp);
        if ($line !== '') {
            $out[] = $line;
        }
        if (count($out) >= 5) {
            break;
        }
    }
    return $out;
}

/** @return list<string> */
function buildExcerptLines(array $sources): array
{
    $keyPoints = keyPointsFromJudgement($sources['ai'] ?? []);
    if ($keyPoints !== []) {
        return $keyPoints;
    }

    foreach (['human_narration', 'news_narration', 'human_content'] as $field) {
        $lines = splitSentences((string) ($sources[$field] ?? ''), 5);
        if ($lines !== []) {
            return $lines;
        }
    }

    $why = stripPlain((string) ($sources['why_important'] ?? ''));
    if ($why !== '') {
        return splitSentences($why, 3);
    }

    return [];
}

function formatExcerpt(array $lines): string
{
    if ($lines === []) {
        return '';
    }
    $numbered = [];
    $i = 1;
    foreach ($lines as $line) {
        $numbered[] = $i . '. ' . $line;
        $i++;
    }
    return implode("\n", $numbered);
}

function loadNewsRow(PDO $pdo, int $newsId): ?array
{
    $cols = ['id', 'title', 'status'];
    foreach (['narration', 'why_important', 'description', 'content', 'source', 'original_source', 'published_at'] as $c) {
        $cols[] = $c;
    }
    $existing = [];
    try {
        $st = $pdo->query('SHOW COLUMNS FROM news');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $existing[] = $row['Field'];
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'MySQL columns check failed: ' . $e->getMessage() . "\n");
        return null;
    }
    $select = array_values(array_intersect($cols, $existing));
    if (!in_array('id', $select, true)) {
        return null;
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM news WHERE id = ? LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$newsId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function loadJudgementRow(\Agents\Services\SupabaseService $supabase, int $newsId): ?array
{
    $rows = $supabase->select(
        'judgement_records',
        'news_id=eq.' . $newsId . '&order=created_at.desc',
        1
    );
    return $rows[0] ?? null;
}

echo "=== EDU §13-a Iran article snapshot backfill ===\n";
echo 'quest_code: ' . $questCode . "\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n\n";

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$quests = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
if (empty($quests[0]['id'])) {
    fwrite(STDERR, "Quest not found: {$questCode}\n");
    exit(1);
}
$questId = (string) $quests[0]['id'];
echo "quest_id: {$questId}\n";

$articles = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $questId . '&order=sort_order.asc',
    20
) ?? [];
if ($articles === []) {
    fwrite(STDERR, "No edu_quest_articles for quest\n");
    exit(1);
}

try {
    $pdo = getDb();
} catch (Throwable $e) {
    fwrite(STDERR, 'MySQL unavailable (' . $e->getMessage() . ") — judgement_records only\n");
    $pdo = null;
}

$updated = 0;
$skipped = 0;

foreach ($articles as $article) {
    $newsId = (int) ($article['news_id'] ?? 0);
    $title = (string) ($article['title'] ?? '');
    echo "\n--- news_id={$newsId} {$title} ---\n";

    $news = $pdo !== null ? loadNewsRow($pdo, $newsId) : null;
    if ($news === null && $pdo !== null) {
        echo "  WARN: news row not found in MySQL — trying judgement only\n";
    }

    $judgement = loadJudgementRow($supabase, $newsId);
    $human = $judgement !== null ? decodeJsonField($judgement['human_output'] ?? null) : [];
    $ai = $judgement !== null ? decodeJsonField($judgement['ai_output'] ?? null) : [];

    $whyImportant = stripPlain((string) ($human['why_important'] ?? ''));
    if ($whyImportant === '' && $news !== null) {
        $whyImportant = stripPlain((string) ($news['why_important'] ?? ''));
    }

    $narration = stripPlain((string) ($human['narration'] ?? ''));
    if ($narration === '' && $news !== null) {
        $narration = stripPlain((string) ($news['narration'] ?? $news['description'] ?? ''));
    }

    $sources = [
        'ai' => $ai,
        'human_narration' => $narration,
        'news_narration' => $news !== null
            ? stripPlain((string) ($news['narration'] ?? $news['description'] ?? ''))
            : '',
        'human_content' => stripPlain((string) ($human['content'] ?? '')),
        'why_important' => $whyImportant,
    ];

    $excerptLines = buildExcerptLines($sources);
    $excerpt = formatExcerpt($excerptLines);
    if ($excerpt === '' && $narration !== '') {
        $excerpt = mb_substr($narration, 0, 400);
    }

    $sourceOutlet = $news !== null
        ? (string) ($news['original_source'] ?? $news['source'] ?? 'the gist')
        : 'the gist';
    $publishedAt = $news !== null ? ($news['published_at'] ?? null) : null;

    if ($excerpt === '' && $judgement === null) {
        echo "  SKIP: no excerpt source (no judgement, no news)\n";
        $skipped++;
        continue;
    }

    $currentExcerpt = trim((string) ($article['excerpt'] ?? ''));
    $currentWhy = trim((string) ($article['why_important'] ?? ''));

    echo '  why_important: ' . ($whyImportant !== '' ? mb_substr($whyImportant, 0, 80) . '…' : '(empty)') . "\n";
    echo '  excerpt lines: ' . count($excerptLines) . "\n";
    if ($excerpt !== '') {
        foreach (explode("\n", $excerpt) as $line) {
            echo '    ' . mb_substr($line, 0, 100) . (mb_strlen($line) > 100 ? '…' : '') . "\n";
        }
    } else {
        echo "  excerpt: (empty — cannot backfill)\n";
        $skipped++;
        continue;
    }

    if ($currentExcerpt !== '' && $currentWhy !== '') {
        echo "  already filled — skip update\n";
        $skipped++;
        continue;
    }

    $patch = array_filter([
        'excerpt' => $excerpt,
        'why_important' => $whyImportant !== '' ? $whyImportant : null,
        'source_outlet' => $sourceOutlet !== '' ? $sourceOutlet : null,
        'published_at' => $publishedAt,
    ], fn ($v) => $v !== null && $v !== '');

    if ($dryRun) {
        echo "  [dry-run] would PATCH " . json_encode(array_keys($patch), JSON_UNESCAPED_UNICODE) . "\n";
        $updated++;
        continue;
    }

    $rowId = $article['id'] ?? '';
    if ($rowId === '') {
        echo "  SKIP: article row id missing\n";
        $skipped++;
        continue;
    }

    $result = $supabase->update('edu_quest_articles', 'id=eq.' . $rowId, $patch);
    if ($result === null) {
        echo '  ERROR: ' . $supabase->getLastError() . "\n";
        $skipped++;
        continue;
    }
    echo "  PATCHED ok\n";
    $updated++;
}

echo "\n=== Summary ===\n";
echo "updated: {$updated}\n";
echo "skipped: {$skipped}\n";

if (!$dryRun && $updated > 0) {
    echo "\nVerify:\n";
    echo "  php tools/edu_backfill_iran_article_snapshots.php --dry-run\n";
    echo "  (or Supabase select edu_quest_articles where quest_id=...)\n";
}

if ($expectedNewsIds !== []) {
    $foundIds = array_map(fn ($a) => (int) ($a['news_id'] ?? 0), $articles);
    $missing = array_diff($expectedNewsIds, $foundIds);
    if ($missing !== []) {
        echo 'WARN: expected news_ids missing from quest: ' . implode(',', $missing) . "\n";
        exit(1);
    }
}

exit($skipped > 0 && $updated === 0 ? 1 : 0);
