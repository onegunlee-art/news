<?php
/**
 * Static gate checks — parent report + EDU operator auth
 *
 * Usage: php tools/edu_parent_report_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'public/api/edu/lib/eduAdminAuth.php',
    'public/api/edu/lib/eduOperatorAuth.php',
    'public/api/edu/operator/reports.php',
    'public/api/edu/lib/eduParentReportData.php',
    'public/api/edu/lib/eduParentReportNarrative.php',
    'public/api/edu/lib/eduParentReportPdf.php',
];

foreach ($files as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = "missing file: {$rel}";
    }
}

$reportsPhp = (string) file_get_contents($root . '/public/api/edu/operator/reports.php');
if (!str_contains($reportsPhp, 'eduRequireOperator')) {
    $errors[] = 'operator/reports.php must call eduRequireOperator()';
}

$pagePath = $root . '/src/frontend/src/pages/edu/EduOperatorReportsPage.tsx';
$apiPath = $root . '/src/frontend/src/services/eduOperatorApi.ts';
if (!is_file($pagePath)) {
    $errors[] = 'missing EduOperatorReportsPage.tsx';
} else {
    $page = (string) file_get_contents($pagePath);
    if (str_contains($page, "navigate('/login'") || str_contains($page, 'useAuthStore')) {
        $errors[] = 'EduOperatorReportsPage must use EDU operator auth only';
    }
}
if (!is_file($apiPath)) {
    $errors[] = 'missing eduOperatorApi.ts';
} elseif (!str_contains((string) file_get_contents($apiPath), '/api/edu/operator/reports.php')) {
    $errors[] = 'eduOperatorApi must use /api/edu/operator/reports.php';
}

$appTsx = (string) file_get_contents($root . '/src/frontend/src/App.tsx');
if (!str_contains($appTsx, '/edu/operator/reports')) {
    $errors[] = 'App.tsx must register /edu/operator/reports route';
}
if (!str_contains($appTsx, '/edu/operator/login')) {
    $errors[] = 'App.tsx must register /edu/operator/login route';
}

$pdfPhp = (string) file_get_contents($root . '/public/api/edu/lib/eduParentReportPdf.php');
$fontChecks = [
    'eduParentReportFontPaths',
    'NotoSansKR-Regular.otf',
    'isFontSubsettingEnabled',
    'page-break-after: always',
    'brand-dot',
];
foreach ($fontChecks as $needle) {
    if (!str_contains($pdfPhp, $needle)) {
        $errors[] = "eduParentReportPdf.php missing layout/font fix: {$needle}";
    }
}
if (str_contains($pdfPhp, 'NotoSansKR-Regular.ttf')) {
    $errors[] = 'eduParentReportPdf.php must not reference missing NotoSansKR-Regular.ttf';
}
if (str_contains($pdfPhp, 'favicon-G-edu.svg')) {
    $errors[] = 'eduParentReportPdf.php must not use SVG logo (dompdf unreliable)';
}
if (str_contains($pdfPhp, 'favicon-192.png')) {
    $errors[] = 'eduParentReportPdf.php must not require PNG logo (GD dependency)';
}

$installed = $root . '/public/fonts/noto/installed-fonts.json';
if (is_file($installed)) {
    $json = (string) file_get_contents($installed);
    if (str_contains($json, ':\\') || str_contains($json, 'C:/')) {
        $errors[] = 'installed-fonts.json must use relative font paths only';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_parent_report_static_test (" . count($files) . " files + gates)\n");
