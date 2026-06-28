<?php
/**
 * GET /api/edu/quests/list.php — approved 퀘스트 목록 (live_at 무관)
 *
 * Query:
 *   limit=20 (max 50)
 *   frame=all | decision_inquiry (default) | myth_bust
 *   category=middle_east_iran  (scores.category)
 *   shelf=war_security         (student shelf → multiple categories)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduQuestCatalog.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduGetStudentOptional();
$supabase = eduSupabase();

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$frame = trim((string) ($_GET['frame'] ?? 'all'));
$category = trim((string) ($_GET['category'] ?? ''));
$shelf = trim((string) ($_GET['shelf'] ?? ''));

$categoryFilter = [];
if ($category !== '') {
    $categoryFilter = [$category];
} elseif ($shelf !== '') {
    $categoryFilter = eduQuestCategoriesForShelf($shelf);
}

$fetchLimit = min(200, max($limit * 3, 50));
$rows = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&order=live_at.desc.nullslast,created_at.desc',
    $fetchLimit
) ?? [];

$completedIds = [];
if ($student !== null && $rows !== []) {
    $sessions = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $student['id'] . '&' . eduSessionStageFilterCompleted(),
        200
    ) ?? [];
    foreach ($sessions as $s) {
        if (!empty($s['quest_id'])) {
            $completedIds[$s['quest_id']] = true;
        }
    }
}

$quests = [];
$filteredRows = [];
foreach ($rows as $quest) {
    // 홈 보드 안전망: draft·선언문 등 비-approved는 절대 목록에 포함하지 않음
    if (($quest['status'] ?? '') !== 'approved') {
        continue;
    }
    if (!eduQuestMatchesFrameFilter($quest, $frame)) {
        continue;
    }
    if (!eduQuestMatchesCategoryFilter($quest, $categoryFilter)) {
        continue;
    }
    $filteredRows[] = $quest;
    if (count($filteredRows) >= $limit) {
        break;
    }
}

$primaryNewsIds = eduBatchPrimaryNewsIdsForQuests($supabase, array_map(static fn (array $q) => (string) ($q['id'] ?? ''), $filteredRows));
$imageMap = eduNewsImageUrlsByIds(array_values($primaryNewsIds));

foreach ($filteredRows as $quest) {
    $questId = (string) ($quest['id'] ?? '');
    $newsId = $primaryNewsIds[$questId] ?? 0;
    $coverUrl = $newsId > 0 ? ($imageMap[$newsId] ?? null) : null;
    $quests[] = eduQuestToListItem($quest, $completedIds, $coverUrl);
}

eduSendJson([
    'success' => true,
    'quests' => $quests,
    'count' => count($quests),
    'filters' => [
        'frame' => $frame,
        'category' => $category !== '' ? $category : null,
        'shelf' => $shelf !== '' ? $shelf : null,
    ],
]);
