<?php
/**
 * UI 2단계 — 완주 후 콤보 정적 검증 (게이지 보너스 없음)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$comboTs = file_get_contents($root . '/src/frontend/src/utils/eduQuestCombo.ts');
if ($comboTs === false || !str_contains($comboTs, 'recordTodayQuestCompletion')) {
    $errors[] = 'eduQuestCombo must track today completion count';
}
if ($comboTs === false || str_contains(strtolower($comboTs), 'gauge') || str_contains(strtolower($comboTs), 'xp_gained')) {
    $errors[] = 'eduQuestCombo must not touch gauge or XP';
}

$continueTs = file_get_contents($root . '/src/frontend/src/components/edu/EduQuestComboContinue.tsx');
if ($continueTs === false || !str_contains($continueTs, 'pickNextQuestRecommendation')) {
    $errors[] = 'EduQuestComboContinue must pick next approved quest';
}
if ($continueTs === false || !str_contains($continueTs, 'startSession')) {
    $errors[] = 'EduQuestComboContinue must start next quest session';
}
if ($continueTs === false || !str_contains($continueTs, '오늘은 여기까지')) {
    $errors[] = 'EduQuestComboContinue must offer opt-out CTA';
}

$cardsTs = file_get_contents($root . '/src/frontend/src/pages/edu/QuestFlowCards.tsx');
if ($cardsTs === false || !str_contains($cardsTs, 'EduQuestComboContinue')) {
    $errors[] = 'QuestFlowCards must render combo continue panel';
}

$chatTs = file_get_contents($root . '/src/frontend/src/pages/edu/QuestFlowChat.tsx');
if ($chatTs === false || !str_contains($chatTs, 'EduQuestComboContinue')) {
    $errors[] = 'QuestFlowChat must render combo continue panel';
}

$composePhp = file_get_contents($root . '/public/api/edu/session/compose.php');
if ($composePhp !== false && preg_match('/combo/i', $composePhp)) {
    $errors[] = 'compose.php must not reference combo (no gauge bonus)';
}

if ($errors !== []) {
    fwrite(STDERR, "edu_quest_combo_static_test FAILED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

echo "edu_quest_combo_static_test OK\n";
