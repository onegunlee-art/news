<?php
/**
 * GIST EDU — students CSV export 검증
 *
 * Usage:
 *   php tools/edu_students_export_verify.php --csv=storage/edu-students-export-YYYYMMDD.csv
 *   php tools/edu_students_export_verify.php --run-export
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';

$opts = getopt('', ['csv::', 'run-export', 'target::']);
$target = max(1, (int) ($opts['target'] ?? 273));
$runExport = isset($opts['run-export']);

$pass = 0;
$fail = 0;

function okv(bool $cond, string $label): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS {$label}\n";
    } else {
        $fail++;
        echo "FAIL {$label}\n";
    }
}

$csvPath = (string) ($opts['csv'] ?? '');
if ($runExport) {
    $exportScript = $root . '/tools/edu_students_export.php';
    $out = $root . '/storage/edu-students-export-verify-' . date('Ymd-His') . '.csv';
    passthru('php ' . escapeshellarg($exportScript) . ' --output=' . escapeshellarg($out), $code);
    if ($code !== 0) {
        fwrite(STDERR, "export failed\n");
        exit(1);
    }
    $csvPath = $out;
}

if ($csvPath === '') {
    fwrite(STDERR, "Usage: --csv=path.csv OR --run-export\n");
    exit(1);
}

if (!str_starts_with($csvPath, '/') && !preg_match('/^[A-Za-z]:\\\\/', $csvPath)) {
    $csvPath = $root . '/' . ltrim(str_replace('\\', '/', $csvPath), '/');
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: {$csvPath}\n");
    exit(1);
}

echo "=== EDU students export verify ===\n";
echo "file={$csvPath}\n\n";

$raw = (string) file_get_contents($csvPath);
okv(str_starts_with($raw, "\xEF\xBB\xBF"), 'UTF-8 BOM present (Excel Korean)');

$content = str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
$lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
okv(count($lines) >= 2, 'CSV has header + data rows');

$expectedHeaders = [
    'no',
    'display_name',
    'grade_band',
    'source_type',
    'email',
    'kakao_id',
    'has_contact',
    'completed_count',
    'created_at',
    'last_active_at',
    'invite_code',
    'student_id',
];
$header = str_getcsv($lines[0], ',', '"', '\\');
okv($header === $expectedHeaders, 'CSV header columns match');

$dataRows = array_slice($lines, 1);
okv(count($dataRows) === $target, 'active rows = ' . $target . ' (actual ' . count($dataRows) . ')');

$synthRows = 0;
$synthWithContact = 0;
$hasContactY = 0;

foreach ($dataRows as $line) {
    if ($line === '') {
        continue;
    }
    $cols = str_getcsv($line, ',', '"', '\\');
    if (count($cols) < 12) {
        continue;
    }
    $sourceType = $cols[3];
    $email = trim($cols[4]);
    $kakaoId = trim($cols[5]);
    $hasContact = $cols[6];

    if ($hasContact === 'Y') {
        $hasContactY++;
    }
    if ($sourceType === 'synth_seed') {
        $synthRows++;
        if ($email !== '' || $kakaoId !== '' || $hasContact === 'Y') {
            $synthWithContact++;
        }
    }
}

okv($synthWithContact === 0, 'synth_seed rows have no email/kakao_id (' . $synthRows . ' synth rows)');
okv($hasContactY <= count($dataRows), 'has_contact=Y count sane (' . $hasContactY . ')');

echo PHP_EOL . "Result: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
