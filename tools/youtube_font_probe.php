<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/youtube/bootstrap.php';

youtubeLoadEnv($root);

echo "=== YouTube Font Probe ===\n\n";

// 1. PHP GD/FreeType 확인
if (!function_exists('imagettftext')) {
    fwrite(STDERR, "ERROR: PHP GD/FreeType (imagettftext) not available\n");
    exit(1);
}
echo "GD/FreeType: OK\n\n";

// 2. 시스템 폰트 스캔
echo "--- System Fonts (Korean) ---\n";
$systemPaths = [
    '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
    '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
    '/usr/share/fonts/noto-cjk/NotoSansCJK-Bold.ttc',
    '/usr/share/fonts/opentype/noto/NotoSansCJKkr-Bold.otf',
    '/usr/share/fonts/truetype/noto/NotoSansKR-Bold.ttf',
];
$systemFound = false;
foreach ($systemPaths as $p) {
    if (is_file($p)) {
        echo "  [OK] {$p}\n";
        $systemFound = true;
    }
}
if (!$systemFound) {
    echo "  (none found - install fonts-noto-cjk)\n";
}
echo "\n";

// 3. 프로젝트 폰트 스캔
echo "--- Project Fonts ---\n";
$fontDir = $root . '/public/fonts/noto';
$projectFonts = glob($fontDir . '/*.{ttf,otf}', GLOB_BRACE) ?: [];
if (empty($projectFonts)) {
    echo "  (none in {$fontDir})\n";
} else {
    foreach ($projectFonts as $f) {
        echo '  ' . basename($f) . "\n";
    }
}
echo "\n";

// 4. youtubeGetConfig 해결 결과
$config = youtubeGetConfig($root);
echo "--- Resolved Fonts ---\n";
echo 'Bold:    ' . ($config['fonts']['title'] ?: '(not found)') . "\n";
echo 'Regular: ' . ($config['fonts']['body'] ?: '(not found)') . "\n\n";

// 5. 렌더링 테스트
echo "--- Render Test ---\n";
$failed = false;

foreach (['title' => 'bold', 'body' => 'regular'] as $key => $weight) {
    $path = $config['fonts'][$key] ?? '';
    if ($path === '' || !is_file($path)) {
        echo strtoupper($key) . ": MISSING\n";
        $failed = true;
        continue;
    }

    $image = imagecreatetruecolor(1080, 400);
    $bg = imagecolorallocate($image, 10, 10, 10);
    $gold = imagecolorallocate($image, 212, 175, 55);
    imagefill($image, 0, 0, $bg);

    $sample = $weight === 'bold' ? '9명 사망' : '핵심 수치 테스트';
    $size = $weight === 'bold' ? 200 : 72;
    $result = @imagettftext($image, $size, 0, 80, 280, $gold, $path, $sample);

    $out = $root . '/storage/youtube/_fixed/probe_' . $weight . '.png';
    if (!is_dir(dirname($out))) {
        mkdir(dirname($out), 0755, true);
    }
    imagepng($image, $out);
    imagedestroy($image);

    $bytes = filesize($out) ?: 0;
    if ($result === false) {
        echo strtoupper($key) . ": RENDER FAILED ({$path})\n";
        $failed = true;
        continue;
    }
    if ($bytes < 5000) {
        echo strtoupper($key) . ": TOO SMALL ({$bytes} bytes) - font may not be rendering\n";
        $failed = true;
        continue;
    }

    echo strtoupper($key) . ": OK ({$bytes} bytes) -> {$out}\n";
}

echo "\n";
if ($failed) {
    echo "FAIL: font probe did not pass\n";
    echo "\nFix: sudo apt install fonts-noto-cjk\n";
    exit(1);
}

echo "SUCCESS: All fonts rendering correctly.\n";
