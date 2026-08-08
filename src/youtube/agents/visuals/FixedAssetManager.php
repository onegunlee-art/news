<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Manages fixed opening/ending brand screens.
 * Uses LayoutHelper for safe zone and proper centering.
 */
final class FixedAssetManager
{
    private int $width;
    private int $height;
    private array $style;
    private string $fixedPath;
    private string $fontBold;
    private string $fontRegular;
    private LayoutHelper $layout;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];
        $this->fixedPath = $config['fixed_assets_path'] ?? 'storage/youtube/_fixed';

        $this->fontBold = $config['fonts']['title'] ?? '';
        $this->fontRegular = $config['fonts']['body'] ?? '';

        if ($this->fontBold === '' || !is_file($this->fontBold)) {
            throw new \RuntimeException("FixedAssetManager: Bold font not found. Path: {$this->fontBold}");
        }
        if ($this->fontRegular === '' || !is_file($this->fontRegular)) {
            throw new \RuntimeException("FixedAssetManager: Regular font not found. Path: {$this->fontRegular}");
        }

        $this->layout = new LayoutHelper($this->width, $this->height);
        $this->ensureDirectory($this->fixedPath);
    }

    public function getOpeningScreen(): string
    {
        $path = $this->fixedPath . '/opening.png';

        if (!file_exists($path)) {
            $this->createOpeningScreen($path);
        }

        return $path;
    }

    public function getEndingScreen(): string
    {
        $path = $this->fixedPath . '/ending.png';

        if (!file_exists($path)) {
            $this->createEndingScreen($path);
        }

        return $path;
    }

    private function createOpeningScreen(string $path): void
    {
        $image = $this->createBaseImage();

        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $white = imagecolorallocate($image, ...$this->style['text_rgb'] ?? [255, 255, 255]);

        $mainText = 'THE WORLD';
        $subText = 'CHANGED TODAY';
        $logoText = 'the gist.';

        $mainSize = $this->layout->fitFontToWidth($mainText, 100, 60, $this->layout->getSafeWidth(), $this->fontBold);
        $subSize = $this->layout->fitFontToWidth($subText, 100, 60, $this->layout->getSafeWidth(), $this->fontBold);
        $logoSize = 52;

        $mainMetrics = $this->layout->measureText($mainText, $mainSize, $this->fontBold);
        $subMetrics = $this->layout->measureText($subText, $subSize, $this->fontBold);
        $logoMetrics = $this->layout->measureText($logoText, $logoSize, $this->fontRegular);

        $textGap = 40;
        $logoGap = 120;

        $heights = [$mainMetrics['height'], $subMetrics['height'], $logoMetrics['height']];
        $totalHeight = array_sum($heights) + $textGap + $logoGap;

        $centerY = (int) ($this->height / 2);
        $startY = $centerY - (int) ($totalHeight / 2) + $mainMetrics['height'];

        $mainX = $this->layout->centerX($mainMetrics['width']);
        $mainY = $this->layout->clampY($startY, $mainMetrics['height']);
        imagettftext($image, $mainSize, 0, $mainX, $mainY, $white, $this->fontBold, $mainText);

        $subX = $this->layout->centerX($subMetrics['width']);
        $subY = $this->layout->clampY($mainY + $textGap + $subMetrics['height'], $subMetrics['height']);
        imagettftext($image, $subSize, 0, $subX, $subY, $gold, $this->fontBold, $subText);

        $logoX = $this->layout->centerX($logoMetrics['width']);
        $logoY = $this->layout->clampY($subY + $logoGap + $logoMetrics['height'], $logoMetrics['height']);
        imagettftext($image, $logoSize, 0, $logoX, $logoY, $gold, $this->fontRegular, $logoText);

        $this->addGoldAccentLines($image);

        imagepng($image, $path);
        imagedestroy($image);
    }

    private function createEndingScreen(string $path): void
    {
        $image = $this->createBaseImage();

        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $white = imagecolorallocate($image, ...$this->style['text_rgb'] ?? [255, 255, 255]);
        $gray = imagecolorallocate($image, ...$this->style['secondary_text_rgb'] ?? [136, 136, 136]);

        $logoText = 'the gist.';
        $tagline1 = 'Essential truth.';
        $tagline2 = 'A clear view of the world.';

        $logoSize = $this->layout->fitFontToWidth($logoText, 90, 60, $this->layout->getSafeWidth(), $this->fontBold);
        $tagSize = $this->layout->fitFontToWidth($tagline2, 44, 32, $this->layout->getSafeWidth(), $this->fontRegular);

        $logoMetrics = $this->layout->measureText($logoText, $logoSize, $this->fontBold);
        $tag1Metrics = $this->layout->measureText($tagline1, $tagSize, $this->fontRegular);
        $tag2Metrics = $this->layout->measureText($tagline2, $tagSize, $this->fontRegular);

        $logoTagGap = 60;
        $tagGap = 20;

        $heights = [$logoMetrics['height'], $tag1Metrics['height'], $tag2Metrics['height']];
        $totalHeight = array_sum($heights) + $logoTagGap + $tagGap;

        $centerY = (int) ($this->height / 2);
        $startY = $centerY - (int) ($totalHeight / 2) + $logoMetrics['height'];

        $logoX = $this->layout->centerX($logoMetrics['width']);
        $logoY = $this->layout->clampY($startY, $logoMetrics['height']);
        imagettftext($image, $logoSize, 0, $logoX, $logoY, $gold, $this->fontBold, $logoText);

        $tag1X = $this->layout->centerX($tag1Metrics['width']);
        $tag1Y = $this->layout->clampY($logoY + $logoTagGap + $tag1Metrics['height'], $tag1Metrics['height']);
        imagettftext($image, $tagSize, 0, $tag1X, $tag1Y, $white, $this->fontRegular, $tagline1);

        $tag2X = $this->layout->centerX($tag2Metrics['width']);
        $tag2Y = $this->layout->clampY($tag1Y + $tagGap + $tag2Metrics['height'], $tag2Metrics['height']);
        imagettftext($image, $tagSize, 0, $tag2X, $tag2Y, $gray, $this->fontRegular, $tagline2);

        $this->addGoldAccentLines($image);

        imagepng($image, $path);
        imagedestroy($image);
    }

    private function createBaseImage(): \GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $bgColor = imagecolorallocate($image, ...$this->style['bg_rgb'] ?? [10, 10, 10]);
        imagefill($image, 0, 0, $bgColor);

        return $image;
    }

    private function addGoldAccentLines(\GdImage $image): void
    {
        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);

        $lineY1 = $this->layout->getSafeTop() + 60;
        $lineY2 = $this->layout->getSafeBottom() - 60;

        imagesetthickness($image, 3);
        imageline($image, $this->layout->getSafeLeft(), $lineY1, $this->width - $this->layout->getSafeLeft(), $lineY1, $gold);
        imageline($image, $this->layout->getSafeLeft(), $lineY2, $this->width - $this->layout->getSafeLeft(), $lineY2, $gold);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
