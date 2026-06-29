<?php
/**
 * EDU operator admin — auth separation static checks
 *
 * Usage: php tools/edu_admin_account_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$required = [
    'database/migrations/edu_operators.sql',
    'public/api/edu/lib/eduOperatorAuth.php',
    'public/api/edu/lib/eduOperatorReports.php',
    'public/api/edu/operator/login.php',
    'public/api/edu/operator/me.php',
    'public/api/edu/operator/reports.php',
    'src/frontend/src/pages/edu/EduOperatorLoginPage.tsx',
    'src/frontend/src/utils/eduOperatorSession.ts',
    'tools/edu_seed_operator.php',
];

foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = "missing: {$rel}";
    }
}

$reportsPage = (string) file_get_contents($root . '/src/frontend/src/pages/edu/EduOperatorReportsPage.tsx');
if (str_contains($reportsPage, 'useAuthStore') || str_contains($reportsPage, "navigate('/login'")) {
    $errors[] = 'EduOperatorReportsPage must not use gist main auth/login';
}
if (!str_contains($reportsPage, '/edu/operator/login')) {
    $errors[] = 'EduOperatorReportsPage must redirect to EDU operator login';
}

$operatorApi = (string) file_get_contents($root . '/src/frontend/src/services/eduOperatorApi.ts');
if (str_contains($operatorApi, 'adminFetch') || str_contains($operatorApi, 'edu-parent-report.php')) {
    $errors[] = 'eduOperatorApi must use /api/edu/operator/* not gist admin API';
}
if (!str_contains($operatorApi, 'X-Edu-Operator-Token')) {
    $errors[] = 'eduOperatorApi must send X-Edu-Operator-Token';
}

$reportsPhp = (string) file_get_contents($root . '/public/api/edu/operator/reports.php');
if (!str_contains($reportsPhp, 'eduRequireOperator')) {
    $errors[] = 'operator/reports.php must call eduRequireOperator()';
}

$menu = (string) file_get_contents($root . '/src/frontend/src/utils/eduTopBarMenu.ts');
if (!str_contains($menu, '리포트 관리') || !str_contains($menu, 'hasEduOperatorSession')) {
    $errors[] = 'eduTopBarMenu must add report link for operator session only';
}

$app = (string) file_get_contents($root . '/src/frontend/src/App.tsx');
if (!str_contains($app, '/edu/operator/login')) {
    $errors[] = 'App.tsx must register /edu/operator/login';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_admin_account_static_test\n");
