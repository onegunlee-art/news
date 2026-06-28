<?php
/**
 * CLI — 부모 리포트 생성 (운영자/로컬 검증)
 *
 * Usage:
 *   php tools/edu_parent_report_generate.php --student-id=UUID [--pdf=out.pdf] [--skip-narrative]
 *   php tools/edu_parent_report_generate.php --list
 *
 * Admin key (--admin-key) optional if EDU_ADMIN_API_KEY in env for HTTP mode;
 * default: direct lib (same as admin API body).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduParentReportData.php';
require_once $root . '/public/api/edu/lib/eduParentReportPdf.php';

$opts = getopt('', ['student-id:', 'pdf:', 'json:', 'list', 'skip-narrative', 'help']);

if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php tools/edu_parent_report_generate.php --student-id=UUID [--pdf=path.pdf] [--json=path.json]\n");
    exit(0);
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

if (isset($opts['list'])) {
    require_once $root . '/public/api/edu/lib/eduQuest.php';
    require_once $root . '/public/api/edu/lib/eduTier.php';
    require_once $root . '/public/api/edu/lib/eduCoachLevel.php';
    $students = $sb->select('edu_students', 'status=eq.active&order=created_at.desc', 30) ?? [];
    foreach ($students as $s) {
        $id = (string) ($s['id'] ?? '');
        echo ($s['display_name'] ?? '?') . "\t" . $id . "\n";
    }
    exit(0);
}

$studentId = trim((string) ($opts['student-id'] ?? ''));
if ($studentId === '') {
    fwrite(STDERR, "--student-id required (or --list)\n");
    exit(1);
}

$skipNarrative = isset($opts['skip-narrative']);

try {
    $payload = eduParentReportBuildPayload($sb, $studentId, !$skipNarrative);
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if (isset($opts['json'])) {
    file_put_contents($opts['json'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "JSON → {$opts['json']}\n";
}

$pdfPath = $opts['pdf'] ?? '';
if ($pdfPath === '') {
    $outDir = $root . '/docs/exports/gist-edu/parent-reports';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }
    $pdfPath = $outDir . '/' . eduParentReportPdfFilename($payload);
}

try {
    $pdf = eduParentReportRenderPdf($payload);
    file_put_contents($pdfPath, $pdf);
    echo "PDF → {$pdfPath}\n";
    echo "Narrative: " . (($payload['coach_letter']['generated'] ?? false) ? 'LLM' : 'fallback') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'PDF failed: ' . $e->getMessage() . "\n");
    exit(1);
}
