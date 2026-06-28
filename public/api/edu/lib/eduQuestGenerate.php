<?php
/**
 * GIST EDU Step 2 — gist 글 → edu_daily_quests draft 생성 (멱등·배치)
 *
 * 본체 MySQL READ-only. edu_daily_quests + edu_quest_articles WRITE.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';
require_once __DIR__ . '/eduHingeQuestMap.php';
require_once __DIR__ . '/eduAxisExtract.php';
require_once __DIR__ . '/eduQuestFilter.php';
require_once __DIR__ . '/eduCoachGuide.php';
require_once __DIR__ . '/eduQuestCatalog.php';
require_once __DIR__ . '/eduQuestArticleSnapshot.php';

const EDU_QUEST_GENERATE_SOURCE = 'p2-step2-batch';
const EDU_QUEST_GENERATE_MIN_AXES = 2;

/** 수동·레거시 시드 — UPDATE/덮어쓰기 금지 (news_id 중복 시에도 skip) */
const EDU_QUEST_GENERATE_PROTECTED_QUEST_CODES = [
    'Q-NUKE-AXIS-630',
    'Q-IRAN-FOREVER-001',
    'Q-M02-PHONE',
    'Q-M03-UNIFORM',
];

function eduQuestGenerateQuestCode(int $newsId): string
{
    return 'Q-GIST-' . $newsId;
}

/**
 * @return array<int, array{quest_code: string, quest_id: string, status: string}>
 */
function eduQuestGenerateExistingPrimaryMap(\Agents\Services\SupabaseService $supabase): array
{
    $map = [];
    $offset = 0;
    while (true) {
        $articles = $supabase->select(
            'edu_quest_articles',
            'role=eq.primary&order=news_id.asc&limit=200&offset=' . $offset,
            200
        ) ?? [];
        if ($articles === []) {
            break;
        }
        foreach ($articles as $row) {
            $newsId = (int) ($row['news_id'] ?? 0);
            if ($newsId <= 0) {
                continue;
            }
            $questId = (string) ($row['quest_id'] ?? '');
            if ($questId === '') {
                continue;
            }
            $quests = $supabase->select('edu_daily_quests', 'id=eq.' . $questId, 1) ?? [];
            $quest = $quests[0] ?? [];
            $map[$newsId] = [
                'quest_code' => (string) ($quest['quest_code'] ?? ''),
                'quest_id' => $questId,
                'status' => (string) ($quest['status'] ?? ''),
            ];
        }
        if (count($articles) < 200) {
            break;
        }
        $offset += 200;
    }

    return $map;
}

function eduQuestGenerateIsSkippable(int $newsId, array $existingPrimary): ?string
{
    if (isset($existingPrimary[$newsId])) {
        $code = $existingPrimary[$newsId]['quest_code'] ?? '';

        return "news_id={$newsId} already primary in {$code} (멱등 skip)";
    }

    return null;
}

function eduQuestGenerateIsProtectedQuestCode(string $questCode): bool
{
    if (in_array($questCode, EDU_QUEST_GENERATE_PROTECTED_QUEST_CODES, true)) {
        return true;
    }

    return !str_starts_with($questCode, 'Q-GIST-');
}

/**
 * @param list<array<string, mixed>> $axes
 * @return list<array<string, mixed>>
 */
