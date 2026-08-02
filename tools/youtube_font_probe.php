<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/youtube/bootstrap.php';

youtubeLoadEnv($root);
$config = youtubeGetConfig($root);

echo "=== YouTube Font Probe ===\n\n";
echo 'Bold: ' . ($config['fonts']['title'] ?: '(not found)') . "\n";
echo 'Regular: ' . ($config['fonts']['body'] ?: '(not found)') . "\n\n";

foreach (['title' => 'bold', 'body' => 'regular'] as $key => $weight) {
    $path = $config['fonts'][$key] ?? '';
    if ($path === '' || !is_file($path)) {
        echo strtoupper($key) . ": MISSING\n";
        continue;
    }

    $image = imagecreatetruecolor(1080, 400);
    $bg = imagecolorallocate($image, 10, 10, 10);
    $gold = imagecolorallocate($image, 212, 175, 55);
    imagefill($image, 0, 0, $bg);

    $sample = $weight === 'bold' ? '9' : '명 사망';
    $size = $weight === 'bold' ? 280 : 72;
    $result = imagettftext($image, $size, 0, 80, 280, $gold, $path, $sample);

    $out = $root . '/storage/youtube/_fixed/probe_' . $weight . '.png';
    if (!is_dir(dirname($out))) {
        mkdir(dirname($out), 0755, true);
    }
    imagepng($image, $out);
    imagedestroy($image);

    echo strtoupper($key) . ": OK -> {$out} (" . filesize($out) . " bytes)\n";
}

echo "\nDone.\n";
