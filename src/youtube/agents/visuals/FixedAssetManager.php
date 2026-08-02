<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Manages fixed opening/ending brand screens.
 * Creates once and reuses for all videos.
 */
final class FixedAssetManager
{
    private int $width;
    private int $height;
    private array $style;
    private string $fixedPath;
    private string $fontBold;
    private string $fontRegular;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];
        $this->fixedPath = $config['fixed_assets_path'] ?? 'storage/youtube/_fixed';
        
        $projectRoot = dirname(__DIR__, 4);
        $this->fontBold = $config['fonts']['title'] ?? $projectRoot . '/public/fonts/noto/noto_sans_kr_bold_b1d8ccaef03cabe0c50be6a406ebee03.ttf';
        $this->fontRegular = $config['fonts']['body'] ?? $projectRoot . '/public/fonts/noto/noto_sans_kr_normal_f720aac0493f6f2cdc1ac7555480ae45.ttf';
        
        $this->ensureDirectory($this->fixedPath);
    }

    /**
     * Get opening screen image path, creating if needed.
     */
    public function getOpeningScreen(): string
    {
        $path = $this->fixedPath . '/opening.png';
        
        if (!file_exists($path)) {
            $this->createOpeningScreen($path);
        }
        
        return $path;
    }

    /**
     * Get ending screen image path, creating if needed.
     */
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
        
        if (file_exists($this->fontBold)) {
            $mainSize = 110;
            $bbox = imagettfbbox($mainSize, 0, $this->fontBold, $mainText);
            $mainWidth = abs($bbox[2] - $bbox[0]);
            $mainX = ($this->width - $mainWidth) / 2;
            imagettftext($image, $mainSize, 0, (int) $mainX, (int) ($this->height / 2 - 80), $white, $this->fontBold, $mainText);
            
            $bbox = imagettfbbox($mainSize, 0, $this->fontBold, $subText);
            $subWidth = abs($bbox[2] - $bbox[0]);
            $subX = ($this->width - $subWidth) / 2;
            imagettftext($image, $mainSize, 0, (int) $subX, (int) ($this->height / 2 + 100), $gold, $this->fontBold, $subText);
            
            $logoText = 'the gist.';
            $logoSize = 56;
            $bbox = imagettfbbox($logoSize, 0, $this->fontRegular, $logoText);
            $logoWidth = abs($bbox[2] - $bbox[0]);
            $logoX = ($this->width - $logoWidth) / 2;
            imagettftext($image, $logoSize, 0, (int) $logoX, $this->height - 250, $gold, $this->fontRegular, $logoText);
        } else {
            $this->renderTextFallback($image, $mainText, $this->height / 2 - 50, $white);
            $this->renderTextFallback($image, $subText, $this->height / 2 + 50, $gold);
        }

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
        
        if (file_exists($this->fontBold)) {
            $logoText = 'the gist.';
            $logoSize = 100;
            $bbox = imagettfbbox($logoSize, 0, $this->fontBold, $logoText);
            $logoWidth = abs($bbox[2] - $bbox[0]);
            $logoX = ($this->width - $logoWidth) / 2;
            imagettftext($image, $logoSize, 0, (int) $logoX, (int) ($this->height / 2 - 100), $gold, $this->fontBold, $logoText);
            
            $tagline = 'Essential truth.';
            $tagSize = 48;
            $bbox = imagettfbbox($tagSize, 0, $this->fontRegular, $tagline);
            $tagWidth = abs($bbox[2] - $bbox[0]);
            $tagX = ($this->width - $tagWidth) / 2;
            imagettftext($image, $tagSize, 0, (int) $tagX, (int) ($this->height / 2 + 60), $white, $this->fontRegular, $tagline);
            
            $tagline2 = 'A clear view of the world.';
            $bbox = imagettfbbox($tagSize, 0, $this->fontRegular, $tagline2);
            $tag2Width = abs($bbox[2] - $bbox[0]);
            $tag2X = ($this->width - $tag2Width) / 2;
            imagettftext($image, $tagSize, 0, (int) $tag2X, (int) ($this->height / 2 + 130), $gray, $this->fontRegular, $tagline2);
        } else {
            $this->renderTextFallback($image, 'the gist.', $this->height / 2 - 50, $gold);
            $this->renderTextFallback($image, 'Essential truth.', $this->height / 2 + 30, $white);
        }

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
        
        imagesetthickness($image, 3);
        imageline($image, 100, 300, $this->width - 100, 300, $gold);
        imageline($image, 100, $this->height - 300, $this->width - 100, $this->height - 300, $gold);
    }

    private function renderTextFallback(\GdImage $image, string $text, int $y, int $color): void
    {
        $x = ($this->width - strlen($text) * 12) / 2;
        imagestring($image, 5, (int) $x, $y, $text, $color);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
