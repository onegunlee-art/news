<?php
/**
 * Step 2 — draft 퀘스트 → approved (검수 후 라이브)
 *
 * Usage:
 *   php tools/edu_quest_generate_approve.php --dry-run --quest-code=Q-GIST-631
 *   php tools/edu_quest_generate_approve.php --apply --quest-code=Q-GIST-631
 *   php tools/edu_quest_generate_approve.php --apply --top=10
 *   php tools/edu_quest_generate_approve.php --apply --codes=631,668,613,670,676,569,638,662,688,680
 *
 * --top=N: 선언문 제외 + filter score 순 상위 N (분석글만)
 * --codes=: 명시 ID/quest-code 목록 (선언문이면 SKIP)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestFilter.php';
require_once $root . '/public/api/edu/lib/eduQuestGenerate.php';

use Agents\Services\SupabaseService;

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;
$allDrafts = in_array('--all-drafts', $argv ?? [], true);
$source = EDU_QUEST_GENERATE_SOURCE;
$questCodes = [];
$topN = 0;
$explicitCodes = [];

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCodes[] = substr($arg, 13);
    }
    if (str_starts_with($arg, '--source=')) {
        $source = substr($arg, 9);
    }
    if (str_starts_with($arg, '--top=')) {
        $topN = max(1, min(50, (int) substr($arg, 6)));
    }
    if (str_starts_with($arg, '--codes=')) {
        foreach (array_filter(explode(',', substr($arg, 8))) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, 'Q-GIST-')) {
                $explicitCodes[] = $part;
            } else {
                $explicitCodes[] = eduQuestGenerateQuestCode((int) $part);
            }
        }
    }
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

if ($explicitCodes !== []) {
    $questCodes = array_values(array_unique(array_merge($questCodes, $explicitCodes)));
}

if ($topN > 0) {
    $candidates = eduQuestListAnalysisDraftCandidates($supabase);
    $picked = array_slice($candidates, 0, $topN);
    foreach ($picked as $row) {
        $questCodes[] = (string) ($row['quest_code'] ?? '');
    }
    $questCodes = array_values(array_unique($questCodes));
    echo "=== Top {$topN} analysis drafts (declaration excluded, by score) ===\n";
    foreach ($picked as $row) {
        echo sprintf(
            "  [%d] %s score=%d — %s\n",
            $row['news_id'],
            $row['quest_code'],
            $row['filter_score'],
            mb_substr((string) $row['title'], 0, 50)
        );
    }
    echo "\n";
}

if ($allDrafts) {
    $rows = $supabase->select(
        'edu_daily_quests',
        'status=eq.draft&order=created_at.desc',
        200
    ) ?? [];
    foreach ($rows as $row) {
        $code = (string) ($row['quest_code'] ?? '');
        $scores = is_string($row['scores'] ?? null) ? json_decode($row['scores'], true) : ($row['scores'] ?? []);
        if (!str_starts_with($code, 'Q-GIST-')) {
            continue;
        }
        if (($scores['source'] ?? '') !== $source) {
            continue;
        }
        $questCodes[] = $code;
    }
    $questCodes = array_values(array_unique($questCodes));
}

if ($questCodes === []) {
    fwrite(STDERR, "Usage: --quest-code=Q-GIST-... | --top=N | --codes=631,668,... | --all-drafts\n");
    exit(1);
}

echo "=== Quest approve (draft → approved) ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n\n";

$approved = 0;
$skipped = 0;

foreach ($questCodes as $code) {
    if (!str_starts_with($code, 'Q-GIST-')) {
        echo "SKIP {$code} — only Q-GIST-* allowed\n";
        $skipped++;
        continue;
    }

    $rows = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1) ?? [];
    $quest = $rows[0] ?? null;
    if ($quest === null) {
        echo "MISSING {$code}\n";
        $skipped++;
        continue;
    }
    if (($quest['status'] ?? '') === 'approved') {
        echo "ALREADY approved {$code}\n";
        continue;
    }
    if (($quest['status'] ?? '') !== 'draft') {
        echo "SKIP {$code} — not draft\n";
        $skipped++;
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
    $meta = ['news_id' => $newsId, 'title' => (string) ($quest['quest_title'] ?? ''), 'category' => '', 'topic_label' => ''];
    $decl = eduQuestFilterDeclarationCheck($meta, array_merge(['news_id' => $newsId], $hinge));
    if ($decl['is_declaration']) {
        echo "SKIP {$code} — 선언문·연설 ({$decl['label']})\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "WOULD approve {$code} [{$newsId}]\n";
        $approved++;
        continue;
    }

    $updated = $supabase->update('edu_daily_quests', 'id=eq.' . ($quest['id'] ?? ''), [
        'status' => 'approved',
        'updated_at' => date('c'),
    ]);
    if ($updated === null) {
        echo "FAIL {$code}: " . $supabase->getLastError() . "\n";
        $skipped++;
        continue;
    }
    echo "APPROVED {$code} [{$newsId}]\n";
    $approved++;
}

echo "\nApproved: {$approved}/" . count($questCodes) . " (skipped {$skipped})\n";
