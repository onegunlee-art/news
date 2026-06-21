<?php
/**
 * P2-A1 — 편별 추출·검수 집계 리포트
 *
 * Usage:
 *   php tools/edu_hinge_batch_report.php 196 150 371 288 220
 *   php tools/edu_hinge_batch_report.php 196 150 371 288 220 --write-md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

$writeMd = in_array('--write-md', $argv ?? [], true);
$ids = array_values(array_filter($argv ?? [], static fn ($a) => is_numeric($a)));
$ids = array_map('intval', $ids);

if ($ids === []) {
    fwrite(STDERR, "Usage: php tools/edu_hinge_batch_report.php <news_id>... [--write-md]\n");
    exit(1);
}

/** @return array<string, mixed>|null */
function batchLatestReview(int $newsId, array $reviews): ?array
{
    $latest = null;
    foreach ($reviews as $r) {
        if ((int) ($r['news_id'] ?? 0) !== $newsId) {
            continue;
        }
        $latest = $r;
    }
    return $latest;
}

/** @return array{bucket: string, label: string} */
function batchClassify(?array $extraction, ?array $review): array
{
    if ($extraction === null) {
        return ['bucket' => 'no_extraction', 'label' => '추출 없음'];
    }
    if ($review === null) {
        return ['bucket' => 'no_review', 'label' => '검수 없음'];
    }

    $conf = strtolower(trim((string) ($review['llm_confidence'] ?? $extraction['confidence'] ?? '')));
    $action = $review['review_action'] ?? '';
    $autoPass = in_array($conf, ['high', 'medium'], true);
    $needsReview = $conf === '' || $conf === 'low';

    if ($autoPass && $action === 'approve') {
        return ['bucket' => 'gate_hit_pass', 'label' => 'high/medium + 승인 ✓'];
    }
    if ($autoPass && $action === 'edit') {
        return ['bucket' => 'false_pass', 'label' => 'high/medium + 수정 ⚠ 과신'];
    }
    if ($needsReview && $action === 'edit') {
        return ['bucket' => 'gate_hit_flag', 'label' => 'low/null + 수정 ✓'];
    }
    if ($needsReview && $action === 'approve') {
        return ['bucket' => 'false_flag', 'label' => 'low/null + 승인 (과소)'];
    }
    return ['bucket' => 'other', 'label' => '기타'];
}

$reviews = eduHingeLoadReviews();
$rows = [];
$counts = [
    'approve' => 0,
    'edit' => 0,
    'no_review' => 0,
    'gate_hit_pass' => 0,
    'false_pass' => 0,
    'gate_hit_flag' => 0,
    'false_flag' => 0,
    'low_confidence' => 0,
];

foreach ($ids as $nid) {
    $ext = eduHingeLoadExtraction($nid);
    $rev = batchLatestReview($nid, $reviews);
    $class = batchClassify($ext, $rev);

    if ($rev !== null) {
        if (($rev['review_action'] ?? '') === 'approve') {
            $counts['approve']++;
        } elseif (($rev['review_action'] ?? '') === 'edit') {
            $counts['edit']++;
        }
    } else {
        $counts['no_review']++;
    }

    if (isset($counts[$class['bucket']])) {
        $counts[$class['bucket']]++;
    }

    $conf = $ext['confidence'] ?? null;
    if ($conf === null || $conf === '' || $conf === 'low') {
        $counts['low_confidence']++;
    }

    $rows[] = [
        'news_id' => $nid,
        'title' => $ext['title'] ?? '(no extraction)',
        'hinge' => $ext['hinge'] ?? null,
        'confidence' => $conf,
        'needs_review' => $ext['needs_review'] ?? null,
        'review_action' => $rev['review_action'] ?? null,
        'edited_fields' => $rev['edited_fields'] ?? [],
        'class' => $class,
    ];
}

