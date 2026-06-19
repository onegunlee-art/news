<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$ids = array_map('intval', array_slice($argv, 1));
if ($ids === []) {
    $ids = [196, 437, 152];
}

$s = eduSupabase();
foreach ($ids as $nid) {
    $j = $s->select('judgement_records', 'news_id=eq.' . $nid . '&order=created_at.desc', 1);
    $row = $j[0] ?? null;
    if ($row === null) {
        echo "news_id={$nid}: no judgement_records\n";
        continue;
    }
    $human = is_string($row['human_output'] ?? null)
        ? json_decode($row['human_output'], true)
        : ($row['human_output'] ?? []);
    $ai = is_string($row['ai_output'] ?? null)
        ? json_decode($row['ai_output'], true)
        : ($row['ai_output'] ?? []);
    $why = mb_strlen(trim((string) ($human['why_important'] ?? '')));
    $nar = mb_strlen(trim((string) ($human['narration'] ?? '')));
    $kp = is_array($ai['key_points'] ?? null) ? count($ai['key_points']) : 0;
    echo "news_id={$nid}: why={$why} narration={$nar} key_points={$kp}\n";
}
