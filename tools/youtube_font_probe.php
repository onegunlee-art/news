<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/youtube/bootstrap.php';

youtubeLoadEnv($root);

if (!function_exists('imagettftext')) {
    fwrite(STDERR, "ERROR: PHP GD/FreeType (imagettftext) not available\n");
    exit(1);
}

$config = youtubeGetConfig($root);

echo "=== YouTube Font Probe ===\n\n";
echo 'Bold: ' . ($config['fonts']['title'] ?: '(not found)') . "\n";
echo 'Regular: ' . ($config['fonts']['body'] ?: '(not found)') . "\n\n";

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

    $sample = $weight === 'bold' ? '9' : '명 사망';
    $size = $weight === 'bold' ? 280 : 72;
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
        echo strtoupper($key) . ": TOO SMALL ({$bytes} bytes)\n";
        $failed = true;
        continue;
    }

    echo strtoupper($key) . ": OK -> {$out} ({$bytes} bytes)\n";
}

echo "\n";
if ($failed) {
    echo "FAIL: font probe did not pass\n";
    exit(1);
}

echo "Done.\n";
