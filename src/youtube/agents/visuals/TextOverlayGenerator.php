<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates text overlay images with bullet points.
 * Creates 1080x1920 vertical images with dark background and gold/white text.
 */
final class TextOverlayGenerator
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
        $this->fontBold = $config['fonts']['title'] ?? $projectRoot . '/public/fonts/noto/NotoSansKR-Bold.otf';
        $this->fontRegular = $config['fonts']['body'] ?? $projectRoot . '/public/fonts/noto/NotoSansKR-Regular.otf';
    }

    /**
     * Generate a text overlay image.
     * @param int $sceneNum Scene number (3 or 4)
     * @param mixed $textOverlay Array of points or single text
     * @param string $title Scene title
     * @return string Path to generated image
     */
    public function generate(int $sceneNum, mixed $textOverlay, string $title, string $projectPath): string
    {
        $image = $this->createBaseImage();
        
        $points = $this->parseTextOverlay($textOverlay);
        $this->renderTitle($image, $title);
        $this->renderPoints($image, $points, $sceneNum);

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

    private function renderTitle(\GdImage $image, string $title): void
    {
        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $fontSize = 80;
        
        if (!file_exists($this->fontBold)) {
            imagestring($image, 5, 100, 200, $title, $gold);
            return;
        }

        $bbox = imagettfbbox($fontSize, 0, $this->fontBold, $title);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $x = ($this->width - $textWidth) / 2;
        
        imagettftext($image, $fontSize, 0, (int) $x, 300, $gold, $this->fontBold, $title);
    }

    private function renderPoints(\GdImage $image, array $points, int $sceneNum): void
    {
        if (empty($points)) {
            return;
        }

        $white = imagecolorallocate($image, ...$this->style['text_rgb'] ?? [255, 255, 255]);
        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        
        $startY = 500;
        $lineHeight = 280;
        $fontSize = 52;
        $numberSize = 96;
        $marginLeft = 100;

        foreach ($points as $i => $point) {
            $y = $startY + ($i * $lineHeight);
            $number = (string) ($i + 1);
            
            if (file_exists($this->fontBold)) {
                $circleX = $marginLeft + 30;
                $circleY = $y;
                imagefilledellipse($image, $circleX, $circleY - 20, 70, 70, $gold);
                
                $black = imagecolorallocate($image, 10, 10, 10);
                $bbox = imagettfbbox($numberSize * 0.6, 0, $this->fontBold, $number);
                $numWidth = abs($bbox[2] - $bbox[0]);
                imagettftext($image, (int)($numberSize * 0.6), 0, (int)($circleX - $numWidth / 2), $circleY + 5, $black, $this->fontBold, $number);
                
                $textX = $marginLeft + 100;
                $this->renderWrappedText($image, $point, $textX, $y, $fontSize, $white);
            } else {
                imagestring($image, 5, $marginLeft, $y - 10, "{$number}. {$point}", $white);
            }
        }
    }

    private function renderWrappedText(\GdImage $image, string $text, int $x, int $y, int $fontSize, int $color): void
    {
        $maxWidth = $this->width - $x - 80;
        $words = preg_split('/\s+/', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $bbox = imagettfbbox($fontSize, 0, $this->fontRegular, $testLine);
            $lineWidth = abs($bbox[2] - $bbox[0]);
            
            if ($lineWidth > $maxWidth && $currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        $lineHeight = $fontSize * 1.5;
        foreach ($lines as $i => $line) {
            imagettftext($image, $fontSize, 0, $x, (int) ($y + $i * $lineHeight), $color, $this->fontRegular, $line);
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
