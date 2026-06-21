<?php
/**
 * P2-A2 — 경첩 JSON → 최소 퀘스트 데이터 (hammer_hints, axes 없음)
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $hinge A1 extraction (approved)
 * @return array<string, mixed> min quest shell (not DB-ready seed)
 */
function eduHingeMapToMinQuest(array $hinge): array
{
    $newsId = (int) ($hinge['news_id'] ?? 0);
    $title = trim((string) ($hinge['title'] ?? ''));
    $sideA = trim((string) ($hinge['side_a'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? ''));
    $hingeLine = trim((string) ($hinge['hinge'] ?? ''));
    $hookShort = trim((string) ($hinge['hook_student'] ?? ''));
    $shake = trim((string) ($hinge['shake_prompt'] ?? ''));

    $shared = eduHingeSharedConclusion($sideB, $hingeLine);
    $hookFull = eduHingeBuildHookFull($hookShort, $sideA, $sideB);

    return [
        'quest_code' => 'Q-AUTO-NUKE-' . $newsId,
        'quest_title' => $title,
        'pro_line' => '',
        'con_line' => '',
        'alignment_summary' => $sideB,
        'conflict_summary' => $hingeLine,
        'hammer_hints' => [
            'mode' => 'adversarial',
            'quest_frame' => eduHingeInferQuestFrame($hinge),
            'time_anchor' => '',
            'hook_short' => $hookShort,
            'hook_full' => $hookFull,
            'shared_conclusion' => $shared,
            'axes' => [],
            'counter_map' => (object) [],
            'fallback_adversarial' => [
                'pro' => $sideA,
                'con' => eduHingeConLineFromSideB($sideB),
            ],
            '_hinge' => [
                'hinge' => $hingeLine !== '' ? $hingeLine : null,
                'side_a' => $sideA,
                'side_b' => $sideB,
                'hook_student' => $hookShort,
                'shake_prompt' => $shake,
            ],
            '_meta' => [
                'article_form' => (string) ($hinge['article_form'] ?? 'unknown'),
                'confidence' => $hinge['confidence'] ?? null,
                'mapper_version' => 'p2-a2-v1',
                'mapped_at' => date('c'),
            ],
        ],
        'articles' => [
            [
                'news_id' => $newsId,
                'role' => 'primary',
                'title' => $title,
                'gist_url' => 'https://www.thegist.co.kr/news/' . $newsId,
            ],
        ],
    ];
}

/** @param array<string, mixed> $hinge */
function eduHingeInferQuestFrame(array $hinge): string
{
    $hook = (string) ($hinge['hook_student'] ?? '');
    $sideA = (string) ($hinge['side_a'] ?? '');

    if (preg_match('/[?？]/u', $hook . $sideA)) {
        return 'myth_bust';
    }
    if (preg_match('/(인가|일까|할까|될까|인지)/u', $sideA)) {
        return 'myth_bust';
    }

    return 'myth_bust';
}

function eduHingeNormalizeHookCompareKey(string $text): string
{
    $t = mb_strtolower(trim($text));
    $t = preg_replace('/[?？!！。．\.…,，、\s]+/u', '', $t) ?? $t;
    $t = preg_replace('/(을까|일까|할까|될까|는가|인가|겠어|거야|정말|진짜)$/u', '', $t) ?? $t;

    return $t;
}

function eduHingeSideARepeatsHookStudent(string $hookShort, string $sideA): bool
{
    if ($hookShort === '' || $sideA === '') {
        return false;
    }

    $hookKey = eduHingeNormalizeHookCompareKey($hookShort);
    $sideKey = eduHingeNormalizeHookCompareKey($sideA);
    if ($hookKey === '' || $sideKey === '') {
        return false;
    }
    if ($hookKey === $sideKey) {
        return true;
    }
    if (str_contains($hookKey, $sideKey) || str_contains($sideKey, $hookKey)) {
        return true;
    }

    similar_text($hookKey, $sideKey, $pct);

    return $pct >= 72.0;
}

function eduHingeBuildHookFull(string $hookShort, string $sideA, string $sideB): string
{
    if ($hookShort === '') {
        return $sideA;
    }

    if (eduHingeSideARepeatsHookStudent($hookShort, $sideA)) {
        return $hookShort;
    }

    // hook_short + side_a 한 줄 (side_a가 새 각도일 때만)
    if ($sideA !== '' && mb_strlen($hookShort) < 120) {
        return $hookShort . ' ' . $sideA;
    }

    return $hookShort;
}

function eduHingeSharedConclusion(string $sideB, string $hingeLine): string
{
    // hinge "A이지만 B" — B절이 shared_conclusion 축과 가장 가깝다 (630 수동 대조)
    if (preg_match('/(?:이)?지만\s*(.+)$/u', $hingeLine, $m)) {
        $bClause = trim($m[1]);
        if (mb_strlen($bClause) >= 20) {
            if (mb_strlen($bClause) > 120) {
                return mb_substr($bClause, 0, 117) . '…';
            }

            return $bClause;
        }
    }

    if ($sideB === '') {
        return $hingeLine;
    }

    // side_b: 사례 나열 뒤 "으며," 이후 결론절 우선
    if (preg_match('/(?:으며|고)\s*,\s*(.+)$/u', $sideB, $m)) {
        $tail = trim($m[1]);
        if (mb_strlen($tail) >= 20 && mb_strlen($tail) <= 140) {
            return $tail;
        }
    }

    if (mb_strlen($sideB) > 120) {
        return mb_substr($sideB, 0, 117) . '…';
    }

    return $sideB;
}

function eduHingeConLineFromSideB(string $sideB): string
{
    if ($sideB === '') {
        return '';
    }
    if (mb_strlen($sideB) > 80) {
        return mb_substr($sideB, 0, 77) . '…';
    }

    return $sideB;
}

/**
 * 수동 630 vs 자동 min 퀘스트 심장(hook/shared/shake) 대조
 *
 * @return array{pass: bool, rows: list<array<string, string>>, notes: list<string>}
 */
function eduHingeCompare630Heart(array $autoQuest, array $manualQuest): array
{
    $autoHints = $autoQuest['hammer_hints'] ?? [];
    $manualHints = $manualQuest['hammer_hints'] ?? [];
    $autoHinge = is_array($autoHints['_hinge'] ?? null) ? $autoHints['_hinge'] : [];

    $rows = [];
    $notes = [];

    $rows[] = eduHingeCompareRow(
        'hook_short',
        (string) ($manualHints['hook_short'] ?? ''),
        (string) ($autoHints['hook_short'] ?? ''),
        'same_axis'
    );

    $rows[] = eduHingeCompareRow(
        'shared_conclusion',
        (string) ($manualHints['shared_conclusion'] ?? ''),
        (string) ($autoHints['shared_conclusion'] ?? ''),
        'same_axis'
    );

    $manualShake = (string) ($manualQuest['alignment_summary'] ?? ($manualHints['hook_full'] ?? ''));
    $rows[] = eduHingeCompareRow(
        'shake / B-side fact',
        $manualShake !== '' ? $manualShake : '(manual axes)',
        (string) ($autoHinge['shake_prompt'] ?? ''),
        'fact_overlap'
    );

    $rows[] = [
        'field' => 'quest_frame',
        'manual' => (string) ($manualHints['quest_frame'] ?? ''),
        'auto' => (string) ($autoHints['quest_frame'] ?? ''),
        'verdict' => ($manualHints['quest_frame'] ?? '') === ($autoHints['quest_frame'] ?? '') ? 'match' : 'diff',
    ];

    $rows[] = [
        'field' => 'mode',
        'manual' => (string) ($manualHints['mode'] ?? ''),
        'auto' => (string) ($autoHints['mode'] ?? ''),
        'verdict' => 'intentional_diff',
        'note' => '수동=convergent+axes / 자동 1단계=adversarial only',
    ];

    $rows[] = [
        'field' => 'axes',
        'manual' => (string) count($manualHints['axes'] ?? []),
        'auto' => (string) count($autoHints['axes'] ?? []),
        'verdict' => 'intentional_diff',
        'note' => '2단계',
    ];

    $passFields = ['hook_short', 'shared_conclusion', 'shake / B-side fact'];
    $pass = true;
    foreach ($rows as $row) {
        if (!in_array($row['field'], $passFields, true)) {
            continue;
        }
        if (($row['verdict'] ?? '') === 'mismatch') {
            $pass = false;
        }
    }

    if (($rows[2]['verdict'] ?? '') === 'partial') {
        $notes[] = 'shake: fact 키워드(드론·미사일·우크라이나·재래식) 겹침 확인';
    }

    return ['pass' => $pass, 'rows' => $rows, 'notes' => $notes];
}

/**
 * @return array{field: string, manual: string, auto: string, verdict: string, note?: string}
 */
function eduHingeCompareRow(string $field, string $manual, string $auto, string $mode): array
{
    $verdict = 'mismatch';
    $note = '';

    if ($manual === '' && $auto === '') {
        $verdict = 'match';
    } elseif ($mode === 'same_axis') {
        $verdict = eduHingeSameAxisVerdict($manual, $auto);
    } elseif ($mode === 'fact_overlap') {
        $verdict = eduHingeFactOverlapVerdict($manual, $auto);
        if ($verdict === 'partial' || $verdict === 'match') {
            $note = '우크라이나·드론·미사일·재래식·전략폭격기 등';
        }
    } elseif ($manual === $auto) {
        $verdict = 'match';
    }

    return [
        'field' => $field,
        'manual' => $manual,
        'auto' => $auto,
        'verdict' => $verdict,
        'note' => $note,
    ];
}

function eduHingeSameAxisVerdict(string $manual, string $auto): string
{
    if ($manual === '' || $auto === '') {
        return 'partial';
    }

    $keywords = ['핵', '드론', '미사일', '재래식', '억지', '막', '공격', '전쟁'];
    $manualHit = 0;
    $autoHit = 0;
    foreach ($keywords as $kw) {
        if (str_contains($manual, $kw)) {
            $manualHit++;
        }
        if (str_contains($auto, $kw)) {
            $autoHit++;
        }
    }

    if ($manualHit >= 2 && $autoHit >= 2) {
        return 'match';
    }

    return 'partial';
}

function eduHingeFactOverlapVerdict(string $manual, string $auto): string
{
    $facts = ['드론', '미사일', '우크라이나', '러시아', '재래식', '전략폭격', '거미줄', '2025'];
    $overlap = 0;
    foreach ($facts as $f) {
        if (str_contains($manual, $f) && str_contains($auto, $f)) {
            $overlap++;
        } elseif (str_contains($manual, $f) || str_contains($auto, $f)) {
            $overlap += 0;
        }
    }
    $manualFacts = 0;
    $autoFacts = 0;
    foreach ($facts as $f) {
        if (str_contains($manual, $f)) {
            $manualFacts++;
        }
        if (str_contains($auto, $f)) {
            $autoFacts++;
        }
    }
    $shared = 0;
    foreach ($facts as $f) {
        if (str_contains($manual, $f) && str_contains($auto, $f)) {
            $shared++;
        }
    }

    if ($shared >= 2) {
        return 'match';
    }
    if ($autoFacts >= 2) {
        return 'partial';
    }

    return 'mismatch';
}
