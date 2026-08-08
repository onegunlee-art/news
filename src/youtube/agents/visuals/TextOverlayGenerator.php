<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates text overlay images with bullet points.
 * Uses LayoutHelper for safe zone enforcement and overlap prevention.
 */
final class TextOverlayGenerator
{
    private int $width;
    private int $height;
    private array $style;
    private string $fontBold;
    private string $fontRegular;
    private LayoutHelper $layout;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];

        $this->fontBold = $config['fonts']['title'] ?? '';
        $this->fontRegular = $config['fonts']['body'] ?? '';

        if ($this->fontBold === '' || !is_file($this->fontBold)) {
            throw new \RuntimeException("TextOverlayGenerator: Bold font not found. Path: {$this->fontBold}");
        }
        if ($this->fontRegular === '' || !is_file($this->fontRegular)) {
            throw new \RuntimeException("TextOverlayGenerator: Regular font not found. Path: {$this->fontRegular}");
        }

        $this->layout = new LayoutHelper($this->width, $this->height);
    }

    public function generate(int $sceneNum, mixed $textOverlay, string $title, string $projectPath): string
    {
        $image = $this->createBaseImage();

        $points = $this->parseTextOverlay($textOverlay);
        $this->renderContent($image, $title, $points);

        $outputPath = $projectPath . "/scene_{$sceneNum}_text.png";
        $this->ensureDirectory(dirname($outputPath));

        imagepng($image, $outputPath);
        imagedestroy($image);

        return $outputPath;
    }

    private function createBaseImage(): \GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $bgColor = imagecolorallocate($image, ...$this->style['bg_rgb'] ?? [10, 10, 10]);
        imagefill($image, 0, 0, $bgColor);

        return $image;
    }

    private function parseTextOverlay(mixed $data): array
    {
        if (is_array($data)) {
            return array_values(array_filter($data, 'is_string'));
        }

        if (is_string($data)) {
            return [$data];
        }

        return [];
    }

    /**
     * Render title and points with proper vertical stacking.
     */
    private function renderContent(\GdImage $image, string $title, array $points): void
    {
        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $white = imagecolorallocate($image, ...$this->style['text_rgb'] ?? [255, 255, 255]);
        $black = imagecolorallocate($image, 10, 10, 10);

        $titleFontSize = 72;
        $pointFontSize = 48;
        $numberFontSize = 52;
        $circleSize = 64;
        $lineHeight = (int) ($pointFontSize * 1.6);
        $pointGap = 60;
        $titleGap = 80;
        $textLeftOffset = 100;
        $maxTextWidth = $this->layout->getSafeWidth() - $textLeftOffset - 20;

        $titleFontSize = $this->layout->fitFontToWidth(
            $title,
            $titleFontSize,
            48,
            $this->layout->getSafeWidth(),
            $this->fontBold
        );
        $titleMetrics = $this->layout->measureText($title, $titleFontSize, $this->fontBold);

        $pointBlocks = [];
        foreach ($points as $point) {
            $lines = $this->layout->wrapText($point, $pointFontSize, $this->fontRegular, $maxTextWidth);
            $blockHeight = count($lines) * $lineHeight;
            $pointBlocks[] = [
                'lines' => $lines,
                'height' => max($blockHeight, $circleSize + 10),
            ];
        }

        $heights = [$titleMetrics['height']];
        foreach ($pointBlocks as $block) {
            $heights[] = $block['height'];
        }

        $gaps = array_fill(0, count($heights) - 1, $pointGap);
        if (count($gaps) > 0) {
            $gaps[0] = $titleGap;
        }

        $totalHeight = array_sum($heights) + array_sum($gaps);
        $availableHeight = $this->layout->getSafeHeight();
        $startY = $this->layout->getSafeTop() + max(60, (int) (($availableHeight - $totalHeight) / 2));

        $currentY = $startY + $titleMetrics['height'];
        $titleX = $this->layout->centerX($titleMetrics['width']);
        imagettftext($image, $titleFontSize, 0, $titleX, $currentY, $gold, $this->fontBold, $title);

        $currentY += $titleGap;

        $circleX = $this->layout->getSafeLeft() + 40;

        foreach ($pointBlocks as $i => $block) {
            $circleY = $currentY + (int) ($circleSize / 2);

            imagefilledellipse($image, $circleX, $circleY, $circleSize, $circleSize, $gold);

            $number = (string) ($i + 1);
            $numMetrics = $this->layout->measureText($number, (int) ($numberFontSize * 0.55), $this->fontBold);
            $numX = $circleX - (int) ($numMetrics['width'] / 2);
            $numY = $circleY + (int) ($numMetrics['height'] / 2) - 2;
            imagettftext($image, (int) ($numberFontSize * 0.55), 0, $numX, $numY, $black, $this->fontBold, $number);

            $textX = $this->layout->getSafeLeft() + $textLeftOffset;
            $textY = $currentY + $pointFontSize;

            foreach ($block['lines'] as $line) {
                $textY = $this->layout->clampY($textY, $pointFontSize);
                imagettftext($image, $pointFontSize, 0, $textX, $textY, $white, $this->fontRegular, $line);
                $textY += $lineHeight;
            }

            $currentY += $block['height'] + $pointGap;
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
