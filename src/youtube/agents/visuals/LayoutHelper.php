<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Shared layout utilities for YouTube visual generators.
 * Ensures text stays within safe zone and elements don't overlap.
 */
final class LayoutHelper
{
    public const SAFE_MARGIN_X = 80;
    public const SAFE_MARGIN_TOP = 140;
    public const SAFE_MARGIN_BOTTOM = 180;

    private int $width;
    private int $height;
    private int $safeLeft;
    private int $safeRight;
    private int $safeTop;
    private int $safeBottom;
    private int $safeWidth;
    private int $safeHeight;

    public function __construct(int $width = 1080, int $height = 1920)
    {
        $this->width = $width;
        $this->height = $height;
        $this->safeLeft = self::SAFE_MARGIN_X;
        $this->safeRight = $width - self::SAFE_MARGIN_X;
        $this->safeTop = self::SAFE_MARGIN_TOP;
        $this->safeBottom = $height - self::SAFE_MARGIN_BOTTOM;
        $this->safeWidth = $this->safeRight - $this->safeLeft;
        $this->safeHeight = $this->safeBottom - $this->safeTop;
    }

    public function getSafeWidth(): int
    {
        return $this->safeWidth;
    }

    public function getSafeHeight(): int
    {
        return $this->safeHeight;
    }

    public function getSafeTop(): int
    {
        return $this->safeTop;
    }

    public function getSafeBottom(): int
    {
        return $this->safeBottom;
    }

    public function getSafeLeft(): int
    {
        return $this->safeLeft;
    }

    /**
     * Measure text dimensions before rendering.
     * @return array{width: int, height: int, ascent: int, descent: int}
     */
    public function measureText(string $text, int $fontSize, string $fontPath): array
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $width = abs($bbox[2] - $bbox[0]);
        $height = abs($bbox[7] - $bbox[1]);
        $ascent = abs($bbox[7]);
        $descent = abs($bbox[1]);

        return [
            'width' => $width,
            'height' => $height,
            'ascent' => $ascent,
            'descent' => $descent,
        ];
    }

    /**
     * Calculate X position for horizontally centered text.
     */
    public function centerX(int $textWidth): int
    {
        return (int) (($this->width - $textWidth) / 2);
    }

    /**
     * Calculate X position for left-aligned text within safe zone.
     */
    public function alignLeft(int $offsetFromSafeLeft = 0): int
    {
        return $this->safeLeft + $offsetFromSafeLeft;
    }

    /**
     * Shrink font size until text fits within max width.
     */
    public function fitFontToWidth(string $text, int $maxFontSize, int $minFontSize, int $maxWidth, string $fontPath): int
    {
        for ($size = $maxFontSize; $size >= $minFontSize; $size -= 2) {
            $metrics = $this->measureText($text, $size, $fontPath);
            if ($metrics['width'] <= $maxWidth) {
                return $size;
            }
        }
        return $minFontSize;
    }

    /**
     * Wrap long text into multiple lines that fit within maxWidth.
     * @return list<string>
     */
    public function wrapText(string $text, int $fontSize, string $fontPath, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', $text);
        if ($words === false || count($words) === 0) {
            return [$text];
        }

        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $metrics = $this->measureText($testLine, $fontSize, $fontPath);

            if ($metrics['width'] > $maxWidth && $currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Calculate vertical positions for a stack of elements, centered in safe area.
     * @param list<int> $heights Heights of each element
     * @param int $gap Gap between elements
     * @return list<int> Y positions (baseline) for each element
     */
    public function verticalStack(array $heights, int $gap, int $topOffset = 0): array
    {
        $totalHeight = array_sum($heights) + (count($heights) - 1) * $gap;
        $availableHeight = $this->safeHeight - $topOffset;

        $startY = $this->safeTop + $topOffset + max(0, (int) (($availableHeight - $totalHeight) / 2));

        $positions = [];
        $currentY = $startY;

        foreach ($heights as $i => $h) {
            $positions[] = $currentY + $h;
            $currentY += $h + $gap;
        }

        return $positions;
    }

    /**
     * Clamp a Y position to stay within safe zone.
     */
    public function clampY(int $y, int $elementHeight): int
    {
        $minY = $this->safeTop + $elementHeight;
        $maxY = $this->safeBottom;

        return max($minY, min($maxY, $y));
    }

    /**
     * Check if an element would overflow the safe zone.
     */
    public function wouldOverflow(int $x, int $y, int $width, int $height): bool
    {
        return $x < $this->safeLeft
            || $x + $width > $this->safeRight
            || $y - $height < $this->safeTop
            || $y > $this->safeBottom;
    }

    /**
     * Render multiple wrapped lines of text, return total height used.
     */
    public function renderWrappedLines(
        \GdImage $image,
        array $lines,
        int $x,
        int $startY,
        int $fontSize,
        int $lineHeight,
        int $color,
        string $fontPath
    ): int {
        $currentY = $startY;
        foreach ($lines as $line) {
            imagettftext($image, $fontSize, 0, $x, $currentY, $color, $fontPath, $line);
            $currentY += $lineHeight;
        }
        return count($lines) * $lineHeight;
    }
}
