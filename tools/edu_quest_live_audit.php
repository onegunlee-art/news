<?php
/**
 * 라이브(approved) 퀘스트 품질·패턴 분류 (READ ONLY, 제거 전 진단)
 *
 * Usage: php tools/edu_quest_live_audit.php
 *        php tools/edu_quest_live_audit.php --json
 *        php tools/edu_quest_live_audit.php --md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$asJson = in_array('--json', $argv ?? [], true);
$asMd = in_array('--md', $argv ?? [], true);

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

/** @return 'preserve_gist'|'preserve_manual'|'legacy_candidate'|'unknown' */
function eduQuestAuditPattern(string $code): string
{
    if (preg_match('/^Q-GIST-\d+$/', $code)) {
        return 'preserve_gist';
    }
    if (preg_match('/^Q-NUKE-AXIS-630$/', $code)
        || preg_match('/^Q-AUTO-NUKE-630$/', $code)
        || preg_match('/^Q-AUTO-DC-150$/', $code)
        || preg_match('/^Q-AUTO-.*-(150|196|288|630)$/', $code)) {
        return 'preserve_manual';
    }
    if (preg_match('/^Q-AUTO-/', $code)
        || preg_match('/^Q-CONV-/', $code)
        || preg_match('/^Q-G0\d$/', $code)
        || preg_match('/^Q-LENS-/', $code)
        || preg_match('/^Q-G\d{2}$/', $code)) {
        return 'legacy_candidate';
    }

    return 'unknown';
}

function eduQuestAuditPatternLabel(string $pattern): string
{
    return match ($pattern) {
        'preserve_gist' => '최근 변환 Q-GIST-*',
        'preserve_manual' => '수동 시드/핵심',
        'legacy_candidate' => '옛날 후보',
        default => '미분류',
    };
}

function eduQuestAuditEmptyStance(?string $pro, ?string $con): bool
{
    $bad = [
        '찬성 입장', '반대 입장', '찬성 입장 한 줄', '반대 입장 한 줄',
        '찬성', '반대', 'pro', 'con',
    ];
    $pro = trim((string) $pro);
    $con = trim((string) $con);
    if ($pro === '' || $con === '') {
        return true;
    }
    foreach ($bad as $b) {
        if ($pro === $b || $con === $b) {
            return true;
        }
    }
    if (mb_strlen($pro) < 8 || mb_strlen($con) < 8) {
        return true;
    }

    return false;
}

/** @param array<string, mixed> $quest */
function eduQuestAuditAxisLeak(array $quest): array
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }
    $issues = [];
    foreach (['hook_short', 'hook_full', 'shared_conclusion'] as $k) {
        $v = (string) ($hints[$k] ?? '');
        if (preg_match('/축\s*[12]/u', $v) || preg_match('/axis_[12]/i', $v)) {
            $issues[] = "hammer_hints.{$k}";
        }
    }
    $axes = is_array($hints['axes'] ?? null) ? $hints['axes'] : [];
    foreach ($axes as $i => $axis) {
        $label = (string) ($axis['axis_label'] ?? '');
        $q = (string) ($axis['core_question'] ?? '');
        if (preg_match('/^축\s*[0-9]/u', $label) || preg_match('/^axis_[0-9]/i', $label)) {
            $issues[] = 'axis_label[' . $i . ']=' . $label;
        }
        if (preg_match('/^축\s*[0-9]/u', $q)) {
            $issues[] = 'core_question[' . $i . ']';
        }
    }

    return $issues;
}

/** @param array<string, mixed> $quest @return list<array<string, mixed>> */
function eduQuestAuditArticles(\Agents\Services\SupabaseService $sb, array $quest): array
{
    $id = (string) ($quest['id'] ?? '');
    if ($id === '') {
        return [];
    }

    return $sb->select('edu_quest_articles', 'quest_id=eq.' . $id . '&order=sort_order.asc', 10) ?? [];
}

/** 훅/질문이 primary 기사와 무관해 보이는 휴리스틱 */
function eduQuestAuditContextMismatch(array $quest, array $articles): bool
{
    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }
    $hook = (string) ($hints['hook_short'] ?? $quest['conflict_summary'] ?? '');
    $primaryTitle = '';
    foreach ($articles as $a) {
        if (($a['role'] ?? '') === 'primary') {
            $primaryTitle = (string) ($a['title'] ?? '');
            break;
        }
    }
    if ($primaryTitle === '' || $hook === '') {
        return false;
    }
    // 걸프-호르무즈 류: primary에 없는 지명이 hook에만 등장
    $knownMismatchPairs = [
        ['걸프', '호르무즈'],
        ['gulf', 'hormuz'],
    ];
    $primaryLower = mb_strtolower($primaryTitle);
    $hookLower = mb_strtolower($hook);
    foreach ($knownMismatchPairs as [$a, $b]) {
        $inPrimary = str_contains($primaryLower, $a) || str_contains($primaryLower, $b);
        $inHook = str_contains($hookLower, $a) && str_contains($hookLower, $b);
        if ($inHook && !$inPrimary) {
            return true;
        }
    }

    return false;
}