function eduQuestGenerateGuideAxesFromExtraction(array $axes): array
{
    $out = [];
    $i = 1;
    foreach ($axes as $ax) {
        if (!is_array($ax)) {
            continue;
        }
        $point = trim((string) ($ax['point'] ?? ''));
        $question = trim((string) ($ax['core_question'] ?? ''));
        if ($point === '' || $question === '') {
            continue;
        }
        $fact = trim((string) ($ax['article_fact'] ?? ''));
        $weak = $fact !== ''
            ? mb_substr($fact, 0, 72) . ' — 이 사실이 네 말을 **강하게** 해주나 **약하게**?'
            : '기사 근거와 네 말 — **맞나/안 맞나** 한쪽만 골라봐.';

        $out[] = [
            'axis_id' => 'axis_' . $i,
            'point' => $point,
            'core_question' => $question,
            'article_fact' => $fact,
            'weak_scaffold' => $weak,
        ];
        $i++;
        if (count($out) >= 4) {
            break;
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $hinge
 * @return list<array<string, mixed>>
 */
function eduQuestGenerateFallbackAxesFromHinge(array $hinge): array
{
    $sideA = trim((string) ($hinge['side_a'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? ''));
    $shake = trim((string) ($hinge['shake_prompt'] ?? ''));
    $axes = [];

    if ($sideA !== '') {
        $axes[] = [
            'axis_id' => 'axis_1',
            'point' => mb_substr($sideA, 0, 80),
            'core_question' => '통념대로라면 — 네 말로 한 문장만 정리해볼래?',
            'article_fact' => mb_substr($shake !== '' ? $shake : $sideA, 0, 200),
            'weak_scaffold' => 'A쪽 입장 — **맞다/틀리다** 중 하나만 골라봐.',
        ];
    }
    if ($sideB !== '') {
        $axes[] = [
            'axis_id' => 'axis_2',
            'point' => mb_substr($sideB, 0, 80),
            'core_question' => '기사가 드러내는 반대쪽 — **한 가지**만 집어서 따져볼래?',
            'article_fact' => mb_substr($sideB, 0, 200),
            'weak_scaffold' => 'B쪽 — 기사와 **맞나/안 맞나**?',
        ];
    }

    return $axes;
}

/**
 * @param array<string, mixed> $hints
 * @return array<string, mixed>
 */
function eduQuestGenerateAttachCoachHints(array $hints, int $newsId, ?array $axisExtraction, array $hinge): array
{
    $fromFile = is_array($axisExtraction['axes'] ?? null) ? $axisExtraction['axes'] : [];
    $guideAxes = eduQuestGenerateGuideAxesFromExtraction($fromFile);

    if (count($guideAxes) < EDU_QUEST_GENERATE_MIN_AXES && in_array($newsId, [630, 150, 196, 288], true)) {
        $guideAxes = eduCoachGuideAxesForNewsId($newsId);
    }
    if (count($guideAxes) < EDU_QUEST_GENERATE_MIN_AXES) {
        $guideAxes = eduQuestGenerateFallbackAxesFromHinge($hinge);
    }

    $hints['coach_mode'] = 'axis_guide_v1';
    $hints['_guide_axes'] = $guideAxes;
    $hints['mode'] = 'adversarial';

    return $hints;
}

/**
 * @param array<string, mixed> $hinge
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function eduQuestGenerateBuildDraft(array $hinge, array $meta, ?array $axisExtraction, array $arcKeywords): array
{
    $newsId = (int) ($hinge['news_id'] ?? 0);
    $min = eduHingeMapToMinQuest($hinge);
    $min['quest_code'] = eduQuestGenerateQuestCode($newsId);

    $sideA = trim((string) ($hinge['side_a'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? ''));
    $shared = trim((string) ($min['hammer_hints']['shared_conclusion'] ?? ''));
    $min['pro_line'] = $sideA !== '' ? mb_substr($sideA, 0, 140) : '';
    $min['con_line'] = mb_substr($shared !== '' ? $shared : $sideB, 0, 140);

    $hints = $min['hammer_hints'];
    $hints = eduQuestGenerateAttachCoachHints($hints, $newsId, $axisExtraction, $hinge);
    $metaBlock = is_array($hints['_meta'] ?? null) ? $hints['_meta'] : [];
    $hints['_meta'] = array_merge($metaBlock, [
        'generator' => EDU_QUEST_GENERATE_SOURCE,
        'generated_at' => date('c'),
        'axis_count' => count($hints['_guide_axes'] ?? []),
    ]);
    $min['hammer_hints'] = $hints;

    $arcs = eduQuestMatchArcsForArticle($meta, $arcKeywords);
    $category = eduQuestCategoryForArc($arcs[0] ?? '') ?? null;
    $min['_db'] = [
        'grade_band' => 'middle',
        'status' => 'draft',
        'manual_arc' => $arcs[0] ?? ('GIST-' . $newsId),
        'pilot_priority' => null,
        'live_at' => null,
        'expires_at' => null,
        'scores' => array_filter([
            'source' => EDU_QUEST_GENERATE_SOURCE,
            'hinge_news_id' => $newsId,
            'category' => $category,
            'mapper_version' => $metaBlock['mapper_version'] ?? 'p2-a2-v1',
        ], static fn ($v) => $v !== null && $v !== ''),
    ];

    return $min;
}

/**
 * @param array<string, mixed> $draft
 * @return array{ok: bool, quest_id?: string, quest_code?: string, error?: string, skipped?: string}
 */
function eduQuestGeneratePersistDraft(
    \Agents\Services\SupabaseService $supabase,
    ?PDO $pdo,
    array $draft,
    array $existingPrimary,
    bool $dryRun = false
): array {
    $newsId = (int) ($draft['articles'][0]['news_id'] ?? 0);
    $questCode = (string) ($draft['quest_code'] ?? '');

    $skip = eduQuestGenerateIsSkippable($newsId, $existingPrimary);
    if ($skip !== null) {
        return ['ok' => false, 'skipped' => $skip];
    }

    if (eduQuestGenerateIsProtectedQuestCode($questCode)) {
        return ['ok' => false, 'skipped' => "protected quest_code {$questCode}"];
    }

    $existing = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1) ?? [];
    if (!empty($existing[0]['id'])) {
        return [
            'ok' => false,
            'skipped' => "quest_code {$questCode} already exists (멱등 skip)",
        ];
    }

    $db = is_array($draft['_db'] ?? null) ? $draft['_db'] : [];
    $hints = is_array($draft['hammer_hints'] ?? null) ? $draft['hammer_hints'] : [];
    if (count($hints['_guide_axes'] ?? []) < EDU_QUEST_GENERATE_MIN_AXES) {
        return ['ok' => false, 'error' => 'axes < ' . EDU_QUEST_GENERATE_MIN_AXES];
    }

    $row = [
        'quest_code' => $questCode,
        'quest_title' => $draft['quest_title'] ?? ('Quest ' . $newsId),
        'grade_band' => $db['grade_band'] ?? 'middle',
        'status' => 'draft',
        'manual_arc' => $db['manual_arc'] ?? ('GIST-' . $newsId),
        'pro_line' => $draft['pro_line'] ?? '',
        'con_line' => $draft['con_line'] ?? '',
        'alignment_summary' => $draft['alignment_summary'] ?? null,
        'conflict_summary' => $draft['conflict_summary'] ?? '',
        'hammer_hints' => $hints,
        'pilot_priority' => null,
        'live_at' => null,
        'expires_at' => null,
        'scores' => $db['scores'] ?? ['source' => EDU_QUEST_GENERATE_SOURCE],
    ];

    if ($dryRun) {
        return ['ok' => true, 'quest_code' => $questCode, 'skipped' => 'dry-run'];
    }

    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        return ['ok' => false, 'error' => 'insert failed: ' . $supabase->getLastError()];
    }

    $questId = (string) $inserted[0]['id'];
    $articles = is_array($draft['articles'] ?? null) ? $draft['articles'] : [];
    $sort = 0;
    foreach ($articles as $article) {
        if (!is_array($article)) {
            continue;
        }
        $supabase->insert('edu_quest_articles', [
            'quest_id' => $questId,
            'news_id' => (int) ($article['news_id'] ?? $newsId),
            'role' => $article['role'] ?? 'primary',
            'sort_order' => $sort++,
            'title' => $article['title'] ?? null,
            'gist_url' => $article['gist_url'] ?? ('https://www.thegist.co.kr/news/' . $newsId),
        ]);
    }

    $articleRows = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $questId, 5) ?? [];
    foreach ($articleRows as $articleRow) {
        eduBackfillQuestArticleSnapshot($supabase, $pdo, $articleRow, false);
    }

    return ['ok' => true, 'quest_id' => $questId, 'quest_code' => $questCode];
}

/**
 * @return array{hinge: array<string, mixed>, axis: ?array<string, mixed>, errors: list<string>}
 */
function eduQuestGenerateEnsureExtractions(
    $llm,
    PDO $pdo,
    int $newsId,
    bool $allowLlm
): array {
    $errors = [];
    $hinge = eduHingeLoadExtraction($newsId);
    if ($hinge === null && $allowLlm) {
        $article = eduHingeLoadMysqlContent($pdo, $newsId);
        if ($article === null) {
            return ['hinge' => [], 'axis' => null, 'errors' => ['content missing']];
        }
        $res = eduHingeExtractFromContent($llm, $newsId, $article['title'], $article['content']);
        if (!$res['ok']) {
            return ['hinge' => [], 'axis' => null, 'errors' => [$res['error'] ?? 'hinge extract failed']];
        }
        $hinge = $res['extraction'];
        eduHingeSaveExtraction($hinge);
    }
    if ($hinge === null) {
        return ['hinge' => [], 'axis' => null, 'errors' => ['hinge cache missing']];
    }

    $axis = eduAxisLoadExtraction($newsId);
    if ($axis === null && $allowLlm) {
        $article = eduHingeLoadMysqlContent($pdo, $newsId);
        if ($article === null) {
            $errors[] = 'content missing for axis';

            return ['hinge' => $hinge, 'axis' => null, 'errors' => $errors];
        }
        $hingeLine = trim((string) ($hinge['hinge'] ?? ''));
        $res = eduAxisExtractFromContent($llm, $newsId, $article['title'], $article['content'], $hingeLine);
        if (!$res['ok']) {
            $errors[] = $res['error'] ?? 'axis extract failed';

            return ['hinge' => $hinge, 'axis' => null, 'errors' => $errors];
        }
        $axis = $res['extraction'];
        eduAxisSaveExtraction($axis);
    }

    return ['hinge' => $hinge, 'axis' => $axis, 'errors' => $errors];
}

/**
 * @return list<int>
 */
function eduQuestGenerateCandidateNewsIds(PDO $pdo, int $limit, int $offset, array $existingPrimary): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $candidates = [];

    try {
        $st = $pdo->query(
            "SELECT id FROM news WHERE status = 'published' ORDER BY published_at DESC LIMIT 500"
        );
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($existingPrimary[$id])) {
                continue;
            }
            $candidates[] = $id;
        }
    } catch (Throwable $e) {
        return array_slice(eduQuestFilterDefaultSampleIds(), $offset, $limit);
    }

    return array_slice($candidates, $offset, $limit * 3);
}

