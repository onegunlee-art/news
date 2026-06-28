<?php
/**
 * Step 1 — the gist 글 퀘스트 가능 거름망 검증 (READ-only)
 *
 * 경첩 추출 → 가능/불가/경계 분류. edu_daily_quests·본체 DB 쓰기 없음.
 *
 * Usage:
 *   php tools/edu_quest_filter_verify.php                    # 기본 샘플 ~24편
 *   php tools/edu_quest_filter_verify.php 630 196 150       # ID 지정
 *   php tools/edu_quest_filter_verify.php --sample=30       # MySQL에서 다양 샘플
 *   php tools/edu_quest_filter_verify.php --cache-only      # docs/hinge_extractions 캐시만
 *   php tools/edu_quest_filter_verify.php --strict          # eligible ≥65
 *   php tools/edu_quest_filter_verify.php --write-md        # docs/quest_filter_verify/report.md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$cacheOnly = in_array('--cache-only', $argv ?? [], true);
$writeMd = in_array('--write-md', $argv ?? [], true);
$strict = in_array('--strict', $argv ?? [], true);
$lenient = in_array('--lenient', $argv ?? [], true);
$eligibleMin = EDU_QUEST_FILTER_ELIGIBLE_DEFAULT;
$borderlineMin = EDU_QUEST_FILTER_BORDERLINE_DEFAULT;
if ($strict) {
    $eligibleMin = 65;
    $borderlineMin = 50;
} elseif ($lenient) {
    $eligibleMin = 45;
    $borderlineMin = 32;
}

$sampleCount = 24;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--sample=')) {
        $sampleCount = max(5, min(50, (int) substr($arg, 9)));
    }
}

$numericIds = array_values(array_filter($argv ?? [], static fn ($a) => is_numeric($a)));
$useAutoSample = in_array('--sample', $argv ?? [], true)
    || array_filter($argv ?? [], static fn ($a) => str_starts_with($a, '--sample=')) !== [];

$pdo = null;
$mysqlOptional = $cacheOnly && ($numericIds !== [] || in_array('--default-ids', $argv ?? [], true));
try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    if ($mysqlOptional || ($numericIds === [] && !$useAutoSample)) {
        echo "MySQL unavailable ({$e->getMessage()}) — metadata minimal, hinge from cache\n\n";
    } else {
        fwrite(STDERR, "MySQL required: {$e->getMessage()}\n");
        exit(1);
    }
}

if ($numericIds !== []) {
    $ids = array_map('intval', $numericIds);
} elseif ($pdo !== null && ($useAutoSample || !in_array('--default-ids', $argv ?? [], true))) {
    $ids = eduQuestFilterPickSampleFromMysql($pdo, $sampleCount);
} else {
    $ids = array_slice(array_values(array_unique(eduQuestFilterDefaultSampleIds())), 0, $sampleCount);
}

$ids = array_values(array_unique($ids));
if ($ids === []) {
    fwrite(STDERR, "No news IDs to verify\n");
    exit(1);
}

$llm = $cacheOnly ? null : eduLlm();

echo "=== Step 1 — Quest filter verify (거름망) ===\n";
echo 'Generated: ' . date('Y-m-d H:i:s') . "\n";
echo 'IDs: ' . count($ids) . ' — ' . implode(', ', $ids) . "\n";
echo "Mode: " . ($cacheOnly ? 'cache-only' : 'extract (LLM if no cache)') . "\n";
echo "Threshold: eligible≥{$eligibleMin}, borderline≥{$borderlineMin}\n";
echo "Conflict note: 기존 edu_daily_quests(수동 시드·curator draft)는 건드리지 않음. P2 hinge-first 파이프와 병행.\n\n";

$rows = [];
$counts = ['가능' => 0, '경계' => 0, '불가' => 0];

foreach ($ids as $newsId) {
    $meta = $pdo !== null ? eduQuestFilterLoadArticleMeta($pdo, $newsId) : [
        'news_id' => $newsId,
        'title' => "(id {$newsId})",
        'category' => '',
        'topic_label' => '',
        'published_at' => null,
        'status' => '',
    ];

    if ($meta === null) {
        $rows[] = [
            'news_id' => $newsId,
            'title' => '(not found)',
            'error' => 'MySQL row missing',
        ];
        $counts['불가']++;
        continue;
    }

    $extraction = eduHingeLoadExtraction($newsId);
    $source = 'cache';

    if ($extraction === null && !$cacheOnly) {
        $article = eduHingeLoadMysqlContent($pdo, $newsId);
        if ($article === null) {
            $rows[] = [
                'news_id' => $newsId,
                'title' => $meta['title'],
                'error' => 'content empty',
            ];
            $counts['불가']++;
            continue;
        }

        $result = eduHingeExtractFromContent($llm, $newsId, $article['title'], $article['content']);
        if (!$result['ok']) {
            $rows[] = [
                'news_id' => $newsId,
                'title' => $meta['title'],
                'error' => $result['error'] ?? 'extract failed',
            ];
            $counts['불가']++;
            continue;
        }
        $extraction = $result['extraction'];
        $source = 'llm';
    }

    if ($extraction === null) {
        $rows[] = [
            'news_id' => $newsId,
            'title' => $meta['title'],
            'error' => 'no cache — run edu_gist_hinge_extract.php or drop --cache-only',
        ];
        $counts['불가']++;
        continue;
    }

    if (($meta['title'] ?? '') === '' || str_starts_with((string) $meta['title'], '(id ')) {
        $meta['title'] = (string) ($extraction['title'] ?? $meta['title']);
    }

    $class = eduQuestFilterClassify($extraction, $meta, $eligibleMin, $borderlineMin);
    $verdict = $class['verdict'];
    $counts[$verdict] = ($counts[$verdict] ?? 0) + 1;

    $rows[] = [
        'news_id' => $newsId,
        'title' => $meta['title'],
        'category' => $meta['category'] ?? '',
        'published_at' => $meta['published_at'] ?? null,
        'verdict' => $verdict,
        'score' => $class['score'],
        'hinge' => $extraction['hinge'] ?? null,
        'confidence' => $extraction['confidence'] ?? null,
        'axis_count' => $class['strength']['axis_count'] ?? null,
        'timeliness' => $class['timeliness']['label'] ?? '',
        'timeliness_hint' => $class['timeliness']['hint'] ?? '',
        'reasons' => $class['reasons'],
        'breakdown' => $class['strength']['breakdown'] ?? [],
        'source' => $source,
        'gist_url' => 'https://www.thegist.co.kr/news/' . $newsId,
    ];
}

// ── Table output ──
$colId = 5;
$colVerdict = 6;
$colScore = 5;
$colTime = 12;

echo str_pad('ID', $colId)
    . str_pad('판정', $colVerdict)
    . str_pad('점수', $colScore)
    . str_pad('시의성', $colTime)
    . "제목 / 경첩 / 이유\n";
echo str_repeat('─', 100) . "\n";

foreach ($rows as $r) {
    if (isset($r['error'])) {
        echo str_pad((string) $r['news_id'], $colId)
            . str_pad('불가', $colVerdict)
            . str_pad('—', $colScore)
            . str_pad('—', $colTime)
            . $r['title'] . ' [' . $r['error'] . "]\n";
        continue;
    }

    $hingeShort = $r['hinge'] !== null ? mb_substr((string) $r['hinge'], 0, 70) : '(null)';
    $reasonShort = implode('; ', $r['reasons']);

    echo str_pad((string) $r['news_id'], $colId)
        . str_pad($r['verdict'], $colVerdict)
        . str_pad((string) $r['score'], $colScore)
        . str_pad($r['timeliness'], $colTime)
        . mb_substr($r['title'], 0, 55) . "\n";
    echo str_repeat(' ', $colId + $colVerdict + $colScore + $colTime)
        . "경첩: {$hingeShort}\n";
    echo str_repeat(' ', $colId + $colVerdict + $colScore + $colTime)
        . "conf={$r['confidence']} axis=" . ($r['axis_count'] ?? '—') . " src={$r['source']} | {$reasonShort}\n";
    echo str_repeat(' ', $colId + $colVerdict + $colScore + $colTime)
        . $r['gist_url'] . "\n\n";
}

$total = count($rows);
$eligible = $counts['가능'] ?? 0;
$border = $counts['경계'] ?? 0;
$ineligible = $counts['불가'] ?? 0;
$pctEligible = $total > 0 ? round(100 * $eligible / $total, 1) : 0;
$pctUsable = $total > 0 ? round(100 * ($eligible + $border) / $total, 1) : 0;
$est300Eligible = round(300 * $eligible / max(1, $total));
$est300Usable = round(300 * ($eligible + $border) / max(1, $total));

echo "=== Summary ===\n";
echo "가능: {$eligible}/{$total} ({$pctEligible}%)\n";
echo "경계: {$border}/{$total} (사람 확인)\n";
echo "불가: {$ineligible}/{$total}\n";
echo "300편 extrapolation: 가능 ~{$est300Eligible}편, 가능+경계 ~{$est300Usable}편\n";
echo "10개+/일 기준: 가능만 " . ($est300Eligible >= 100 ? '충분(10일+)' : '부족 — 경계 포함 또는 신규 gist 보충') . "\n\n";

echo "=== 이원근 검수 체크 ===\n";
echo "1. [가능] 분류 — 진짜 따질 만한가? 경첩이 A이지만 B인가?\n";
echo "2. [불가] 분류 — 버려도 되는가? 좋은 글이 잘못 걸러지지 않았나?\n";
echo "3. [경계] — 샘플 몇 개 직접 읽고 기준 조정 (--strict / --lenient)\n";
echo "4. 오분류 있으면 edu_hinge_review.php edit 후 기준 피드백\n\n";

if ($writeMd) {
    $dir = $root . '/docs/quest_filter_verify';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $mdPath = $dir . '/report_' . date('Ymd_His') . '.md';
    $lines = [
        '# Quest filter verify — Step 1',
        '',
        'Generated: ' . date('c'),
        '',
        '| ID | 판정 | 점수 | 시의성 | 제목 | 경첩 |',
        '|----|------|------|--------|------|------|',
    ];
    foreach ($rows as $r) {
        if (isset($r['error'])) {
            $lines[] = sprintf(
                '| %d | 불가 | — | — | %s | %s |',
                $r['news_id'],
                str_replace('|', '/', $r['title']),
                $r['error']
            );
            continue;
        }
        $hinge = str_replace('|', '/', mb_substr((string) ($r['hinge'] ?? ''), 0, 80));
        $title = str_replace('|', '/', mb_substr($r['title'], 0, 60));
        $lines[] = sprintf(
            '| %d | %s | %d | %s | %s | %s |',
            $r['news_id'],
            $r['verdict'],
            $r['score'],
            $r['timeliness'],
            $title,
            $hinge
        );
    }
    $lines[] = '';
    $lines[] = "## Summary";
    $lines[] = "- 가능: {$eligible}/{$total} ({$pctEligible}%)";
    $lines[] = "- 경계: {$border}";
    $lines[] = "- 300편 추정 가능: ~{$est300Eligible}";
    file_put_contents($mdPath, implode("\n", $lines) . "\n");
    echo "Wrote {$mdPath}\n";
}

echo json_encode([
    'generated_at' => date('c'),
    'ids' => $ids,
    'counts' => $counts,
    'pct_eligible' => $pctEligible,
    'estimate_300_eligible' => $est300Eligible,
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
