<?php
declare(strict_types=1);

/**
 * Test YouTube visual generators with layout fixes.
 * Generates all scene types to verify no text overflow or overlap.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/youtube/bootstrap.php';

$config = youtubeGetConfig($projectRoot);
$outputDir = $projectRoot . '/storage/youtube/_preview/layout_test';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "=== YouTube Layout Test ===\n\n";
echo "Output: {$outputDir}\n\n";

// Test 1: Map with long location
echo "1. MapGenerator (Kyiv, Ukraine)...\n";
$mapGen = new Youtube\Agents\Visuals\MapGenerator($config);
$path = $mapGen->generate('Kyiv, Ukraine', $outputDir);
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 2: Map fallback with very long text
echo "2. MapGenerator fallback (long text)...\n";
$path = $mapGen->generate('Some Very Long Unknown Location Name That Should Wrap', $outputDir . '/fallback');
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 3: Chart with 2 numbers
echo "3. ChartGenerator (2 numbers)...\n";
$chartGen = new Youtube\Agents\Visuals\ChartGenerator($config);
$chartData = [
    'numbers' => [
        ['value' => '9', 'label' => '명 사망'],
        ['value' => '30+', 'label' => '명 부상 (일부는 심각한 상태)'],
    ],
];
$path = $chartGen->generate($chartData, $outputDir);
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 4: Chart with 3 numbers
echo "4. ChartGenerator (3 numbers)...\n";
$chartData3 = [
    'numbers' => [
        ['value' => '1,500', 'label' => '건물 손상'],
        ['value' => '45', 'label' => '사망자'],
        ['value' => '200+', 'label' => '부상자'],
    ],
];
$path = $chartGen->generate($chartData3, $outputDir . '/chart3');
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 5: Text overlay with 3 points (normal)
echo "5. TextOverlayGenerator (3 points)...\n";
$textGen = new Youtube\Agents\Visuals\TextOverlayGenerator($config);
$points = [
    '첫 번째 중요한 포인트입니다. 러시아의 공격이 계속됩니다.',
    '두 번째 포인트는 국제 사회의 반응에 관한 것입니다.',
    '세 번째 포인트는 앞으로의 전망입니다.',
];
$path = $textGen->generate(3, $points, '왜 중요한가', $outputDir);
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 6: Text overlay with long points
echo "6. TextOverlayGenerator (long points)...\n";
$longPoints = [
    '이것은 매우 긴 첫 번째 포인트입니다. 러시아의 지속적인 공격으로 인해 민간인 피해가 급증하고 있으며, 국제 사회의 우려가 커지고 있습니다.',
    '두 번째 포인트는 더욱 길어질 수 있습니다. 유럽연합과 미국은 추가 제재를 검토 중이며, 이는 글로벌 경제에도 영향을 미칠 것으로 예상됩니다.',
    '마지막 세 번째 포인트입니다. 전문가들은 이 상황이 장기화될 수 있다고 경고하고 있으며, 외교적 해결책을 촉구하고 있습니다.',
];
$path = $textGen->generate(4, $longPoints, '앞으로의 전망', $outputDir . '/text_long');
echo "   ✓ {$path} (" . filesize($path) . " bytes)\n";

// Test 7: Fixed assets
echo "7. FixedAssetManager...\n";
// Force regeneration by deleting existing files
$fixedDir = $projectRoot . '/storage/youtube/_fixed';
@unlink($fixedDir . '/opening.png');
@unlink($fixedDir . '/ending.png');
$fixedGen = new Youtube\Agents\Visuals\FixedAssetManager($config);
$opening = $fixedGen->getOpeningScreen();
$ending = $fixedGen->getEndingScreen();
echo "   ✓ Opening: {$opening} (" . filesize($opening) . " bytes)\n";
echo "   ✓ Ending: {$ending} (" . filesize($ending) . " bytes)\n";

echo "\n=== All tests completed ===\n";
echo "Check images in: {$outputDir}\n";
echo "And fixed assets in: {$fixedDir}\n";
