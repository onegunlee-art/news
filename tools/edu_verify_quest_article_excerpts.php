<?php
/**
 * Verify quest payload article excerpt lengths (live Supabase)
 *
 * Usage: php tools/edu_verify_quest_article_excerpts.php Q-LENS-NUKE-001
 */
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/edu_verify_quest_article_excerpts.php <quest_code>\n");
    exit(1);
}

$code = $argv[1];
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';

$q = eduLoadQuestByCode($code);
if ($q === null) {
    fwrite(STDERR, "quest not found: {$code}\n");
    exit(1);
}

$p = eduPublicQuestPayload($q);
echo "=== {$code} article excerpts ===\n\n";
foreach ($p['articles'] as $a) {
    $excerptLen = mb_strlen($a['excerpt'] ?? '');
    $whyLen = mb_strlen($a['why_important'] ?? '');
    echo "{$a['news_id']} | {$a['title']}\n";
    echo "  excerpt_len={$excerptLen} why_len={$whyLen}\n";
}
