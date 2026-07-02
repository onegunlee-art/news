<?php
/**
 * GET /api/edu/quests/categories.php — explore shelf chips + level counts
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduQuestCatalog.php';
require_once __DIR__ . '/../lib/eduQuestDifficulty.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$supabase = eduSupabase();

$rows = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&order=created_at.desc',
    200
) ?? [];

$shelfCounts = [];
foreach (eduQuestShelfDefinitions() as $shelfId => $def) {
    $shelfCounts[$shelfId] = 0;
}
$levelCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$total = 0;

foreach ($rows as $quest) {
    $total++;
    $meta = eduQuestListCategoryMeta($quest);
    $shelf = $meta['shelf'] ?? null;
    if ($shelf !== null && isset($shelfCounts[$shelf])) {
        $shelfCounts[$shelf]++;
    }
    $lv = eduQuestReadDifficultyLevel($quest);
    if ($lv !== null && $lv >= 1 && $lv <= 5) {
        $levelCounts[$lv]++;
    }
}

$shelves = [];
foreach (eduQuestShelfDefinitions() as $shelfId => $def) {
    $shelves[] = [
        'shelf_id' => $shelfId,
        'label' => $def['label'],
        'count' => $shelfCounts[$shelfId] ?? 0,
        'categories' => $def['categories'],
    ];
}

$levels = [];
foreach ([1, 2, 3, 4, 5] as $lv) {
    $labels = eduQuestDifficultyLabel($lv);
    $levels[] = [
        'id' => $lv,
        'label_ko' => $labels['ko'],
        'label_en' => $labels['en'],
        'count' => $levelCounts[$lv] ?? 0,
    ];
}

eduSendJson([
    'success' => true,
    'total' => $total,
    'shelves' => $shelves,
    'levels' => $levels,
]);
