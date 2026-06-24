<?php
/**
 * 196·288 axis_guide 퀘스트 일괄 seed (live_at=null)
 *
 * Usage:
 *   php tools/edu_seed_axis_guide_196_288.php --dry-run
 *   php tools/edu_seed_axis_guide_196_288.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = !in_array('--apply', $argv ?? [], true);
$drafts = [
    'docs/hinge_quest_drafts/AUTO-196-min.json',
    'docs/hinge_quest_drafts/AUTO-288-min.json',
];

echo "=== Seed axis_guide 196 + 288 ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n\n";

$exit = 0;
foreach ($drafts as $rel) {
    $path = $root . '/' . $rel;
    echo "--- {$rel} ---\n";
    $cmd = 'php ' . escapeshellarg($root . '/tools/seed_hinge_auto_quest.php')
        . ($dryRun ? '' : ' --apply')
        . ' --input=' . escapeshellarg($rel);
    passthru($cmd, $code);
    if ($code !== 0) {
        $exit = $code;
    }
    echo "\n";
}

if (!$dryRun) {
    echo "EC2 backfill (excerpt/snippet):\n";
    echo "  php tools/edu_backfill_quest_article_snapshots.php --quest-code=Q-AUTO-IRAN-196\n";
    echo "  php tools/edu_backfill_quest_article_snapshots.php --quest-code=Q-AUTO-YOUTH-288\n";
    echo "\nVerify:\n";
    echo "  php tools/edu_coach_guide_test.php\n";
    echo "  php tools/edu_multiuser_separation_test.php\n";
}

exit($exit);
