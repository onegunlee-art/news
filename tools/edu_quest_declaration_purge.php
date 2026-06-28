<?php
/**
 * Step 2 — 선언문·연설 Q-GIST draft 제거 (분석글 보존)
 *
 * Usage:
 *   php tools/edu_quest_declaration_purge.php              # 제거 목록만
 *   php tools/edu_quest_declaration_purge.php --apply    # draft만 삭제
 *   php tools/edu_quest_declaration_purge.php --apply --ids=651,591
 *
 * NEVER: approved, 수동 시드, non-Q-GIST
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
$forceIds = [];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--ids=')) {
        $forceIds = array_map('intval', array_filter(explode(',', substr($arg, 6))));
    }
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

/** @return list<array<string, mixed>> */
function loadGistDraftQuests(SupabaseService $supabase, array $forceIds = []): array
{
    $out = [];
    $offset = 0;
    while (true) {
        $batch = $supabase->select(
            'edu_daily_quests',
            'status=eq.draft&order=quest_code.asc&limit=100&offset=' . $offset,
            100
        ) ?? [];
        if ($batch === []) {
            break;
        }
        foreach ($batch as $row) {
            $code = (string) ($row['quest_code'] ?? '');
            if (!str_starts_with($code, 'Q-GIST-')) {
                continue;
            }
            $articles = $supabase->select(
                'edu_quest_articles',
                'quest_id=eq.' . ($row['id'] ?? '') . '&role=eq.primary',
                1
            ) ?? [];
            $newsId = (int) ($articles[0]['news_id'] ?? 0);
            $hints = $row['hammer_hints'] ?? [];
            if (is_string($hints)) {
                $hints = json_decode($hints, true) ?: [];
            }
            $hingeBlock = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];
            $extraction = array_merge(['news_id' => $newsId], $hingeBlock, [
                'title' => $row['quest_title'] ?? '',
                'confidence' => $hints['_meta']['confidence'] ?? null,
            ]);
            $meta = [
                'news_id' => $newsId,
                'title' => (string) ($row['quest_title'] ?? ($articles[0]['title'] ?? '')),
                'category' => '',
                'topic_label' => '',
            ];
            $decl = eduQuestFilterDeclarationCheck($meta, $extraction);
            $forced = $forceIds !== [] && in_array($newsId, $forceIds, true);

            $out[] = [
                'quest_id' => (string) ($row['id'] ?? ''),
                'quest_code' => $code,
                'news_id' => $newsId,
                'title' => $meta['title'],
                'status' => (string) ($row['status'] ?? ''),
                'declaration' => $decl,
                'purge' => $forced || ($decl['is_declaration'] ?? false),
            ];
        }
        if (count($batch) < 100) {
            break;
        }
        $offset += 100;
    }

    return $out;
}

echo "=== 선언문·연설 draft purge ===\n";
echo 'Mode: ' . ($apply ? 'APPLY (delete draft)' : 'LIST ONLY') . "\n";
echo "Scope: Q-GIST-* status=draft only\n";
echo "Preserve: approved, 수동 시드, 분석글\n\n";

$quests = loadGistDraftQuests($supabase, $forceIds);
$toPurge = array_values(array_filter($quests, static fn ($q) => $q['purge']));
$toKeep = array_values(array_filter($quests, static fn ($q) => !$q['purge']));

echo '=== 제거 대상 (' . count($toPurge) . ") ===\n";
foreach ($toPurge as $q) {
    $decl = $q['declaration'];
    echo sprintf(
        "  [%d] %s — %s (%s)\n",
        $q['news_id'],
        $q['quest_code'],
        mb_substr($q['title'], 0, 55),
        $decl['label'] ?? ''
    );
    foreach ($decl['reasons'] ?? [] as $r) {
        echo "       · {$r}\n";
    }
}

echo "\n=== 보존 — 분석글 draft (" . count($toKeep) . ") ===\n";
echo "(approve 후보 — edu_quest_analyze_candidates.php 참고)\n";
foreach (array_slice($toKeep, 0, 25) as $q) {
    echo sprintf(
        "  [%d] %s — %s\n",
        $q['news_id'],
        $q['quest_code'],
        mb_substr($q['title'], 0, 60)
    );
}
if (count($toKeep) > 25) {
    echo '  ... +' . (count($toKeep) - 25) . " more\n";
}

echo "\n=== 검증 요약 ===\n";
echo 'G7 blocklist hit: ' . count(array_filter($toPurge, static fn ($q) => in_array($q['news_id'], [647, 650, 651, 652, 653, 654, 656, 657, 658], true))) . "\n";
echo 'Shangri-La blocklist hit: ' . count(array_filter($toPurge, static fn ($q) => $q['news_id'] >= 590 && $q['news_id'] <= 598)) . "\n";
$keepIds = array_column($toKeep, 'news_id');
foreach ([631, 668, 288] as $checkId) {
    $inDraftKeep = in_array($checkId, $keepIds, true);
    $code = eduQuestGenerateQuestCode($checkId);
    $approvedRows = $supabase->select(
        'edu_daily_quests',
        'quest_code=eq.' . rawurlencode($code) . '&status=eq.approved',
        1
    ) ?? [];
    $isApproved = $approvedRows !== [];
    if ($isApproved) {
        echo "{$checkId} approved (live pool): YES\n";
    } elseif ($inDraftKeep) {
        echo "{$checkId} draft preserved: YES\n";
    } else {
        echo "{$checkId} missing: CHECK\n";
    }
}

if (!$apply) {
    echo "\nNext: php tools/edu_quest_declaration_purge.php --apply\n";
    exit(0);
}

if ($toPurge === []) {
    echo "\nNothing to purge.\n";
    exit(0);
}

$removed = 0;
foreach ($toPurge as $q) {
    if (($q['status'] ?? '') !== 'draft') {
        echo "SKIP {$q['quest_code']} — not draft\n";
        continue;
    }
    if (eduQuestGenerateIsProtectedQuestCode($q['quest_code'])) {
        echo "SKIP {$q['quest_code']} — protected\n";
        continue;
    }
    $questId = $q['quest_id'];
    $supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
    $supabase->delete('edu_daily_quests', 'id=eq.' . $questId);
    $check = $supabase->select('edu_daily_quests', 'id=eq.' . $questId, 1) ?? [];
    if ($check === []) {
        echo "REMOVED {$q['quest_code']} [{$q['news_id']}]\n";
        $removed++;
    } else {
        fwrite(STDERR, "FAIL remove {$q['quest_code']}\n");
    }
}

echo "\nRemoved: {$removed}/" . count($toPurge) . "\n";
echo "Analyze approve candidates: php tools/edu_quest_analyze_candidates.php\n";
