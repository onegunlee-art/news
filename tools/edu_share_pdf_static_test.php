<?php
/**
 * eduSharePdf static checks — desktop must not rely on navigator.share alone
 *
 * Usage: php tools/edu_share_pdf_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/src/frontend/src/utils/eduSharePdf.ts';
$src = (string) file_get_contents($path);
$errors = [];

$needles = [
    'eduSharePdfDeviceLikelySupportsFiles',
    'eduSharePdfCanShareFiles',
    'triggerPdfDownload',
    'downloadPdfFile',
    'return \'downloaded\'',
];

foreach ($needles as $needle) {
    if (!str_contains($src, $needle)) {
        $errors[] = "eduSharePdf.ts missing: {$needle}";
    }
}

// Must not retry text-only share after file share fails (desktop "try again" trap)
if (preg_match('/PDF는 저장 후 카카오톡에서 파일로 첨부/', $src)) {
    $errors[] = 'remove text-only navigator.share fallback (causes desktop retry popup)';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK edu_share_pdf_static_test\n");
