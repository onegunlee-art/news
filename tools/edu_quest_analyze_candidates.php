<?php
/**
 * Step 2 — 분석글 approve 후보 목록 (선언문 제외)
 *
 * Usage:
 *   php tools/edu_quest_analyze_candidates.php
 *   php tools/edu_quest_analyze_candidates.php --write-md
 *   php tools/edu_quest_analyze_candidates.php --priority=631,668,288
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';

use Agents\Services\SupabaseService;

$writeMd = in_array('--write-md', $argv ?? [], true);
$priority = [];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--priority=')) {
        $priority = array_map('intval', array_filter(explode(',', substr($arg, 11))));
    }
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$rows = [];
$offset = 0;
while (true) {
    $batch = $supabase->select(
        'edu_daily_quests',
        'status=eq.draft&order=created_at.desc&limit=100&offset=' . $offset,
        100
    ) ?? [];
    if ($batch === []) {
        break;
    }
    foreach ($batch as $quest) {
        $code = (string) ($quest['quest_code'] ?? '');
        if (!str_starts_with($code, 'Q-GIST-')) {
            continue;
        }
        $articles = $supabase->select(
            'edu_quest_articles',
            'quest_id=eq.' . ($quest['id'] ?? '') . '&role=eq.primary',
            1
        ) ?? [];
        $newsId = (int) ($articles[0]['news_id'] ?? 0);
        $hints = $quest['hammer_hints'] ?? [];
        if (is_string($hints)) {
            $hints = json_decode($hints, true) ?: [];
        }
        $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];
        $meta = [
            'news_id' => $newsId,
            'title' => (string) ($quest['quest_title'] ?? ''),
            'category' => '',
            'topic_label' => '',
        ];
        $extraction = array_merge(['news_id' => $newsId], $hinge);
        $decl = eduQuestFilterDeclarationCheck($meta, $extraction);
        if ($decl['is_declaration']) {
            continue;
        }
        $class = eduQuestFilterClassify($extraction, $meta);
        $rows[] = [
            'news_id' => $newsId,
            'quest_code' => $code,
            'title' => $meta['title'],
            'hinge' => $hinge['hinge'] ?? ($quest['conflict_summary'] ?? ''),
            'filter_verdict' => $class['verdict'] ?? '',
            'filter_score' => $class['score'] ?? 0,
            'axes' => count($hints['_guide_axes'] ?? []),
            'priority' => in_array($newsId, $priority, true) || in_array($newsId, [631, 668, 288, 630, 196, 150], true),
            'gist_url' => 'https://www.thegist.co.kr/news/' . $newsId,
            'approve_cmd' => 'php tools/edu_quest_generate_approve.php --apply --quest-code=' . $code,
        ];
    }
    if (count($batch) < 100) {
        break;
    }
    $offset += 100;
}

usort($rows, static function ($a, $b) {
    if ($a['priority'] !== $b['priority']) {
        return $b['priority'] <=> $a['priority'];
    }
    return ($b['filter_score'] ?? 0) <=> ($a['filter_score'] ?? 0);
});

echo "=== 분석글 approve 후보 (선언문 제외) ===\n";
echo 'Count: ' . count($rows) . " draft Q-GIST-*\n";
echo "일괄 approve 금지 — 아래 명령을 개별 실행\n\n";

echo "★ 검수 우선 (631·668 등):\n";
foreach ($rows as $r) {
    if (!$r['priority']) {
        continue;
    }
    echo sprintf(
        "  [%d] %s score=%d — %s\n",
        $r['news_id'],
        $r['quest_code'],
        $r['filter_score'],
        mb_substr($r['title'], 0, 50)
    );
    echo '    경첩: ' . mb_substr((string) $r['hinge'], 0, 70) . "\n";
    echo '    ' . $r['approve_cmd'] . "\n\n";
}

echo "--- 전체 목록 (score 순) ---\n";
foreach (array_slice($rows, 0, 40) as $r) {
    echo sprintf(
        "[%d] %s (%d) %s\n",
        $r['news_id'],
        $r['quest_code'],
        $r['filter_score'],
        mb_substr($r['title'], 0, 45)
    );
}
if (count($rows) > 40) {
    echo '... +' . (count($rows) - 40) . " more\n";
}

echo "\n예: 검수 OK 후\n";
echo "  php tools/edu_quest_generate_approve.php --apply --quest-code=Q-GIST-631\n";

if ($writeMd) {
    $dir = $root . '/docs/quest_generate';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir . '/analyze_candidates_' . date('Ymd_His') . '.md';
    $lines = [
        '# 분석글 approve 후보',
        '',
        'Generated: ' . date('c'),
        '',
        '| news_id | quest_code | score | title | hinge | approve |',
        '|---------|------------|-------|-------|-------|---------|',
    ];
    foreach ($rows as $r) {
        $lines[] = sprintf(
            '| %d | %s | %d | %s | %s | `%s` |',
            $r['news_id'],
            $r['quest_code'],
            $r['filter_score'],
            str_replace('|', '/', mb_substr($r['title'], 0, 40)),
            str_replace('|', '/', mb_substr((string) $r['hinge'], 0, 50)),
            $r['quest_code']
        );
    }
    file_put_contents($path, implode("\n", $lines) . "\n");
    echo "\nWrote {$path}\n";
}

echo json_encode([
    'count' => count($rows),
    'priority_ids' => array_values(array_filter(array_column($rows, 'news_id'), static fn ($id) => in_array($id, [631, 668, 288], true))),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
