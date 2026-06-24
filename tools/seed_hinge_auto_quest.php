<?php
/**
 * P2-A3 — 경첩 자동 퀘스트 edu_* seed (격리: edu_daily_quests + edu_quest_articles only)
 *
 * Usage:
 *   php tools/seed_hinge_auto_quest.php --dry-run
 *   php tools/seed_hinge_auto_quest.php --apply
 *   php tools/seed_hinge_auto_quest.php --apply --set-live
 *   php tools/seed_hinge_auto_quest.php --dry-run --input=docs/hinge_quest_drafts/AUTO-630-min.json
 *   php tools/seed_hinge_auto_quest.php --dry-run --input=docs/hinge_quest_drafts/AUTO-150-min.json
 *
 * Rollback: php tools/edu_hinge_auto_quest_remove.php --apply --quest-code=Q-AUTO-DC-150
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/eduHingeQuestMap.php';
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';

use Agents\Services\SupabaseService;

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

$questCode = trim((string) ($draft['quest_code'] ?? ''));
if ($questCode === '') {
    fwrite(STDERR, "Draft missing quest_code: {$inputPath}\n");
    exit(1);
}

$articles = is_array($draft['articles'] ?? null) && $draft['articles'] !== []
    ? $draft['articles']
    : [['news_id' => 630, 'role' => 'primary']];
$newsId = (int) ($articles[0]['news_id'] ?? 0);
if ($newsId <= 0) {
    fwrite(STDERR, "Draft missing articles[0].news_id\n");
    exit(1);
}

$hints = is_array($draft['hammer_hints'] ?? null) ? $draft['hammer_hints'] : [];
$hints = eduCoachGuideAttachHints($hints, $newsId);
$shared = trim((string) ($hints['shared_conclusion'] ?? ''));
$hookShort = trim((string) ($hints['hook_short'] ?? ''));
$axes = $hints['_guide_axes'] ?? [];
$axis1Q = is_array($axes[0] ?? null) ? trim((string) ($axes[0]['core_question'] ?? '')) : '';
if ($hookShort !== '' && $axis1Q !== '' && eduCoachGuideTextsOverlap($hookShort, $axis1Q)) {
    fwrite(STDERR, "WARN: hook_short overlaps axis-1 core_question — 630-style duplicate intro risk\n");
    fwrite(STDERR, "  hook: {$hookShort}\n");
    fwrite(STDERR, "  axis1: {$axis1Q}\n");
}

[$defaultPro, $defaultCon, $manualArc] = eduHingeSeedDefaults($newsId, $shared, $hints);

$row = [
    'quest_code' => $questCode,
    'quest_title' => $draft['quest_title'] ?? "Quest {$newsId} (자동)",
    'grade_band' => 'middle',
    'status' => 'approved',
    'manual_arc' => $manualArc,
    'pro_line' => trim((string) ($draft['pro_line'] ?? '')) !== ''
        ? $draft['pro_line']
        : $defaultPro,
    'con_line' => trim((string) ($draft['con_line'] ?? '')) !== ''
        ? $draft['con_line']
        : $defaultCon,
    'alignment_summary' => $draft['alignment_summary'] ?? null,
    'conflict_summary' => $draft['conflict_summary'] ?? '',
    'hammer_hints' => $hints,
    'pilot_priority' => null,
    'live_at' => null,
    'expires_at' => null,
    'scores' => array_filter([
        'source' => 'p2-a3-hinge-auto',
        'hinge_news_id' => $newsId,
        'mapper_version' => $hints['_meta']['mapper_version'] ?? 'p2-a2-v1',
        'category' => match ($newsId) {
            196 => 'middle_east_iran',
            288 => 'society_youth',
            150 => 'ai_tech',
            default => null,
        },
    ], static fn ($v) => $v !== null),
];

if ($setLive) {
    $row['live_at'] = date('c');
    $row['expires_at'] = date('c', strtotime('+7 days'));
}

echo "=== P2-A3 Hinge Auto Quest Seed ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'input: ' . $inputPath . "\n";
echo 'quest_code: ' . $questCode . "\n";
echo 'news_id: ' . $newsId . "\n";
echo 'guide_axes: ' . count($axes) . "\n";
echo 'WRITE tables: ' . implode(', ', EDU_HINGE_SEED_WRITE_TABLES) . " only\n";
echo 'manual coexist: ' . EDU_MANUAL_NUKE_QUEST_CODE . " (never updated)\n";
echo "pilot_priority: null (edu_quest_drop cron A/B 제외 — live는 --set-live 또는 수동)\n";
echo 'live_at: ' . ($row['live_at'] ?? 'null') . ($setLive ? ' (--set-live)' : '') . "\n\n";

if ($dryRun) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\nArticles: " . count($articles) . "\n";
    echo "\nNext: php tools/seed_hinge_auto_quest.php --apply --input=" . basename(dirname($inputPath)) . '/' . basename($inputPath) . "\n";
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
    'quest_code=eq.' . rawurlencode($questCode),
    1
);

if (!empty($existing[0]['id'])) {
    $questId = $existing[0]['id'];
    $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $row);
    echo "Updated quest {$questCode} ({$questId})\n";
} else {
    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        fwrite(STDERR, 'Insert failed: ' . $supabase->getLastError() . "\n");
        exit(1);
    }
    $questId = $inserted[0]['id'];
    echo "Inserted quest {$questCode} ({$questId})\n";
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
    'quest_code=eq.' . rawurlencode($questCode),
    1
);
$q = $verify[0] ?? [];
echo "\nVerify:\n";
echo '  id: ' . ($q['id'] ?? '') . "\n";
echo '  title: ' . ($q['quest_title'] ?? '') . "\n";
echo '  mode: ' . (($q['hammer_hints']['mode'] ?? '') ?: '?') . "\n";
echo '  coach_mode: ' . (($q['hammer_hints']['coach_mode'] ?? '') ?: '?') . "\n";
echo '  guide_axes: ' . count($q['hammer_hints']['_guide_axes'] ?? []) . "\n";
echo '  quest_frame: ' . (($q['hammer_hints']['quest_frame'] ?? '') ?: '?') . "\n";
echo '  live_at: ' . ($q['live_at'] ?? 'null') . "\n";

echo "\nNext:\n";
echo "  php tools/edu_backfill_quest_article_snapshots.php --quest-code={$questCode}\n";
echo "  php tools/edu_hinge_a3_verify.php\n";
echo "Rollback: php tools/edu_hinge_auto_quest_remove.php --apply --quest-code={$questCode}\n";

/**
 * @param array<string, mixed> $hints
 * @return array{0: string, 1: string, 2: string}
 */
