<?php
/**
 * edu share static checks — URL share (dashboard) + PDF download helper
 *
 * Usage: php tools/edu_share_pdf_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/src/frontend/src/utils/eduSharePdf.ts';
$src = (string) file_get_contents($path);
$errors = [];

$needles = [
    'SamsungBrowser',
    'eduSharePdfIsMobileBrowser',
    'eduSharePdfCanShareFiles',
    'eduSharePdfBuildTextSharePayload',
    'eduSharePdfCanShareText',
    'triggerPdfDownload',
    'downloadPdfFile',
    'eduShareDiagStep',
    'isGestureError',
    'NotAllowedError',
    'gestureBlocked',
    "return { result: 'downloaded', diagnostics: diag, gestureBlocked",
];

$diagPath = $root . '/src/frontend/src/utils/eduSharePdfDiagnose.ts';
if (!is_file($diagPath)) {
    $errors[] = 'missing eduSharePdfDiagnose.ts';
} else {
    $diagSrc = (string) file_get_contents($diagPath);
    if (!str_contains($diagSrc, 'share_debug')) {
        $errors[] = 'eduSharePdfDiagnose.ts missing share_debug flag';
    }
}

$urlSharePath = $root . '/src/frontend/src/utils/eduShareReportUrl.ts';
if (!is_file($urlSharePath)) {
    $errors[] = 'missing eduShareReportUrl.ts';
} elseif (!str_contains((string) file_get_contents($urlSharePath), 'navigator.share')) {
    $errors[] = 'eduShareReportUrl.ts must use navigator.share for URL';
}

$dashPath = $root . '/src/frontend/src/pages/edu/EduDashboardPage.tsx';
$dashSrc = (string) file_get_contents($dashPath);
if (!str_contains($dashSrc, 'eduOperatorCreateReportShareLink')) {
    $errors[] = 'EduDashboardPage must create report share link';
}
if (!str_contains($dashSrc, 'shareReportUrl')) {
    $errors[] = 'EduDashboardPage must use shareReportUrl (URL share)';
}
if (str_contains($dashSrc, 'sharePdfFile')) {
    $errors[] = 'EduDashboardPage must not use PDF file share';
}
if (!str_contains($dashSrc, 'downloadPdfFile')) {
    $errors[] = 'EduDashboardPage must keep PDF download via downloadPdfFile';
}

foreach ($needles as $needle) {
    if (!str_contains($src, $needle)) {
        $errors[] = "eduSharePdf.ts missing: {$needle}";
    }
}

// Text-only share fallback must exist (Samsung Internet)
if (!str_contains($src, 'eduSharePdfBuildTextSharePayload(meta)')) {
    $errors[] = 'missing text/url share fallback for Samsung Internet';
}

// Must not use old text-only share that triggers download before sheet
if (preg_match('/PDF는 저장 후 카카오톡에서 파일로 첨부/', $src)) {
    $errors[] = 'remove legacy text-share+download combo that skips share sheet';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_share_pdf_static_test\n");
