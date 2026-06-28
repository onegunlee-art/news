<?php
/**
 * Static gate checks — parent report admin auth surface
 *
 * Usage: php tools/edu_parent_report_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'public/api/edu/lib/eduAdminAuth.php',
    'public/api/edu/admin/students.php',
    'public/api/edu/admin/parent_report.php',
    'public/api/admin/edu-parent-report.php',
    'public/api/edu/lib/eduParentReportData.php',
    'public/api/edu/lib/eduParentReportNarrative.php',
    'public/api/edu/lib/eduParentReportPdf.php',
];

foreach ($files as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = "missing file: {$rel}";
    }
}

$studentsPhp = (string) file_get_contents($root . '/public/api/edu/admin/students.php');
if (!str_contains($studentsPhp, 'eduRequireAdminKey()')) {
    $errors[] = 'students.php must call eduRequireAdminKey()';
}

$parentPhp = (string) file_get_contents($root . '/public/api/admin/edu-parent-report.php');
if (!str_contains($parentPhp, 'requireAdminApi')) {
    $errors[] = 'edu-parent-report.php must call requireAdminApi()';
}
if (!str_contains($parentPhp, 'eduParentReportBuildPayload')) {
    $errors[] = 'edu-parent-report.php must use eduParentReportBuildPayload';
}

$eduParentAdmin = (string) file_get_contents($root . '/public/api/edu/admin/parent_report.php');
if (!str_contains($eduParentAdmin, 'eduRequireAdminKey()')) {
    $errors[] = 'parent_report.php must call eduRequireAdminKey()';
}

$appTsx = (string) file_get_contents($root . '/src/frontend/src/App.tsx');
if (!str_contains($appTsx, '/edu/operator/reports')) {
    $errors[] = 'App.tsx must register /edu/operator/reports route';
}

$pagePath = $root . '/src/frontend/src/pages/edu/EduOperatorReportsPage.tsx';
$apiPath = $root . '/src/frontend/src/services/eduOperatorApi.ts';
if (!is_file($pagePath)) {
    $errors[] = 'missing EduOperatorReportsPage.tsx';
} else {
    $page = (string) file_get_contents($pagePath);
    if (!str_contains($page, "role") || !str_contains($page, 'admin')) {
        $errors[] = 'EduOperatorReportsPage must gate admin role';
    }
}
if (!is_file($apiPath)) {
    $errors[] = 'missing eduOperatorApi.ts';
} elseif (!str_contains((string) file_get_contents($apiPath), 'edu-parent-report.php')) {
    $errors[] = 'eduOperatorApi must use edu-parent-report admin API';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_parent_report_static_test (" . count($files) . " files + gates)\n");