/**
 * @return array<string, mixed>
 */
function eduQuestGenerateReviewRow(array $draft, array $persist): array
{
    $newsId = (int) ($draft['articles'][0]['news_id'] ?? 0);
    $hinge = $draft['hammer_hints']['_hinge']['hinge'] ?? $draft['conflict_summary'] ?? '';

    return [
        'news_id' => $newsId,
        'quest_code' => $draft['quest_code'] ?? '',
        'title' => $draft['quest_title'] ?? '',
        'hinge' => $hinge,
        'axes' => count($draft['hammer_hints']['_guide_axes'] ?? []),
        'status' => 'draft',
        'gist_url' => 'https://www.thegist.co.kr/news/' . $newsId,
        'persist' => $persist,
    ];
}

/**
 * Q-GIST draft 중 선언문 제외 · filter score 순 후보.
 *
 * @return list<array<string, mixed>>
 */
function eduQuestListAnalysisDraftCandidates(\Agents\Services\SupabaseService $supabase): array
{
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
            if (($class['verdict'] ?? '') === '불가') {
                continue;
            }
            $rows[] = [
                'quest_id' => (string) ($quest['id'] ?? ''),
                'news_id' => $newsId,
                'quest_code' => $code,
                'title' => $meta['title'],
                'hinge' => $hinge['hinge'] ?? ($quest['conflict_summary'] ?? ''),
                'filter_verdict' => $class['verdict'] ?? '',
                'filter_score' => (int) ($class['score'] ?? 0),
                'axes' => count($hints['_guide_axes'] ?? []),
                'gist_url' => 'https://www.thegist.co.kr/news/' . $newsId,
            ];
        }
        if (count($batch) < 100) {
            break;
        }
        $offset += 100;
    }

    usort($rows, static fn ($a, $b) => ($b['filter_score'] ?? 0) <=> ($a['filter_score'] ?? 0));

    return $rows;
}
