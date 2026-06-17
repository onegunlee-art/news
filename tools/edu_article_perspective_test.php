<?php
/**
 * GIST EDU — article media_perspective helper smoke test
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';

$quest = eduLoadQuestByCode('Q-G09-DEC-2022');
if ($quest === null) {
    fwrite(STDERR, "Quest Q-G09-DEC-2022 not found\n");
    exit(1);
}

$supabase = eduSupabase();
$articles = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $quest['id'] . '&order=sort_order.asc',
    20
) ?? [];

$fail = 0;
foreach ($articles as $article) {
    $newsId = (int) ($article['news_id'] ?? 0);
    $row = eduPublicArticleRow($quest, $article);
    $perspective = (string) ($row['media_perspective'] ?? '');
    $excerpt = trim((string) ($row['excerpt'] ?? ''));
    $outlet = trim((string) ($row['source_outlet'] ?? ''));

    echo "news_id={$newsId}\n";
    echo "  outlet: " . ($outlet !== '' ? $outlet : '(empty)') . "\n";
    echo "  perspective: {$perspective}\n";
    echo '  excerpt: ' . ($excerpt !== '' ? mb_substr($excerpt, 0, 60) . '…' : '(empty)') . "\n";

    if ($perspective === '') {
        echo "  FAIL: empty media_perspective\n";
        $fail++;
    }
}

if ($fail > 0) {
    fwrite(STDERR, "\nGATE FAIL: {$fail} article(s) missing perspective\n");
    exit(1);
}

echo "\nGATE PASS: media_perspective for all G09 articles\n";
exit(0);