function eduHingeSeedDefaults(int $newsId, string $shared, array $hints): array
{
    $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];
    $sideA = trim((string) ($hinge['side_a'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? ''));

    return match ($newsId) {
        150 => [
            $sideA !== '' ? $sideA : 'AI 데이터센터가 전기요금을 올린다',
            $shared !== '' ? $shared : '전력망·시장 제약이 더 크고 DC는 투자 수요가 될 수 있다',
            'ARC-DC-POWER-AUTO',
        ],
        196 => [
            $sideA !== '' ? $sideA : '군사·외교 수단으로 이란 핵 위협을 제거·억제할 수 있다',
            $shared !== '' ? $shared : '세 가지 해법 모두 실행 비용·우라늄·지도부 문제로 어중간하다',
            'ARC-IRAN-NUKE-AUTO',
        ],
        288 => [
            $sideA !== '' ? $sideA : '청소년 AI 사용은 시간 제한이나 금지로 관리해야 한다',
            $shared !== '' ? $shared : '맥락에 따라 기회가 되기도 정서적 대체재가 되기도 한다',
            'ARC-SOCIETY-YOUTH',
        ],
        default => [
            $sideA !== '' ? $sideA : '핵무기가 있으면 재래식 공격과 전쟁 확대를 막을 수 있다',
            $shared !== '' ? $shared : ($sideB !== '' ? $sideB : '핵만으로는 드론·재래식 공격까지 막기 어렵다'),
            'ARC-NUKE-DETERRENCE-AUTO',
        ],
    };
}