function eduQuestAuditRecommend(string $pattern, bool $emptyStance, bool $mismatch, array $axisLeaks): string
{
    if ($pattern === 'preserve_gist' || $pattern === 'preserve_manual') {
        if ($axisLeaks !== []) {
            return '보존 (축 라벨 수정)';
        }

        return '보존';
    }
    if ($emptyStance || $mismatch) {
        return '제거 후보 ★';
    }
    if ($pattern === 'legacy_candidate') {
        return '검토 (옛날)';
    }

    return '검토';
}

$rows = $sb->select(
    'edu_daily_quests',
    'status=eq.approved&order=created_at.asc',
    200
) ?? [];

$audited = [];
foreach ($rows as $q) {
    $code = (string) ($q['quest_code'] ?? '');
    $pattern = eduQuestAuditPattern($code);
    $articles = eduQuestAuditArticles($sb, $q);
    $emptyStance = eduQuestAuditEmptyStance($q['pro_line'] ?? '', $q['con_line'] ?? '');
    $mismatch = eduQuestAuditContextMismatch($q, $articles);
    $axisLeaks = eduQuestAuditAxisLeak($q);
    $audited[] = [
        'quest_code' => $code,
        'quest_title' => (string) ($q['quest_title'] ?? ''),
        'created_at' => (string) ($q['created_at'] ?? ''),
        'live_at' => $q['live_at'] ?? null,
        'pattern' => $pattern,
        'pattern_label' => eduQuestAuditPatternLabel($pattern),
        'pro_line' => (string) ($q['pro_line'] ?? ''),
        'con_line' => (string) ($q['con_line'] ?? ''),
        'empty_stance' => $emptyStance,
        'context_mismatch' => $mismatch,
        'axis_leaks' => $axisLeaks,
        'primary_title' => (function () use ($articles) {
            foreach ($articles as $a) {
                if (($a['role'] ?? '') === 'primary') {
                    return (string) ($a['title'] ?? '');
                }
            }

            return '';
        })(),
        'article_count' => count($articles),
        'recommend' => eduQuestAuditRecommend($pattern, $emptyStance, $mismatch, $axisLeaks),
    ];
}

if ($asJson) {
    echo json_encode(['count' => count($audited), 'quests' => $audited], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($asMd) {
    $md = "# EDU 라이브 퀘스트 감사 (approved)\n\n";
    $md .= '| 코드 | 패턴 | 입장 | 맥락 | primary | 추천 |\n';
    $md .= '|------|------|------|------|---------|------|\n';
    foreach ($audited as $r) {
        $stance = $r['empty_stance'] ? '빈/플레이스홀더' : '정상';
        $ctx = $r['context_mismatch'] ? '끊김?' : 'OK';
        if ($r['axis_leaks'] !== []) {
            $ctx .= ' ·축노출';
        }
        $md .= sprintf(
            "| %s | %s | %s | %s | %s | %s |\n",
            $r['quest_code'],
            $r['pattern_label'],
            $stance,
            $ctx,
            mb_substr($r['primary_title'], 0, 24),
            $r['recommend']
        );
    }
    $path = $root . '/docs/EDU_QUEST_LIVE_AUDIT.md';
    file_put_contents($path, $md);
    echo "Wrote {$path}\n";
    exit(0);
}

echo "=== EDU Live Quest Audit (approved: " . count($audited) . ") ===\n\n";
printf("%-22s %-18s %-8s %-8s %-12s %s\n", 'CODE', 'PATTERN', 'STANCE', 'CONTEXT', 'RECOMMEND', 'TITLE');
echo str_repeat('-', 110) . "\n";
foreach ($audited as $r) {
    printf(
        "%-22s %-18s %-8s %-8s %-12s %s\n",
        $r['quest_code'],
        $r['pattern_label'],
        $r['empty_stance'] ? 'BAD' : 'OK',
        $r['context_mismatch'] ? 'MISMATCH' : 'OK',
        $r['recommend'],
        mb_substr($r['quest_title'], 0, 40)
    );
    if ($r['pro_line'] !== '' && $r['empty_stance']) {
        echo "    pro: {$r['pro_line']} | con: {$r['con_line']}\n";
    }
    if ($r['axis_leaks'] !== []) {
        echo '    axis_leak: ' . implode(', ', $r['axis_leaks']) . "\n";
    }
    if ($r['primary_title'] !== '') {
        echo "    primary: {$r['primary_title']}\n";
    }
}

$removeCandidates = array_filter($audited, fn ($r) => str_contains($r['recommend'], '제거'));
$axisFix = array_filter($audited, fn ($r) => str_contains($r['recommend'], '축 라벨'));

echo "\n=== 제거 후보 (" . count($removeCandidates) . ") ===\n";
foreach ($removeCandidates as $r) {
    echo "- {$r['quest_code']} | {$r['quest_title']} | pro={$r['pro_line']} con={$r['con_line']}\n";
}

echo "\n=== 축 라벨 수정 (" . count($axisFix) . ") ===\n";
foreach ($axisFix as $r) {
    echo "- {$r['quest_code']} | " . implode(', ', $r['axis_leaks']) . "\n";
}
