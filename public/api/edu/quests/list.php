<?php
/**
 * GET /api/edu/quests/list.php — approved 퀘스트 목록 (live_at 무관)
 *
 * Query:
 *   limit=20 (max 50)
 *   level=1|2|3|4|5  (optional difficulty filter)
 *   category=middle_east_iran  (scores.category)
 *   shelf=war_security         (student shelf → multiple categories)
 *   frame=  (deprecated — ignored for student list; kept for internal compat)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduQuestCatalog.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduQuestDifficulty.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduGetStudentOptional();
$supabase = eduSupabase();

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$category = trim((string) ($_GET['category'] ?? ''));
$shelf = trim((string) ($_GET['shelf'] ?? ''));
$levelFilter = (int) ($_GET['level'] ?? 0);
if ($levelFilter < 1 || $levelFilter > 5) {
    $levelFilter = 0;
}

$coachLevel = $student !== null
    ? eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_DEFAULT))
    : null;

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

$filteredRows = [];
foreach ($rows as $quest) {
    if (($quest['status'] ?? '') !== 'approved') {
        continue;
    }
    if ($levelFilter > 0) {
        $qLevel = eduQuestReadDifficultyLevel($quest);
        if ($qLevel !== $levelFilter) {
            continue;
        }
    }
    if (!eduQuestMatchesCategoryFilter($quest, $categoryFilter)) {
        continue;
    }
    $filteredRows[] = $quest;
}

if ($coachLevel !== null) {
    usort($filteredRows, static function (array $a, array $b) use ($coachLevel): int {
        $aLevel = eduQuestReadDifficultyLevel($a);
        $bLevel = eduQuestReadDifficultyLevel($b);
        $aMatch = $aLevel === $coachLevel ? 0 : 1;
        $bMatch = $bLevel === $coachLevel ? 0 : 1;
        if ($aMatch !== $bMatch) {
            return $aMatch <=> $bMatch;
        }
        $aLive = strtotime((string) ($a['live_at'] ?? '')) ?: 0;
        $bLive = strtotime((string) ($b['live_at'] ?? '')) ?: 0;

        return $bLive <=> $aLive;
    });
}

$filteredRows = array_slice($filteredRows, 0, $limit);

$primaryNewsIds = eduBatchPrimaryNewsIdsForQuests($supabase, array_map(static fn (array $q) => (string) ($q['id'] ?? ''), $filteredRows));
$imageMap = eduNewsImageUrlsByIds(array_values($primaryNewsIds));

$quests = [];
foreach ($filteredRows as $quest) {
    $questId = (string) ($quest['id'] ?? '');
    $newsId = $primaryNewsIds[$questId] ?? 0;
    $coverUrl = $newsId > 0 ? ($imageMap[$newsId] ?? null) : null;
    $item = eduQuestToListItem($quest, $completedIds, $coverUrl);
    $item['recommended_for_you'] = $coachLevel !== null
        && ($item['difficulty_level'] ?? null) === $coachLevel;
    $quests[] = $item;
}

eduSendJson([
    'success' => true,
    'quests' => $quests,
    'count' => count($quests),
    'coach_level' => $coachLevel,
    'filters' => [
        'level' => $levelFilter > 0 ? $levelFilter : null,
        'category' => $category !== '' ? $category : null,
        'shelf' => $shelf !== '' ? $shelf : null,
    ],
]);
