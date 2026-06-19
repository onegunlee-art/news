<?php
/**
 * P2-A1 — confidence 게이트 vs 본인 검수 일치율
 *
 * Usage:
 *   php tools/edu_hinge_gate_stats.php
 *   php tools/edu_hinge_gate_stats.php --write-md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

$writeMd = in_array('--write-md', $argv ?? [], true);

/** @return array<string, mixed>|null */
function hingeGateClassifyReview(array $review): ?array
{
    $conf = strtolower(trim((string) ($review['llm_confidence'] ?? '')));
    $action = $review['review_action'] ?? '';
    if (!in_array($action, ['approve', 'edit'], true)) {
        return null;
    }

    $autoPass = in_array($conf, ['high', 'medium'], true);
    $needsReview = $conf === '' || $conf === 'low' || ($review['llm_confidence'] ?? null) === null;

    if ($autoPass && $action === 'approve') {
        $bucket = 'gate_hit_pass';
    } elseif ($autoPass && $action === 'edit') {
        $bucket = 'false_pass';
    } elseif ($needsReview && $action === 'edit') {
        $bucket = 'gate_hit_flag';
    } elseif ($needsReview && $action === 'approve') {
        $bucket = 'false_flag';
    } else {
        $bucket = 'other';
    }

    return [
        'news_id' => (int) ($review['news_id'] ?? 0),
        'llm_confidence' => $review['llm_confidence'],
        'review_action' => $action,
        'bucket' => $bucket,
        'reviewed_at' => $review['reviewed_at'] ?? '',
    ];
}

/** Latest review per news_id (most recent wins). */
function hingeGateLatestReviews(array $reviews): array
{
    $byId = [];
    foreach ($reviews as $review) {
        $nid = (int) ($review['news_id'] ?? 0);
        if ($nid <= 0) {
            continue;
        }
        $byId[$nid] = $review;
    }
    return $byId;
}

$reviews = eduHingeLoadReviews();
$latest = hingeGateLatestReviews($reviews);

$counts = [
    'gate_hit_pass' => 0,
    'false_pass' => 0,
    'gate_hit_flag' => 0,
    'false_flag' => 0,
    'other' => 0,
];

$rows = [];
foreach ($latest as $review) {
    $classified = hingeGateClassifyReview($review);
    if ($classified === null) {
        continue;
    }
    $counts[$classified['bucket']]++;
    $rows[] = $classified;
}

$total = array_sum($counts);
$gateHits = $counts['gate_hit_pass'] + $counts['gate_hit_flag'];
$gateMiss = $counts['false_pass'] + $counts['false_flag'];
$accuracy = $total > 0 ? round(100 * $gateHits / $total, 1) : null;

$manifestPath = eduHingeExtractionsDir() . '/manifest.json';
$pendingReview = 0;
$extracted = 0;
if (is_file($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($manifest)) {
        foreach ($manifest as $row) {
            if (!is_array($row)) {
                continue;
            }
            $extracted++;
            $nid = (int) ($row['news_id'] ?? 0);
            if (!isset($latest[$nid]) && !empty($row['needs_review'])) {
                $pendingReview++;
            }
        }
    }
}

echo "=== P2-A1 Hinge Gate Stats ===\n";
echo 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";
echo "Reviews (latest per news_id): {$total}\n";
echo "Extractions in manifest: {$extracted}\n";
echo "Pending review (needs_review, no review yet): {$pendingReview}\n\n";

echo "| Bucket | Count | Meaning |\n";
echo "|--------|-------|----------|\n";
echo "| gate_hit_pass | {$counts['gate_hit_pass']} | high/medium → 승인 |\n";
echo "| false_pass | {$counts['false_pass']} | high/medium → 수정 (과신) |\n";
echo "| gate_hit_flag | {$counts['gate_hit_flag']} | low/null → 수정 |\n";
echo "| false_flag | {$counts['false_flag']} | low/null → 승인 (보수적 게이트) |\n";
echo "| other | {$counts['other']} | — |\n\n";

if ($accuracy !== null) {
    echo "Gate accuracy (hits / reviewed): {$gateHits}/{$total} = {$accuracy}%\n";
    echo "Gate miss (false pass + false flag): {$gateMiss}\n";
} else {
    echo "No reviews yet — run edu_hinge_review.php after extractions.\n";
}

if ($rows !== []) {
    echo "\nPer-article:\n";
    foreach ($rows as $r) {
        echo "  [{$r['news_id']}] conf={$r['llm_confidence']} action={$r['review_action']} → {$r['bucket']}\n";
    }
}

if ($writeMd) {
    eduHingeEnsureDirs();
    $md = "# P2-A1 Hinge Gate Stats\n\n";
    $md .= '> ' . date('Y-m-d H:i:s') . "\n\n";
    $md .= "| Metric | Value |\n|--------|-------|\n";
    $md .= "| Reviewed | {$total} |\n";
    $md .= "| Gate hits | {$gateHits} |\n";
    $md .= "| Gate miss | {$gateMiss} |\n";
    $md .= '| Accuracy | ' . ($accuracy !== null ? "{$accuracy}%" : '—') . " |\n\n";
    $md .= "## Buckets\n\n";
    $md .= "- gate_hit_pass: {$counts['gate_hit_pass']}\n";
    $md .= "- false_pass: {$counts['false_pass']}\n";
    $md .= "- gate_hit_flag: {$counts['gate_hit_flag']}\n";
    $md .= "- false_flag: {$counts['false_flag']}\n";

    $mdPath = dirname(eduHingeReviewsFile()) . '/gate_stats.md';
    file_put_contents($mdPath, $md);
    echo "\nWrote {$mdPath}\n";
}
