<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates number/statistic graphics using PHP GD.
 * Uses LayoutHelper for safe zone and vertical stacking.
 */
final class ChartGenerator
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
            throw new \RuntimeException("ChartGenerator: Bold font not found. Path: {$this->fontBold}");
        }
        if ($this->fontRegular === '' || !is_file($this->fontRegular)) {
            throw new \RuntimeException("ChartGenerator: Regular font not found. Path: {$this->fontRegular}");
        }

        $this->layout = new LayoutHelper($this->width, $this->height);
    }

    public function generate(mixed $data, string $projectPath): string
    {
        $image = $this->createBaseImage();

        $numbers = $this->parseNumberData($data);
        $this->renderNumbers($image, $numbers);

        $outputPath = $projectPath . '/scene_5_chart.png';
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

    private function parseNumberData(mixed $data): array
    {
        if (is_array($data) && isset($data['numbers'])) {
            return $data['numbers'];
        }

        if (is_array($data)) {
            $numbers = [];
            foreach ($data as $item) {
                if (is_array($item) && isset($item['value'])) {
                    $numbers[] = $item;
                } elseif (is_string($item)) {
                    if (preg_match('/(\d+)\s*(.+)/', $item, $m)) {
                        $numbers[] = ['value' => $m[1], 'label' => trim($m[2])];
                    } else {
                        $numbers[] = ['value' => '', 'label' => $item];
                    }
                }
            }
            return $numbers;
        }

        if (is_string($data)) {
            preg_match_all('/(\d+)\s*([^0-9,]+)/', $data, $matches, PREG_SET_ORDER);
            $numbers = [];
            foreach ($matches as $m) {
                $numbers[] = ['value' => $m[1], 'label' => trim($m[2])];
            }
            return $numbers ?: [['value' => '', 'label' => $data]];
        }

        return [];
    }

    private function renderNumbers(\GdImage $image, array $numbers): void
    {
        if (empty($numbers)) {
            return;
        }

        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $white = imagecolorallocate($image, ...$this->style['text_rgb'] ?? [255, 255, 255]);
        $gray = imagecolorallocate($image, ...$this->style['secondary_text_rgb'] ?? [136, 136, 136]);

        $count = count($numbers);
        $numberSize = $count <= 2 ? 240 : 180;
        $labelSize = $count <= 2 ? 64 : 52;
        $labelGap = $count <= 2 ? 40 : 32;
        $blockGap = $count <= 2 ? 140 : 100;

        $title = '핵심 수치';
        $titleSize = 56;
        $titleMetrics = $this->layout->measureText($title, $titleSize, $this->fontBold);

        $blockHeights = [];
        foreach ($numbers as $num) {
            $value = (string) ($num['value'] ?? '');
            $label = (string) ($num['label'] ?? '');

            $valueHeight = $value !== '' ? $this->layout->measureText($value, $numberSize, $this->fontBold)['height'] : 0;

            $labelLines = $this->layout->wrapText($label, $labelSize, $this->fontRegular, $this->layout->getSafeWidth() - 40);
            $labelHeight = count($labelLines) * (int) ($labelSize * 1.4);

            $blockHeights[] = [
                'total' => $valueHeight + $labelGap + $labelHeight,
                'valueHeight' => $valueHeight,
                'labelLines' => $labelLines,
                'labelHeight' => $labelHeight,
            ];
        }

        $totalContentHeight = $titleMetrics['height'] + 60;
        foreach ($blockHeights as $i => $bh) {
            $totalContentHeight += $bh['total'];
            if ($i < count($blockHeights) - 1) {
                $totalContentHeight += $blockGap;
            }
        }

        $startY = $this->layout->getSafeTop() + max(40, (int) (($this->layout->getSafeHeight() - $totalContentHeight) / 2));

        $currentY = $startY + $titleMetrics['height'];
        $titleX = $this->layout->centerX($titleMetrics['width']);
        imagettftext($image, $titleSize, 0, $titleX, $currentY, $gray, $this->fontBold, $title);

        $currentY += 60;

        foreach ($numbers as $i => $num) {
            $value = (string) ($num['value'] ?? '');
            $bh = $blockHeights[$i];

            if ($value !== '') {
                $valueFitted = $this->layout->fitFontToWidth($value, $numberSize, 100, $this->layout->getSafeWidth() - 40, $this->fontBold);
                $valueMetrics = $this->layout->measureText($value, $valueFitted, $this->fontBold);
                $valueX = $this->layout->centerX($valueMetrics['width']);
                $valueY = $this->layout->clampY($currentY + $valueMetrics['height'], $valueMetrics['height']);
                imagettftext($image, $valueFitted, 0, $valueX, $valueY, $gold, $this->fontBold, $value);

                $currentY = $valueY + $labelGap;
            }

            $lineHeight = (int) ($labelSize * 1.4);
            foreach ($bh['labelLines'] as $line) {
                $lineMetrics = $this->layout->measureText($line, $labelSize, $this->fontRegular);
                $lineX = $this->layout->centerX($lineMetrics['width']);
                $lineY = $this->layout->clampY($currentY + $labelSize, $labelSize);
                imagettftext($image, $labelSize, 0, $lineX, $lineY, $white, $this->fontRegular, $line);
                $currentY = $lineY + ($lineHeight - $labelSize);
            }

            if ($i < count($numbers) - 1) {
                $currentY += $blockGap;
            }
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
