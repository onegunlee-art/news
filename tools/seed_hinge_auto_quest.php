<?php
/**
 * P2-A3 — 경첩 자동 퀘스트 edu_* seed (격리: edu_daily_quests + edu_quest_articles only)
 *
 * Usage:
 *   php tools/seed_hinge_auto_quest.php --dry-run
 *   php tools/seed_hinge_auto_quest.php --apply
 *   php tools/seed_hinge_auto_quest.php --apply --set-live
 *   php tools/seed_hinge_auto_quest.php --apply --input=docs/hinge_quest_drafts/AUTO-630-min.json
 *
 * Rollback: php tools/edu_hinge_auto_quest_remove.php --apply --quest-code=Q-AUTO-NUKE-630
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/eduHingeQuestMap.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';

use Agents\Services\SupabaseService;

const EDU_HINGE_AUTO_QUEST_CODE = 'Q-AUTO-NUKE-630';
const EDU_MANUAL_NUKE_QUEST_CODE = 'Q-NUKE-AXIS-630';

/** edu_* WRITE 허용 테이블 — 이 목록 밖은 스크립트가 쓰지 않음 */
const EDU_HINGE_SEED_WRITE_TABLES = [
    'edu_daily_quests',
    'edu_quest_articles',
];

$dryRun = !in_array('--apply', $argv ?? [], true);
$setLive = in_array('--set-live', $argv ?? [], true);
$inputPath = $root . '/docs/hinge_quest_drafts/AUTO-630-min.json';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--input=')) {
        $inputPath = str_starts_with(substr($arg, 8), '/') || preg_match('#^[A-Za-z]:#', substr($arg, 8))
            ? substr($arg, 8)
            : $root . '/' . substr($arg, 8);
    }
}

if (!is_file($inputPath)) {
    fwrite(STDERR, "Missing draft: {$inputPath}\n");
    fwrite(STDERR, "Run: php tools/edu_hinge_quest_map.php 630 --write\n");
    exit(1);
}

$draft = json_decode((string) file_get_contents($inputPath), true);
if (!is_array($draft)) {
    fwrite(STDERR, "Invalid JSON: {$inputPath}\n");
    exit(1);
}

$draft['quest_code'] = EDU_HINGE_AUTO_QUEST_CODE;
$hints = is_array($draft['hammer_hints'] ?? null) ? $draft['hammer_hints'] : [];
$hints = eduCoachGuideAttachHints($hints);
$shared = trim((string) ($hints['shared_conclusion'] ?? ''));

$row = [
    'quest_code' => EDU_HINGE_AUTO_QUEST_CODE,
    'quest_title' => $draft['quest_title'] ?? "핵 억지의 '기묘한' 패배 (자동)",
    'grade_band' => 'middle',
    'status' => 'approved',
    'manual_arc' => 'ARC-NUKE-DETERRENCE-AUTO',
    'pro_line' => trim((string) ($draft['pro_line'] ?? '')) !== ''
        ? $draft['pro_line']
        : '핵무기가 있으면 재래식 공격과 전쟁 확대를 막을 수 있다',
    'con_line' => trim((string) ($draft['con_line'] ?? '')) !== ''
        ? $draft['con_line']
        : ($shared !== '' ? $shared : '핵만으로는 드론·재래식 공격까지 막기 어렵다'),
    'alignment_summary' => $draft['alignment_summary'] ?? null,
    'conflict_summary' => $draft['conflict_summary'] ?? '',
    'hammer_hints' => $hints,
    'pilot_priority' => null,
    'live_at' => null,
    'expires_at' => null,
    'scores' => [
        'source' => 'p2-a3-hinge-auto',
        'hinge_news_id' => 630,
        'mapper_version' => $hints['_meta']['mapper_version'] ?? 'p2-a2-v1',
    ],
];

$articles = $draft['articles'] ?? [
    [
        'news_id' => 630,
        'role' => 'primary',
        'title' => $row['quest_title'],
        'gist_url' => 'https://www.thegist.co.kr/news/630',
    ],
];

if ($setLive) {
    $row['live_at'] = date('c');
    $row['expires_at'] = date('c', strtotime('+7 days'));
}

