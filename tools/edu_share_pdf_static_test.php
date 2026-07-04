<?php
/**
 * eduSharePdf static checks — Samsung text-share fallback
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
    "return 'downloaded'",
];

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
