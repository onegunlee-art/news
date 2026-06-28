<?php
/**
 * Step 2 — gist 글 → edu_daily_quests draft 배치 생성 (멱등·안전)
 *
 * Usage:
 *   php tools/edu_quest_generate.php --dry-run --limit=30
 *   php tools/edu_quest_generate.php --apply --limit=30
 *   php tools/edu_quest_generate.php --apply --limit=30 --write-report
 *   php tools/edu_quest_generate.php --apply --ids=220,371,546
 *   php tools/edu_quest_generate.php --apply --limit=30 --cache-only
 *
 * Rollback (단일): php tools/edu_hinge_auto_quest_remove.php --apply --quest-code=Q-GIST-220
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduQuestGenerate.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/src/backend/Services/edu/EduQuestFactory.php';

use Agents\Services\SupabaseService;
use Services\Edu\EduQuestFactory;

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply || in_array('--dry-run', $argv ?? [], true);
$writeReport = in_array('--write-report', $argv ?? [], true);
$cacheOnly = in_array('--cache-only', $argv ?? [], true);
$includeBorderline = in_array('--include-borderline', $argv ?? [], true);

$limit = 30;
$offset = 0;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(50, (int) substr($arg, 8)));
    }
    if (str_starts_with($arg, '--offset=')) {
        $offset = max(0, (int) substr($arg, 9));
    }
}

$numericIds = array_values(array_filter($argv ?? [], static fn ($a) => is_numeric($a)));
$explicitIds = [];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--ids=')) {
        $explicitIds = array_map('intval', array_filter(explode(',', substr($arg, 6))));
    }
}

try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL required: {$e->getMessage()}\n");
    exit(1);
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$llm = $cacheOnly ? null : eduLlm();
$arcKeywords = EduQuestFactory::arcTopicKeywords();
$existingPrimary = eduQuestGenerateExistingPrimaryMap($supabase);

echo "=== Step 2 — Quest batch generate (draft) ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo "Limit: {$limit} · offset: {$offset}\n";
echo 'Extract: ' . ($cacheOnly ? 'cache-only' : 'LLM if cache missing') . "\n";
echo 'Filter: eligible' . ($includeBorderline ? ' + borderline' : '') . "\n";
echo 'Existing primary news_ids: ' . count($existingPrimary) . " (멱등 skip)\n";
echo "Protected manual seeds: never overwritten\n";
echo "Status: draft only (approved = separate step)\n\n";

if ($explicitIds !== []) {
    $scanIds = $explicitIds;
} elseif ($numericIds !== []) {
    $scanIds = array_map('intval', $numericIds);
} else {
    $scanIds = eduQuestGenerateCandidateNewsIds($pdo, $limit, $offset, $existingPrimary);
}

$created = [];
$skipped = [];
$errors = [];
$processed = 0;

foreach ($scanIds as $newsId) {
    if (count($created) >= $limit && $explicitIds === [] && $numericIds === []) {
        break;
    }

    $skipReason = eduQuestGenerateIsSkippable($newsId, $existingPrimary);
    if ($skipReason !== null) {
        $skipped[] = ['news_id' => $newsId, 'reason' => $skipReason];
        continue;
    }

    $meta = eduQuestFilterLoadArticleMeta($pdo, $newsId);
    if ($meta === null) {
        $errors[] = ['news_id' => $newsId, 'error' => 'article not found'];
        continue;
    }

    $ext = eduQuestGenerateEnsureExtractions($llm, $pdo, $newsId, !$cacheOnly);
    if ($ext['hinge'] === [] || !empty($ext['errors']) && ($ext['hinge']['hinge'] ?? null) === null) {
        $errors[] = ['news_id' => $newsId, 'error' => implode('; ', $ext['errors']) ?: 'hinge missing'];
        continue;
    }

    $hinge = $ext['hinge'];
    $class = eduQuestFilterClassify($hinge, $meta);
    $verdict = $class['verdict'] ?? '불가';
    if ($verdict === '불가') {
        $decl = $class['declaration'] ?? null;
        $reason = $decl !== null && ($decl['is_declaration'] ?? false)
            ? '선언문·연설: ' . implode(', ', $decl['reasons'] ?? [])
            : implode(', ', $class['reasons'] ?? []);
        $skipped[] = ['news_id' => $newsId, 'reason' => 'filter: 불가 — ' . $reason];
        continue;
    }
    if ($verdict === '경계' && !$includeBorderline) {
        $skipped[] = ['news_id' => $newsId, 'reason' => 'filter: 경계 (use --include-borderline)'];
        continue;
    }

    $draft = eduQuestGenerateBuildDraft($hinge, $meta, $ext['axis'], $arcKeywords);
    $persist = eduQuestGeneratePersistDraft($supabase, $pdo, $draft, $existingPrimary, $dryRun);

    $processed++;
    $row = eduQuestGenerateReviewRow($draft, $persist);
    $row['filter_verdict'] = $verdict;
    $row['filter_score'] = $class['score'] ?? 0;

    if (!empty($persist['skipped']) && ($persist['ok'] ?? false) === false && !str_contains((string) $persist['skipped'], 'dry-run')) {
        $skipped[] = ['news_id' => $newsId, 'reason' => (string) $persist['skipped']];
        continue;
    }
    if (!empty($persist['error'])) {
        $errors[] = ['news_id' => $newsId, 'error' => (string) $persist['error']];
        continue;
    }

    $created[] = $row;
    if (!$dryRun && !empty($persist['quest_id'])) {
        $existingPrimary[$newsId] = [
            'quest_code' => (string) ($persist['quest_code'] ?? ''),
            'quest_id' => (string) $persist['quest_id'],
            'status' => 'draft',
        ];
    }

    echo "[{$newsId}] " . ($dryRun ? 'WOULD CREATE' : 'CREATED') . ' '
        . ($draft['quest_code'] ?? '') . ' — '
        . mb_substr((string) ($draft['quest_title'] ?? ''), 0, 50) . "\n";
}

echo "\n=== Review list (검수용) ===\n";
echo str_pad('ID', 6) . str_pad('code', 16) . "제목 / 경첩\n";
echo str_repeat('─', 90) . "\n";
foreach ($created as $r) {
    echo str_pad((string) $r['news_id'], 6)
        . str_pad((string) $r['quest_code'], 16)
        . mb_substr((string) $r['title'], 0, 42) . "\n";
    echo str_repeat(' ', 22)
        . '경첩: ' . mb_substr((string) $r['hinge'], 0, 65) . "\n";
    echo str_repeat(' ', 22)
        . 'axes=' . $r['axes'] . ' · ' . $r['gist_url'] . "\n\n";
}

echo "=== Summary ===\n";
echo 'Created: ' . count($created) . ($dryRun ? ' (dry-run)' : '') . "\n";
echo 'Skipped: ' . count($skipped) . "\n";
echo 'Errors: ' . count($errors) . "\n";
echo "Processed candidates: {$processed}\n\n";

echo "=== 이원근 검수 ===\n";
echo "1. 위 draft " . count($created) . "개 중 5개 풀어봄 (경첩·코치·완주)\n";
echo "2. OK → php tools/edu_quest_generate_approve.php --apply --quest-code=Q-GIST-...\n";
echo "3. 멱등: 같은 명령 다시 실행 → created 0, skipped (already primary)\n";
echo "4. 기존 Q-NUKE-AXIS-630 등 수동 시드 untouched\n\n";

if ($writeReport) {
    $dir = $root . '/docs/quest_generate';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $stamp = date('Ymd_His');
    $mdPath = $dir . '/batch_' . $stamp . '.md';
    $jsonPath = $dir . '/batch_' . $stamp . '.json';

    $lines = [
        '# Quest generate batch — Step 2',
        '',
        'Generated: ' . date('c'),
        'Mode: ' . ($dryRun ? 'dry-run' : 'apply'),
        '',
        '| news_id | quest_code | axes | filter | title | hinge |',
        '|---------|------------|------|--------|-------|-------|',
    ];
    foreach ($created as $r) {
        $lines[] = sprintf(
            '| %d | %s | %d | %s | %s | %s |',
            $r['news_id'],
            $r['quest_code'],
            $r['axes'],
            $r['filter_verdict'] ?? '',
            str_replace('|', '/', mb_substr((string) $r['title'], 0, 40)),
            str_replace('|', '/', mb_substr((string) $r['hinge'], 0, 60))
        );
    }
    $lines[] = '';
    $lines[] = '## Sample URLs';
    foreach (array_slice($created, 0, 10) as $r) {
        $lines[] = '- [' . $r['quest_code'] . '](' . $r['gist_url'] . ')';
    }
    file_put_contents($mdPath, implode("\n", $lines) . "\n");

    file_put_contents($jsonPath, json_encode([
        'generated_at' => date('c'),
        'dry_run' => $dryRun,
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    echo "Wrote {$mdPath}\n";
    echo "Wrote {$jsonPath}\n";
}

if ($skipped !== []) {
    echo "\n--- Skipped (first 10) ---\n";
    foreach (array_slice($skipped, 0, 10) as $s) {
        echo "  {$s['news_id']}: {$s['reason']}\n";
    }
}

if ($errors !== []) {
    echo "\n--- Errors ---\n";
    foreach ($errors as $e) {
        echo "  {$e['news_id']}: {$e['error']}\n";
    }
    exit(count($created) > 0 ? 0 : 1);
}

exit(0);