echo "=== P2-A3 Hinge Auto Quest Seed ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'quest_code: ' . EDU_HINGE_AUTO_QUEST_CODE . "\n";
echo 'WRITE tables: ' . implode(', ', EDU_HINGE_SEED_WRITE_TABLES) . " only\n";
echo 'manual coexist: ' . EDU_MANUAL_NUKE_QUEST_CODE . " (never updated)\n";
echo "pilot_priority: null (edu_quest_drop cron A/B 제외 — live는 --set-live 또는 수동)\n";
echo 'live_at: ' . ($row['live_at'] ?? 'null') . ($setLive ? ' (--set-live)' : '') . "\n\n";

if ($dryRun) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\nArticles: " . count($articles) . "\n";
    echo "\nNext: php tools/seed_hinge_auto_quest.php --apply\n";
    exit(0);
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$manualBefore = $supabase->select(
    'edu_daily_quests',
    'quest_code=eq.' . rawurlencode(EDU_MANUAL_NUKE_QUEST_CODE),
    1
);
$manualId = $manualBefore[0]['id'] ?? null;
$manualTitle = $manualBefore[0]['quest_title'] ?? null;

$existing = $supabase->select(
    'edu_daily_quests',
    'quest_code=eq.' . rawurlencode(EDU_HINGE_AUTO_QUEST_CODE),
    1
);

if (!empty($existing[0]['id'])) {
    $questId = $existing[0]['id'];
    $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $row);
    echo "Updated quest " . EDU_HINGE_AUTO_QUEST_CODE . " ({$questId})\n";
} else {
    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        fwrite(STDERR, 'Insert failed: ' . $supabase->getLastError() . "\n");
        exit(1);
    }
    $questId = $inserted[0]['id'];
    echo "Inserted quest " . EDU_HINGE_AUTO_QUEST_CODE . " ({$questId})\n";
}

$supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
$sort = 0;
foreach ($articles as $article) {
    $supabase->insert('edu_quest_articles', [
        'quest_id' => $questId,
        'news_id' => (int) $article['news_id'],
        'role' => $article['role'] ?? 'primary',
        'sort_order' => $sort++,
        'title' => $article['title'] ?? null,
        'gist_url' => $article['gist_url'] ?? null,
    ]);
}
echo 'Synced ' . count($articles) . " article(s)\n";

$manualAfter = $supabase->select(
    'edu_daily_quests',
    'quest_code=eq.' . rawurlencode(EDU_MANUAL_NUKE_QUEST_CODE),
    1
);
if ($manualId !== null && ($manualAfter[0]['id'] ?? null) === $manualId
    && ($manualAfter[0]['quest_title'] ?? '') === $manualTitle) {
    echo "\nIsolation OK: " . EDU_MANUAL_NUKE_QUEST_CODE . " unchanged\n";
} elseif ($manualId === null) {
    echo "\nNote: " . EDU_MANUAL_NUKE_QUEST_CODE . " not in DB (coexist N/A)\n";
} else {
    fwrite(STDERR, "WARN: manual quest row may have changed — verify\n");
}

$verify = $supabase->select(
    'edu_daily_quests',
    'quest_code=eq.' . rawurlencode(EDU_HINGE_AUTO_QUEST_CODE),
    1
);
$q = $verify[0] ?? [];
echo "\nVerify:\n";
echo '  id: ' . ($q['id'] ?? '') . "\n";
echo '  title: ' . ($q['quest_title'] ?? '') . "\n";
echo '  mode: ' . (($q['hammer_hints']['mode'] ?? '') ?: '?') . "\n";
echo '  quest_frame: ' . (($q['hammer_hints']['quest_frame'] ?? '') ?: '?') . "\n";
echo '  live_at: ' . ($q['live_at'] ?? 'null') . "\n";

echo "\nNext:\n";
echo "  php tools/edu_backfill_quest_article_snapshots.php --quest-code=" . EDU_HINGE_AUTO_QUEST_CODE . "\n";
echo "  php tools/edu_hinge_a3_verify.php\n";
echo "  php tools/edu_hinge_auto_quest_e2e_smoke.php --live\n";
echo "Rollback: php tools/edu_hinge_auto_quest_remove.php --apply\n";
