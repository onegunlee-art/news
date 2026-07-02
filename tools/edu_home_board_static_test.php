<?php
/**
 * 홈 보드 — approved-only + 레벨 파티션 정적 검증
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$listPhp = file_get_contents($root . '/public/api/edu/quests/list.php');
if ($listPhp === false || !str_contains($listPhp, "status=eq.approved")) {
    $errors[] = 'list.php must query status=eq.approved';
}
if ($listPhp === false || !str_contains($listPhp, 'recommended_for_you')) {
    $errors[] = 'list.php must set recommended_for_you';
}
if ($listPhp === false || !str_contains($listPhp, 'difficulty_level')) {
    $errors[] = 'list.php must use difficulty_level';
}

$catalogPhp = file_get_contents($root . '/public/api/edu/lib/eduQuestCatalog.php');
if ($catalogPhp === false || !str_contains($catalogPhp, "'difficulty_level'")) {
    $errors[] = 'eduQuestToListItem must expose difficulty_level';
}

$categoriesPhp = file_get_contents($root . '/public/api/edu/quests/categories.php');
if ($categoriesPhp === false || !str_contains($categoriesPhp, "'levels'")) {
    $errors[] = 'categories.php must expose levels[]';
}
if ($categoriesPhp !== false && str_contains($categoriesPhp, "'frames'")) {
    $errors[] = 'categories.php must not expose frames[] to students';
}

$sectionsTs = file_get_contents($root . '/src/frontend/src/utils/eduHomeBoardSections.ts');
if ($sectionsTs === false || !str_contains($sectionsTs, 'partitionHomeBoard')) {
    $errors[] = 'eduHomeBoardSections must define partitionHomeBoard';
}
if ($sectionsTs === false || !str_contains($sectionsTs, 'myLevelRecommended')) {
    $errors[] = 'eduHomeBoardSections must have myLevelRecommended section';
}

$boardTs = file_get_contents($root . '/src/frontend/src/pages/edu/EduHomeBoard.tsx');
if ($boardTs === false || !str_contains($boardTs, '내 레벨 추천')) {
    $errors[] = 'EduHomeBoard must show my level recommended section';
}
if ($boardTs === false || !str_contains($boardTs, 'startSession')) {
    $errors[] = 'EduHomeBoard must start session on card select';
}

$exploreTs = file_get_contents($root . '/src/frontend/src/pages/edu/EduExplorePage.tsx');
if ($exploreTs !== false && str_contains($exploreTs, 'Myth Bust')) {
    $errors[] = 'EduExplorePage must not show Myth Bust label';
}
if ($exploreTs === false || !str_contains($exploreTs, 'EduQuestDifficultyBadge')) {
    $errors[] = 'EduExplorePage must use EduQuestDifficultyBadge';
}

if ($errors !== []) {
    fwrite(STDERR, "edu_home_board_static_test FAILED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

echo "edu_home_board_static_test OK\n";
