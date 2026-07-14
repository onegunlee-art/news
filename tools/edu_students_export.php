<?php
/**
 * GIST EDU — active 학생 CSV export (기보 제출용)
 *
 * Usage:
 *   php tools/edu_students_export.php
 *   php tools/edu_students_export.php --output=storage/edu-students-export-20260714.csv
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentsExport.php';

$opts = getopt('', ['output::']);
$defaultOut = $root . '/storage/edu-students-export-' . date('Ymd-His') . '.csv';
$outputPath = (string) ($opts['output'] ?? $defaultOut);
if (!str_starts_with($outputPath, '/') && !preg_match('/^[A-Za-z]:\\\\/', $outputPath)) {
    $outputPath = $root . '/' . ltrim(str_replace('\\', '/', $outputPath), '/');
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

echo "=== EDU students CSV export ===\n";

$result = eduStudentsExportBuild($supabase, eduStudentsExportSelectFilterAll());

$dir = dirname($outputPath);
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create directory: {$dir}\n");
    exit(1);
}

if (file_put_contents($outputPath, $result['csv']) === false) {
    fwrite(STDERR, "Cannot write: {$outputPath}\n");
    exit(1);
}

echo "Wrote: {$outputPath}\n";
echo eduStudentsExportSummaryLine($result['summary']) . PHP_EOL;
