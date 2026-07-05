<?php
/**
 * Static checks — parent report public URL share
 *
 * Usage: php tools/edu_parent_report_share_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'database/migrations/edu_parent_report_shares.sql',
    'public/api/edu/lib/eduParentReportShare.php',
    'public/api/edu/parent_report/view.php',
    'src/frontend/src/utils/eduShareReportUrl.ts',
    'src/frontend/src/pages/edu/EduParentReportPublicPage.tsx',
];

foreach ($files as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = "missing: {$rel}";
    }
}

$ops = (string) file_get_contents($root . '/public/api/edu/lib/eduOperatorReports.php');
if (!str_contains($ops, "action === 'share_link'")) {
    $errors[] = 'eduOperatorReports.php missing share_link action';
}

$api = (string) file_get_contents($root . '/src/frontend/src/services/eduOperatorApi.ts');
if (!str_contains($api, 'eduOperatorCreateReportShareLink')) {
    $errors[] = 'eduOperatorApi missing eduOperatorCreateReportShareLink';
}

$app = (string) file_get_contents($root . '/src/frontend/src/App.tsx');
if (!str_contains($app, '/report/:token')) {
    $errors[] = 'App.tsx missing /report/:token route';
}

$panel = (string) file_get_contents($root . '/src/frontend/src/components/edu/EduOperatorReportPanel.tsx');
if (!str_contains($panel, '리포트 링크 공유하기')) {
    $errors[] = 'EduOperatorReportPanel must use link share label';
}

$shareLib = (string) file_get_contents($root . '/public/api/edu/lib/eduParentReportShare.php');
foreach (['eduParentReportShareGenerateToken', '/report/', 'report_snapshot'] as $needle) {
    if (!str_contains($shareLib, $needle)) {
        $errors[] = "eduParentReportShare.php missing {$needle}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_parent_report_share_static_test\n");
