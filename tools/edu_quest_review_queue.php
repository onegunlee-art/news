<?php
/**
 * GIST EDU — Draft review queue: category별 1건 polish + optional approve
 *
 * Usage:
 *   php tools/edu_quest_review_queue.php
 *   php tools/edu_quest_review_queue.php --approve
 *   php tools/edu_quest_review_queue.php --approve --priority=B
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';

$doApprove = in_array('--approve', $argv ?? [], true);
$priority = 'B';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--priority=')) {
        $priority = strtoupper(substr($arg, 11));
        if (!in_array($priority, ['A', 'B', 'C'], true)) {
            $priority = 'B';
        }
    }
}

echo "=== EDU Quest Review Queue ===\n";
echo 'approve: ' . ($doApprove ? 'YES' : 'dry-run') . " priority={$priority}\n\n";

$supabase = eduSupabase();
$drafts = $supabase->select(
    'edu_daily_quests',
    'status=eq.draft&order=created_at.desc',
    100
) ?? [];

if ($drafts === []) {
    echo "No draft quests in queue\n";
    exit(0);
}

/** @var array<string, array<string, mixed>> */
$bestByCategory = [];

foreach ($drafts as $q) {
    $scores = $q['scores'] ?? [];
    if (is_string($scores)) {
        $scores = json_decode($scores, true) ?: [];
    }
    $cat = (string) ($scores['category'] ?? '');
    if ($cat === '') {
        $cat = eduQuestCategoryForArc((string) ($q['manual_arc'] ?? '')) ?? 'unmapped';
    }

    $articles = $supabase->select(
        'edu_quest_articles',
        'quest_id=eq.' . $q['id'] . '&order=sort_order.asc',
        10
    ) ?? [];

    $hasPrimary = false;
    foreach ($articles as $a) {
        if (($a['role'] ?? '') === 'primary') {
            $hasPrimary = true;
            break;
        }
    }
    $valid = count($articles) >= 3 && $hasPrimary && trim((string) ($q['conflict_summary'] ?? '')) !== '';

    $rank = count($articles) + ($valid ? 10 : 0);
    if (!isset($bestByCategory[$cat]) || ($bestByCategory[$cat]['_rank'] ?? 0) < $rank) {
        $bestByCategory[$cat] = array_merge($q, [
            '_rank' => $rank,
            '_articles' => $articles,
            '_valid' => $valid,
        ]);
    }
}

$approved = 0;
foreach ($bestByCategory as $catId => $q) {
    $label = eduQuestCategoryLabel($catId);
    echo "## {$label} ({$catId})\n";
    echo '  quest: ' . ($q['quest_code'] ?? '') . "\n";
    echo '  title: ' . ($q['quest_title'] ?? '') . "\n";
    echo '  arc: ' . ($q['manual_arc'] ?? '') . "\n";
    echo '  articles: ' . count($q['_articles'] ?? []) . ' valid=' . (($q['_valid'] ?? false) ? 'Y' : 'N') . "\n";

    if (!($q['_valid'] ?? false)) {
        echo "  SKIP: validation failed\n\n";
        continue;
    }

    $updates = [
        'grade_band' => ($q['grade_band'] ?? '') === 'high' ? 'middle' : ($q['grade_band'] ?? 'middle'),
        'pilot_priority' => $priority,
        'updated_at' => date('c'),
    ];
    $scores = $q['scores'] ?? [];
    if (is_string($scores)) {
        $scores = json_decode($scores, true) ?: [];
    }
    $scores['category'] = $catId;
    $scores['review_queue'] = date('c');
    $updates['scores'] = json_encode($scores, JSON_UNESCAPED_UNICODE);

    if ($doApprove) {
        $updates['status'] = 'approved';
        $supabase->update('edu_daily_quests', 'id=eq.' . $q['id'], $updates);
        echo "  => APPROVED (priority {$priority}, no live_at)\n\n";
        $approved++;
    } else {
        echo '  => would polish: grade_band=' . $updates['grade_band'] . " priority={$priority}\n";
        if ($doApprove) {
            echo "  => would APPROVE\n";
        }
        echo "\n";
    }
}

echo "=== Categories in queue: " . count($bestByCategory) . " | approved: {$approved} ===\n";
