<?php
/**
 * EDU 브랜드 리프레시 — 디자인만, 로직 무관 정적 검증
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$theme = file_get_contents($root . '/src/frontend/src/constants/eduGameTheme.ts');
if ($theme === false || !str_contains($theme, '#D85A30')) {
    $errors[] = 'eduGame primary must be #D85A30';
}

foreach (
    [
        'EduGistudyLogo.tsx',
        'EduCoachLevelIcon.tsx',
        'EduGamingStreakFlame.tsx',
        'EduTopBar.tsx',
    ] as $file
) {
    if (!is_file($root . '/src/frontend/src/components/edu/' . $file)) {
        $errors[] = "missing component: {$file}";
    }
}

$tier = file_get_contents($root . '/src/frontend/src/utils/eduStreakFlameTier.ts');
if ($tier === false || !str_contains($tier, 'eduStreakFlameTier')) {
    $errors[] = 'eduStreakFlameTier must define 1/3/7 day tiers';
}

$badge = file_get_contents($root . '/src/frontend/src/components/edu/EduCoachLevelBadge.tsx');
if ($badge === false || !str_contains($badge, 'EduCoachLevelIcon')) {
    $errors[] = 'EduCoachLevelBadge must use SVG level icons';
}

$flame = file_get_contents($root . '/src/frontend/src/components/edu/EduGamingStreakFlame.tsx');
if ($flame === false || !str_contains($flame, 'eduStreakFlameTier')) {
    $errors[] = 'EduGamingStreakFlame must use streak tier from streakDays';
}

$board = file_get_contents($root . '/src/frontend/src/pages/edu/EduHomeBoard.tsx');
if ($board === false || !str_contains($board, 'EduTopBar')) {
    $errors[] = 'EduHomeBoard must use EduTopBar';
}

if ($errors !== []) {
    fwrite(STDERR, "edu_brand_refresh_static_test FAILED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

echo "edu_brand_refresh_static_test OK\n";
