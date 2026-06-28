<?php
/**
 * 홈 보드 — approved-only 안전망 정적 검증
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$listPhp = file_get_contents($root . '/public/api/edu/quests/list.php');
if ($listPhp === false || !str_contains($listPhp, "status=eq.approved")) {
    $errors[] = 'list.php must query status=eq.approved';
}
if ($listPhp === false || !str_contains($listPhp, "!== 'approved'")) {
    $errors[] = 'list.php must skip non-approved rows in loop';
}

$catalogPhp = file_get_contents($root . '/public/api/edu/lib/eduQuestCatalog.php');
if ($catalogPhp === false || !str_contains($catalogPhp, "'status'")) {
    $errors[] = 'eduQuestToListItem must expose status field';
}

$sectionsTs = file_get_contents($root . '/src/frontend/src/utils/eduHomeBoardSections.ts');
if ($sectionsTs === false || !str_contains($sectionsTs, 'filterApprovedQuestsForHome')) {
    $errors[] = 'eduHomeBoardSections must filter approved on client';
}

$boardTs = file_get_contents($root . '/src/frontend/src/pages/edu/EduHomeBoard.tsx');
if ($boardTs === false || !str_contains($boardTs, 'filterApprovedQuestsForHome')) {
    $errors[] = 'EduHomeBoard must apply approved filter';
}
if ($boardTs === false || !str_contains($boardTs, 'startSession')) {
    $errors[] = 'EduHomeBoard must start session on card select';
}

if ($errors !== []) {
    fwrite(STDERR, "edu_home_board_static_test FAILED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

echo "edu_home_board_static_test OK\n";
