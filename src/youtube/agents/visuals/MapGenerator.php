<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates country-silhouette map images using Natural Earth GeoJSON.
 * Draws 1080x1920 vertical maps with location marker and label.
 */
final class MapGenerator
{
    private int $width;
    private int $height;
    private array $style;
    private string $fontBold;
    private CountryGeoData $geoData;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];

        $this->fontBold = $config['fonts']['title'] ?? '';
        if ($this->fontBold === '' || !is_file($this->fontBold)) {
            throw new \RuntimeException("MapGenerator: Bold font not found. Path: {$this->fontBold}");
        }

        $geoPath = $config['map']['geo_json_path'] ?? '';
        $this->geoData = new CountryGeoData($geoPath);
    }

    /**
     * Generate a map image for a location.
     * @return string Path to generated image
     */
    public function generate(string $location, string $projectPath): string
    {
        $outputPath = $projectPath . '/scene_2_map.png';
        $this->ensureDirectory(dirname($outputPath));

        $countryName = $this->geoData->extractCountryName($location);
        $coords = $this->geocode($location);
        $country = $countryName !== '' ? $this->geoData->findCountry($countryName) : null;

        if ($country === null || $coords === null) {
            $image = $this->renderTextFallback($location !== '' ? $location : ($countryName !== '' ? $countryName : 'Unknown'));
        } else {
            $image = $this->renderSilhouetteMap($location, $country, $coords);
        }

        imagepng($image, $outputPath);
        imagedestroy($image);

        return $outputPath;
    }

    /**
     * @param array{properties: array<string, mixed>, geometry: array<string, mixed>} $country
     * @param array{0: float, 1: float} $coords
     */
    private function renderSilhouetteMap(string $location, array $country, array $coords): \GdImage
    {
        $image = $this->createBaseImage();
        $rings = $this->geoData->getRelevantRings($country['geometry'], $coords[0], $coords[1]);
        if ($rings === []) {
            return $this->renderTextFallback($location);
        }

        $bbox = $this->geoData->computeBoundingBox($rings);
        $mapTop = 140;
        $mapBottom = 1500;
        $mapLeft = 40;
        $mapRight = $this->width - 40;
        $mapWidth = $mapRight - $mapLeft;
        $mapHeight = $mapBottom - $mapTop;

        $transform = $this->buildTransform($bbox, $mapLeft, $mapTop, $mapWidth, $mapHeight);

        $primaryRgb = $this->style['primary_rgb'] ?? [212, 175, 55];
        $fillColor = imagecolorallocatealpha($image, $primaryRgb[0], $primaryRgb[1], $primaryRgb[2], 90);
        $strokeColor = imagecolorallocate($image, $primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        imagesetthickness($image, 4);

        foreach ($rings as $ring) {
            $points = $this->projectRing($ring, $transform);
            if (count($points) < 6) {
                continue;
            }
            imagefilledpolygon($image, $points, $fillColor);
            imagepolygon($image, $points, $strokeColor);
        }

        imagesetthickness($image, 1);
        [$markerX, $markerY] = $this->projectPoint($coords[0], $coords[1], $transform);
        $this->drawMarker($image, $markerX, $markerY);
        $this->addLocationLabel($image, $location);

        return $image;
    }

    private function renderTextFallback(string $label): \GdImage
    {
        $image = $this->createBaseImage();
        $gold = imagecolorallocate($image, ...($this->style['primary_rgb'] ?? [212, 175, 55]));
        $gray = imagecolorallocate($image, ...($this->style['secondary_text_rgb'] ?? [136, 136, 136]));

        $fontSize = 88;
        while ($fontSize >= 40) {
            $bbox = imagettfbbox($fontSize, 0, $this->fontBold, $label);
            $textWidth = abs($bbox[2] - $bbox[0]);
            if ($textWidth <= $this->width - 120) {
                break;
            }
            $fontSize -= 4;
        }
        $textHeight = abs($bbox[7] - $bbox[1]);
        $x = (int) (($this->width - $textWidth) / 2);
        $y = (int) (($this->height + $textHeight) / 2);
        imagettftext($image, $fontSize, 0, $x, $y, $gold, $this->fontBold, $label);

        $subtitle = 'Location';
        $subSize = 48;
        $subBox = imagettfbbox($subSize, 0, $this->fontBold, $subtitle);
        $subWidth = abs($subBox[2] - $subBox[0]);
        imagettftext(
            $image,
            $subSize,
            0,
            (int) (($this->width - $subWidth) / 2),
            $y + 90,
            $gray,
            $this->fontBold,
            $subtitle
        );

        return $image;
    }

    private function createBaseImage(): \GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $bgColor = imagecolorallocate($image, ...($this->style['bg_rgb'] ?? [10, 10, 10]));
        imagefill($image, 0, 0, $bgColor);
        return $image;
    }

    /**
     * @param array{minLon: float, maxLon: float, minLat: float, maxLat: float} $bbox
     * @return array{offsetX: float, offsetY: float, scale: float, maxLat: float}
     */
    private function buildTransform(array $bbox, int $areaX, int $areaY, int $areaW, int $areaH): array
    {
        $geoW = max(0.0001, $bbox['maxLon'] - $bbox['minLon']);
        $geoH = max(0.0001, $bbox['maxLat'] - $bbox['minLat']);
        $scale = min($areaW / $geoW, $areaH / $geoH) * 0.92;
        $drawW = $geoW * $scale;
        $drawH = $geoH * $scale;

        return [
            'offsetX' => $areaX + ($areaW - $drawW) / 2,
            'offsetY' => $areaY + ($areaH - $drawH) / 2,
            'scale' => $scale,
            'minLon' => $bbox['minLon'],
            'maxLat' => $bbox['maxLat'],
        ];
    }

    /**
     * @param list<array{0: float, 1: float}> $ring
     * @param array{offsetX: float, offsetY: float, scale: float, minLon: float, maxLat: float} $transform
     * @return list<int>
     */
    private function projectRing(array $ring, array $transform): array
    {
        $points = [];
        foreach ($ring as $coord) {
            [$x, $y] = $this->projectPoint((float) $coord[1], (float) $coord[0], $transform);
            $points[] = $x;
            $points[] = $y;
        }
        return $points;
    }

    /**
     * @param array{offsetX: float, offsetY: float, scale: float, minLon: float, maxLat: float} $transform
     * @return array{0: int, 1: int}
     */
    private function projectPoint(float $lat, float $lon, array $transform): array
    {
        $x = (int) round($transform['offsetX'] + ($lon - $transform['minLon']) * $transform['scale']);
        $y = (int) round($transform['offsetY'] + ($transform['maxLat'] - $lat) * $transform['scale']);
        return [$x, $y];
    }

    private function drawMarker(\GdImage $image, int $x, int $y): void
    {
        $gold = imagecolorallocate($image, ...($this->style['primary_rgb'] ?? [212, 175, 55]));
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, ...($this->style['bg_rgb'] ?? [10, 10, 10]));

        imagefilledellipse($image, $x, $y, 40, 40, $dark);
        imagefilledellipse($image, $x, $y, 34, 34, $gold);
        imagefilledellipse($image, $x, $y, 16, 16, $white);
    }

    private function addLocationLabel(\GdImage $image, string $location): void
    {
        $gold = imagecolorallocate($image, ...($this->style['primary_rgb'] ?? [212, 175, 55]));
        $fontSize = 72;

        $bbox = imagettfbbox($fontSize, 0, $this->fontBold, $location);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $x = (int) (($this->width - $textWidth) / 2);
        $y = $this->height - 180;

        imagettftext($image, $fontSize, 0, $x, $y, $gold, $this->fontBold, $location);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function geocode(string $location): ?array
    {
        $knownLocations = [
            'kyiv, ukraine' => [50.4501, 30.5234],
            'kyiv' => [50.4501, 30.5234],
            'kiev, ukraine' => [50.4501, 30.5234],
            'moscow, russia' => [55.7558, 37.6173],
            'beijing, china' => [39.9042, 116.4074],
            'washington, dc' => [38.9072, -77.0369],
            'washington dc' => [38.9072, -77.0369],
            'london, uk' => [51.5074, -0.1278],
            'paris, france' => [48.8566, 2.3522],
            'berlin, germany' => [52.5200, 13.4050],
            'tokyo, japan' => [35.6762, 139.6503],
            'seoul, korea' => [37.5665, 126.9780],
            'seoul, south korea' => [37.5665, 126.9780],
            'tel aviv, israel' => [32.0853, 34.7818],
            'riyadh, saudi arabia' => [24.7136, 46.6753],
            'tehran, iran' => [35.6892, 51.3890],
        ];

        $normalized = strtolower(trim($location));
        if (isset($knownLocations[$normalized])) {
            return $knownLocations[$normalized];
        }

        foreach ($knownLocations as $key => $coords) {
            if (str_contains($normalized, explode(',', $key)[0])) {
                return $coords;
            }
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $location,
            'format' => 'json',
            'limit' => 1,
        ]);

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TheGistYouTubePipeline/1.0',
                'timeout' => 10,
            ],
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return null;
        }

        return [(float) $data[0]['lat'], (float) $data[0]['lon']];
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
