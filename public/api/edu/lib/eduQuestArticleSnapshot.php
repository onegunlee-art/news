<?php
/**
 * GIST EDU — edu_quest_articles excerpt / why_important backfill helpers
 */
declare(strict_types=1);

function eduSnapshotStripPlain(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
}

/** @return list<string> */
function eduSnapshotSplitSentences(string $text, int $max = 5): array
{
    $text = eduSnapshotStripPlain($text);
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
function eduSnapshotDecodeJson($raw): array
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
function eduSnapshotKeyPointsFromJudgement(array $ai): array
{
    $kps = $ai['key_points'] ?? null;
    if (!is_array($kps) || $kps === []) {
        return [];
    }
    $out = [];
    foreach ($kps as $kp) {
        $line = eduSnapshotStripPlain((string) $kp);
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
function eduSnapshotBuildExcerptLines(array $sources): array
{
    $keyPoints = eduSnapshotKeyPointsFromJudgement($sources['ai'] ?? []);
    if ($keyPoints !== []) {
        return $keyPoints;
    }
    foreach (['human_narration', 'news_narration', 'human_content'] as $field) {
        $lines = eduSnapshotSplitSentences((string) ($sources[$field] ?? ''), 5);
        if ($lines !== []) {
            return $lines;
        }
    }
    $why = eduSnapshotStripPlain((string) ($sources['why_important'] ?? ''));
    if ($why !== '') {
        return eduSnapshotSplitSentences($why, 3);
    }
    return [];
}

function eduSnapshotFormatExcerpt(array $lines): string
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

function eduSnapshotLoadNewsRow(?PDO $pdo, int $newsId): ?array
{
    if ($pdo === null) {
        return null;
    }
    $cols = ['id', 'title', 'status'];
    foreach (['narration', 'why_important', 'description', 'content', 'source', 'original_source', 'published_at'] as $c) {
        $cols[] = $c;
    }
    try {
        $existing = [];
        $st = $pdo->query('SHOW COLUMNS FROM news');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $existing[] = $row['Field'];
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
    } catch (Throwable $e) {
        return null;
    }
}

function eduSnapshotLoadJudgementRow($supabase, int $newsId): ?array
{
    $rows = $supabase->select(
        'judgement_records',
        'news_id=eq.' . $newsId . '&order=created_at.desc',
        1
    );
    return $rows[0] ?? null;
}

/**
 * @param array<string, mixed> $article edu_quest_articles row
 * @return array{status: string, patch?: array<string, mixed>}
 */
function eduBackfillQuestArticleSnapshot($supabase, ?PDO $pdo, array $article, bool $dryRun = false): array
{
    $newsId = (int) ($article['news_id'] ?? 0);
    $articleId = (string) ($article['id'] ?? '');

    $currentExcerpt = trim((string) ($article['excerpt'] ?? ''));
    $currentWhy = trim((string) ($article['why_important'] ?? ''));
    if ($currentExcerpt !== '' && $currentWhy !== '') {
        return ['status' => 'already_filled'];
    }

    $news = eduSnapshotLoadNewsRow($pdo, $newsId);
    $judgement = eduSnapshotLoadJudgementRow($supabase, $newsId);
    $human = $judgement !== null ? eduSnapshotDecodeJson($judgement['human_output'] ?? null) : [];
    $ai = $judgement !== null ? eduSnapshotDecodeJson($judgement['ai_output'] ?? null) : [];

    $whyImportant = eduSnapshotStripPlain((string) ($human['why_important'] ?? ''));
    if ($whyImportant === '' && $news !== null) {
        $whyImportant = eduSnapshotStripPlain((string) ($news['why_important'] ?? ''));
    }

    $narration = eduSnapshotStripPlain((string) ($human['narration'] ?? ''));
    if ($narration === '' && $news !== null) {
        $narration = eduSnapshotStripPlain((string) ($news['narration'] ?? $news['description'] ?? ''));
    }

    $sources = [
        'ai' => $ai,
        'human_narration' => $narration,
        'news_narration' => $news !== null
            ? eduSnapshotStripPlain((string) ($news['narration'] ?? $news['description'] ?? ''))
            : '',
        'human_content' => eduSnapshotStripPlain((string) ($human['content'] ?? '')),
        'why_important' => $whyImportant,
    ];

    $excerptLines = eduSnapshotBuildExcerptLines($sources);
    $excerpt = eduSnapshotFormatExcerpt($excerptLines);
    if ($excerpt === '' && $narration !== '') {
        $excerpt = mb_substr($narration, 0, 400);
    }

    if ($excerpt === '') {
        return ['status' => 'no_source'];
    }

    $sourceOutlet = $news !== null
        ? (string) ($news['original_source'] ?? $news['source'] ?? 'the gist')
        : 'the gist';
    $publishedAt = $news !== null ? ($news['published_at'] ?? null) : null;

    $patch = array_filter([
        'excerpt' => $currentExcerpt === '' ? $excerpt : null,
        'why_important' => $currentWhy === '' && $whyImportant !== '' ? $whyImportant : null,
        'source_outlet' => ($article['source_outlet'] ?? '') === '' && $sourceOutlet !== '' ? $sourceOutlet : null,
        'published_at' => empty($article['published_at']) && $publishedAt ? $publishedAt : null,
    ], fn ($v) => $v !== null && $v !== '');

    if ($patch === []) {
        return ['status' => 'nothing_to_patch'];
    }

    if ($dryRun) {
        return ['status' => 'would_update', 'patch' => $patch];
    }

    $supabase->update('edu_quest_articles', 'id=eq.' . $articleId, $patch);
    return ['status' => 'updated', 'patch' => $patch];
}
