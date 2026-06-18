<?php
/**
 * GET /api/edu/quests/categories.php — explore shelf chips + counts
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

$frame = trim((string) ($_GET['frame'] ?? 'all'));
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
$total = 0;

foreach ($rows as $quest) {
    if (!eduQuestMatchesFrameFilter($quest, $frame)) {
        continue;
    }
    $total++;
    $meta = eduQuestListCategoryMeta($quest);
    $shelf = $meta['shelf'] ?? null;
    if ($shelf !== null && isset($shelfCounts[$shelf])) {
        $shelfCounts[$shelf]++;
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

eduSendJson([
    'success' => true,
    'total' => $total,
    'shelves' => $shelves,
    'frames' => [
        ['id' => 'all', 'label' => '전체'],
        ['id' => 'decision_inquiry', 'label' => '결정 탐구'],
        ['id' => 'myth_bust', 'label' => 'Myth Bust'],
    ],
]);
