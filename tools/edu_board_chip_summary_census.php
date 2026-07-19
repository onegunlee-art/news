<?php
/**
 * emit_card.text 전수 → 칩 요약 품질 검증 (eduBoardChipSummary.ts 미러)
 * Usage: php tools/edu_board_chip_summary_census.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);

function stripBoardChipPrefix(string $text): string
{
    return preg_replace('/^[①②③④⑤⑥]\s*\S+:\s*/u', '', trim($text)) ?? trim($text);
}

function extractQuotes(string $text): array
{
    preg_match_all('/[\'\'""]([^\'\'""]+)[\'\'""]/u', $text, $m);
    return array_values(array_filter(array_map('trim', $m[1] ?? [])));
}

function tailWords(string $text, int $count): string
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === []) {
        return '';
    }
    if (count($words) <= $count) {
        return implode(' ', $words);
    }

    return implode(' ', array_slice($words, -$count));
}

function capChipText(string $text, int $maxLen = 10): string
{
    $trimmed = trim($text);
    if (mb_strlen($trimmed) <= $maxLen) {
        return $trimmed;
    }

    return mb_substr($trimmed, 0, $maxLen - 1) . '…';
}

function lastSentence(string $body): string
{
    $parts = preg_split('/(?<=[。!?？.])\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts)));
    if ($parts === []) {
        return $body;
    }

    return $parts[count($parts) - 1];
}

function lastClause(string $sentence): string
{
    $parts = preg_split('/[,，·—]|(?:\s+(?:되|지만|라도)\s+)/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts)));
    if ($parts === []) {
        return $sentence;
    }

    return $parts[count($parts) - 1];
}

function summarizeBoardChipText(string $text, string $labelFallback): string
{
    $body = stripBoardChipPrefix($text);
    if ($body === '') {
        return $labelFallback;
    }

    $focus = lastSentence($body);
    $quotesInBody = extractQuotes($body);
    $quotesInFocus = extractQuotes($focus);

    if ($quotesInFocus === [] && count($quotesInBody) === 1) {
        $quoted = capChipText($quotesInBody[0]);
        if (mb_strlen(preg_replace('/…$/u', '', $quoted)) >= 4) {
            return $quoted;
        }
    }

    $clause = lastClause($focus);
    $candidate = tailWords($clause, 3);
    if (mb_strlen(preg_replace('/…$/u', '', $candidate)) < 4) {
        $candidate = tailWords($clause, 2);
    }
    if (mb_strlen(preg_replace('/…$/u', '', $candidate)) < 4) {
        $candidate = $clause;
    }

    $candidate = capChipText($candidate);
    if (mb_strlen(preg_replace('/…$/u', '', $candidate)) < 4) {
        return $labelFallback;
    }

    return $candidate;
}

function gradeBoardChipSummary(string $original, string $summary, string $label): string
{
    if ($summary === $label) {
        return 'fallback';
    }
    if (mb_strlen($summary) < 4) {
        return 'fallback';
    }
    $core = preg_replace('/…$/u', '', $summary) ?? $summary;
    if (preg_match('/(?:한다|본다|있다|없다|인가|할까|될까|보기|골라)$/u', $core)) {
        return 'weak';
    }
    if (preg_match('/[?？]$/u', $core)) {
        return 'weak';
    }

    return 'good';
}

function inferLayerLabel(string $text): string
{
    if (preg_match('/^①/u', $text)) {
        return '입장';
    }
    if (preg_match('/^②/u', $text)) {
        return '근거';
    }
    if (preg_match('/^③/u', $text)) {
        return '깊이';
    }
    if (preg_match('/^④/u', $text)) {
        return '반론';
    }
    if (preg_match('/^⑤/u', $text)) {
        return '재정립';
    }
    if (preg_match('/^⑥/u', $text)) {
        return '종합';
    }

    return '생각';
}

function walkEmitTexts(array $node, callable $onText): void
{
    if (isset($node['emit_card']['text'])) {
        $onText(trim((string) $node['emit_card']['text']));
    }
    foreach ($node as $value) {
        if (is_array($value)) {
            walkEmitTexts($value, $onText);
        }
    }
}

$files = glob($root . '/docs/coach_scripts/*_narrative_v2.json') ?: [];
$rows = [];
$seen = [];

foreach ($files as $path) {
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        continue;
    }
    walkEmitTexts($raw, static function (string $text) use (&$rows, &$seen, $path): void {
        if ($text === '' || isset($seen[$text])) {
            return;
        }
        $seen[$text] = true;
        $label = inferLayerLabel($text);
        $summary = summarizeBoardChipText($text, $label);
        $grade = gradeBoardChipSummary($text, $summary, $label);
        $rows[] = [
            'file' => basename($path),
            'layer' => $label,
            'text' => $text,
            'summary' => $summary,
            'grade' => $grade,
        ];
    });
}

usort($rows, static fn (array $a, array $b): int => [$a['file'], $a['layer'], $a['text']] <=> [$b['file'], $b['layer'], $b['text']]);

$counts = ['good' => 0, 'weak' => 0, 'fallback' => 0];
foreach ($rows as $row) {
    $counts[$row['grade']]++;
}
$total = count($rows);

echo "=== edu board chip summary census ===\n\n";
echo 'unique_emit_texts=' . $total . "\n";
echo 'good=' . $counts['good'] . ' (' . round($counts['good'] / max(1, $total) * 100, 1) . "%)\n";
echo 'weak=' . $counts['weak'] . ' (' . round($counts['weak'] / max(1, $total) * 100, 1) . "%)\n";
echo 'fallback=' . $counts['fallback'] . ' (' . round($counts['fallback'] / max(1, $total) * 100, 1) . "%)\n\n";

$byLayer = [];
foreach ($rows as $row) {
    $byLayer[$row['layer']][$row['grade']] = ($byLayer[$row['layer']][$row['grade']] ?? 0) + 1;
}
echo "by_layer:\n";
foreach (['입장', '근거', '깊이', '반론', '재정립', '종합', '생각'] as $layer) {
    if (!isset($byLayer[$layer])) {
        continue;
    }
    $layerTotal = array_sum($byLayer[$layer]);
    $layerWeak = ($byLayer[$layer]['weak'] ?? 0) + ($byLayer[$layer]['fallback'] ?? 0);
    echo sprintf(
        "  %s: total=%d weak+fallback=%d (%.1f%%)\n",
        $layer,
        $layerTotal,
        $layerWeak,
        $layerWeak / max(1, $layerTotal) * 100
    );
}

echo "\n--- weak/fallback samples (max 15) ---\n";
$shown = 0;
foreach ($rows as $row) {
    if ($row['grade'] === 'good') {
        continue;
    }
    echo sprintf("[%s] %s | %s\n  → %s\n", $row['grade'], $row['layer'], $row['file'], $row['text']);
    echo '  → ' . $row['summary'] . "\n";
    $shown++;
    if ($shown >= 15) {
        break;
    }
}

echo "\n--- full table (TSV) ---\n";
echo "grade\tlayer\tfile\tsummary\toriginal\n";
foreach ($rows as $row) {
    echo implode("\t", [
        $row['grade'],
        $row['layer'],
        $row['file'],
        $row['summary'],
        str_replace(["\t", "\n", "\r"], ' ', $row['text']),
    ]) . "\n";
}
