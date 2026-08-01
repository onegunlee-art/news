<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates number/statistic graphics using PHP GD.
 * Creates 1080x1920 vertical images with large gold numbers.
 */
final class ChartGenerator
{
    private int $width;
    private int $height;
    private array $style;
    private string $fontBold;
    private string $fontRegular;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];
        
        $projectRoot = dirname(__DIR__, 4);
        $this->fontBold = $config['fonts']['title'] ?? $projectRoot . '/public/fonts/noto/noto_sans_kr_bold_b1d8ccaef03cabe0c50be6a406ebee03.ttf';
        $this->fontRegular = $config['fonts']['body'] ?? $projectRoot . '/public/fonts/noto/noto_sans_kr_normal_f720aac0493f6f2cdc1ac7555480ae45.ttf';
    }

    /**
     * Generate a chart/numbers image.
     * @param mixed $data Numbers data from scene text_overlay
     * @return string Path to generated image
     */
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
        $sectionHeight = $this->height / ($count + 1);
        
        $numberSize = $count <= 2 ? 200 : 150;
        $labelSize = $count <= 2 ? 48 : 36;

        foreach ($numbers as $i => $num) {
            $y = (int) (($i + 1) * $sectionHeight);
            $this->renderSingleNumber($image, $num, $y, $numberSize, $labelSize, $gold, $white);
        }

        $title = '핵심 수치';
        $titleSize = 36;
        if (file_exists($this->fontBold)) {
            $bbox = imagettfbbox($titleSize, 0, $this->fontBold, $title);
            $titleWidth = abs($bbox[2] - $bbox[0]);
            imagettftext($image, $titleSize, 0, (int) (($this->width - $titleWidth) / 2), 120, $gray, $this->fontBold, $title);
        }
    }

    private function renderSingleNumber(\GdImage $image, array $num, int $y, int $numberSize, int $labelSize, int $gold, int $white): void
    {
        $value = (string) ($num['value'] ?? '');
        $label = (string) ($num['label'] ?? '');

        if ($value !== '' && file_exists($this->fontBold)) {
            $bbox = imagettfbbox($numberSize, 0, $this->fontBold, $value);
            $valueWidth = abs($bbox[2] - $bbox[0]);
            $valueX = ($this->width - $valueWidth) / 2;
            imagettftext($image, $numberSize, 0, (int) $valueX, $y, $gold, $this->fontBold, $value);
            
            $labelY = $y + 80;
        } else {
            $labelY = $y;
        }

        if ($label !== '' && file_exists($this->fontRegular)) {
            $bbox = imagettfbbox($labelSize, 0, $this->fontRegular, $label);
            $labelWidth = abs($bbox[2] - $bbox[0]);
            $labelX = ($this->width - $labelWidth) / 2;
            imagettftext($image, $labelSize, 0, (int) $labelX, $labelY, $white, $this->fontRegular, $label);
        } elseif ($label !== '') {
            $labelX = ($this->width - strlen($label) * 10) / 2;
            imagestring($image, 5, (int) $labelX, $labelY - 10, $label, $white);
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