$reviewed = $counts['approve'] + $counts['edit'];
$accuracy = $reviewed > 0
    ? round(100 * $counts['approve'] / $reviewed, 1)
    : null;
$gateHits = $counts['gate_hit_pass'] + $counts['gate_hit_flag'];
$gateTotal = $reviewed;
$gateAccuracy = $gateTotal > 0 ? round(100 * $gateHits / $gateTotal, 1) : null;

echo "=== P2-A1 Batch Report ===\n";
echo 'Generated: ' . date('Y-m-d H:i:s') . "\n";
echo 'IDs: ' . implode(', ', $ids) . "\n\n";

foreach ($rows as $r) {
    echo "[{$r['news_id']}] {$r['title']}\n";
    echo "  confidence: " . ($r['confidence'] ?? 'null') . ' | needs_review: ' . json_encode($r['needs_review']) . "\n";
    echo '  review: ' . ($r['review_action'] ?? '(none)') . "\n";
    if ($r['edited_fields'] !== []) {
        echo '  edited: ' . implode(', ', $r['edited_fields']) . "\n";
    }
    echo "  → {$r['class']['label']}\n";
    if ($r['hinge'] !== null) {
        echo '  hinge: ' . mb_substr((string) $r['hinge'], 0, 100) . "\n";
    }
    echo "\n";
}

echo "--- Summary ---\n";
echo "Reviewed: {$reviewed}/" . count($ids) . " (approve {$counts['approve']}, edit {$counts['edit']})\n";
echo 'Approval rate: ' . ($accuracy !== null ? "{$accuracy}%" : '—') . "\n";
echo "False pass (high/med + edit): {$counts['false_pass']} ← **과신**\n";
echo "False flag (low/null + approve): {$counts['false_flag']}\n";
echo "Gate accuracy: " . ($gateAccuracy !== null ? "{$gateHits}/{$gateTotal} = {$gateAccuracy}%" : '—') . "\n";
echo "low/null confidence extractions: {$counts['low_confidence']}/" . count($ids) . "\n";

if ($writeMd) {
    eduHingeEnsureDirs();
    $md = "# P2-A1 Fresh Batch Report\n\n";
    $md .= '> ' . date('Y-m-d H:i:s') . "\n\n";
    $md .= '**IDs:** ' . implode(', ', $ids) . "\n\n";
    $md .= "## 편별\n\n";
    $md .= "| ID | confidence | needs_review | 검수 | 판정 | hinge (앞 80자) |\n";
    $md .= "|----|------------|--------------|------|------|----------------|\n";
    foreach ($rows as $r) {
        $hinge = mb_substr((string) ($r['hinge'] ?? ''), 0, 80);
        $hinge = str_replace('|', '\\|', $hinge);
        $md .= '| ' . $r['news_id'] . ' | ' . ($r['confidence'] ?? 'null') . ' | '
            . json_encode($r['needs_review']) . ' | ' . ($r['review_action'] ?? '—') . ' | '
            . $r['class']['label'] . ' | ' . $hinge . " |\n";
    }
    $md .= "\n## 총평\n\n";
    $md .= "- **승인률:** {$counts['approve']}/{$reviewed} (" . ($accuracy ?? '—') . "%)\n";
    $md .= "- **과신 (high/med → 수정):** {$counts['false_pass']}\n";
    $md .= "- **과소 (low/null → 승인):** {$counts['false_flag']}\n";
    $md .= "- **게이트 적중:** " . ($gateAccuracy ?? '—') . "%\n";
    $md .= "- **low/null 추출:** {$counts['low_confidence']}/" . count($ids) . "\n\n";
    $md .= "### 본인 메모 (검수 시 기록)\n\n";
    foreach ($ids as $nid) {
        $md .= "- [ ] {$nid}: \n";
    }

    $path = eduHingeProjectRoot() . '/docs/P2_HINGE_A1_FRESH_RESULT.md';
    file_put_contents($path, $md);
    echo "\nWrote {$path}\n";
}
