<?php
declare(strict_types=1);

/**
 * Discovery 자동화 전체 흐름 테스트 (cron 대기 없이)
 * - 생성 → 발행 → 이메일 전 과정을 수동으로 트리거
 * 
 * Usage: php tools/discovery_cron_test.php [YYYY-MM-DD]
 */

require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
$date = $argv[1] ?? discoveryTodayKst();

echo "=== Discovery Cron Full Test date={$date} ===\n\n";

// Step 1: Generate
echo ">>> Step 1: Generate\n";
$generateCmd = "php " . escapeshellarg(__DIR__ . '/../cron/discovery_generate.php') . " " . escapeshellarg($date);
echo "CMD: {$generateCmd}\n";
passthru($generateCmd, $genExitCode);
echo "\nGenerate exit code: {$genExitCode}\n\n";

if ($genExitCode !== 0) {
    echo "❌ Generate failed, but continuing to test publish/fallback...\n\n";
}

// Step 2: Publish + Email
echo ">>> Step 2: Publish + Email\n";
$publishCmd = "php " . escapeshellarg(__DIR__ . '/../cron/discovery_publish.php') . " " . escapeshellarg($date);
echo "CMD: {$publishCmd}\n";
passthru($publishCmd, $pubExitCode);
echo "\nPublish exit code: {$pubExitCode}\n\n";

// Summary
echo "=== Test Summary ===\n";
echo "Generate: " . ($genExitCode === 0 ? "✅ OK" : "❌ FAILED ({$genExitCode})") . "\n";
echo "Publish: " . ($pubExitCode === 0 ? "✅ OK" : "❌ FAILED ({$pubExitCode})") . "\n";

// Read results
$genResultPath = $root . 'storage/discovery_last_generate.json';
$pubResultPath = $root . 'storage/discovery_last_publish.json';

if (is_file($genResultPath)) {
    echo "\n--- Last Generate Result ---\n";
    echo file_get_contents($genResultPath) . "\n";
}

if (is_file($pubResultPath)) {
    echo "\n--- Last Publish Result ---\n";
    echo file_get_contents($pubResultPath) . "\n";
}

echo "\n📧 이메일이 도착했는지 확인하세요!\n";
