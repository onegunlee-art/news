<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Generates dark-style map images using CartoDB/OSM tiles.
 * Creates 1080x1920 vertical map with location marker.
 */
final class MapGenerator
{
    private const TILE_URL = 'https://cartodb-basemaps-{s}.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png';
    private const TILE_SIZE = 256;
    private const SUBDOMAINS = ['a', 'b', 'c', 'd'];

    private int $width;
    private int $height;
    private array $style;
    private string $storagePath;

    public function __construct(array $config)
    {
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->style = $config['style'] ?? [];
        $this->storagePath = $config['storage_path'] ?? 'storage/youtube';
    }

    /**
     * Generate a map image for a location.
     * @return string Path to generated image
     */
    public function generate(string $location, string $projectPath): string
    {
        $coords = $this->geocode($location);
        if ($coords === null) {
            throw new \RuntimeException("MapGenerator: Could not geocode location: {$location}");
        }

        [$lat, $lon] = $coords;
        $zoom = 10;

        $image = $this->createMapImage($lat, $lon, $zoom);
        $this->addMarker($image, $lat, $lon, $zoom);
        $this->addLocationLabel($image, $location);
        
        $outputPath = $projectPath . '/scene_2_map.png';
        $this->ensureDirectory(dirname($outputPath));
        
        imagepng($image, $outputPath);
        imagedestroy($image);

        return $outputPath;
    }

    private function geocode(string $location): ?array
    {
        $knownLocations = [
            'kyiv, ukraine' => [50.4501, 30.5234],
            'kyiv' => [50.4501, 30.5234],
            'kiev, ukraine' => [50.4501, 30.5234],
            'moscow, russia' => [55.7558, 37.6173],
            'beijing, china' => [39.9042, 116.4074],
            'washington, dc' => [38.9072, -77.0369],
            'london, uk' => [51.5074, -0.1278],
            'paris, france' => [48.8566, 2.3522],
            'berlin, germany' => [52.5200, 13.4050],
            'tokyo, japan' => [35.6762, 139.6503],
            'seoul, korea' => [37.5665, 126.9780],
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

    private function createMapImage(float $lat, float $lon, int $zoom): \GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $bgColor = imagecolorallocate($image, ...$this->style['bg_rgb'] ?? [10, 10, 10]);
        imagefill($image, 0, 0, $bgColor);

        $centerX = $this->lonToTileX($lon, $zoom);
        $centerY = $this->latToTileY($lat, $zoom);

        $tilesX = (int) ceil($this->width / self::TILE_SIZE) + 2;
        $tilesY = (int) ceil($this->height / self::TILE_SIZE) + 2;

        $startTileX = (int) floor($centerX - $tilesX / 2);
        $startTileY = (int) floor($centerY - $tilesY / 2);

        $offsetX = (int) (($centerX - $startTileX - $tilesX / 2) * self::TILE_SIZE + $this->width / 2);
        $offsetY = (int) (($centerY - $startTileY - $tilesY / 2) * self::TILE_SIZE + $this->height / 2);

        for ($x = 0; $x < $tilesX; $x++) {
            for ($y = 0; $y < $tilesY; $y++) {
                $tileX = $startTileX + $x;
                $tileY = $startTileY + $y;
                
                $tile = $this->fetchTile($zoom, $tileX, $tileY);
                if ($tile !== null) {
                    $destX = $offsetX + $x * self::TILE_SIZE;
                    $destY = $offsetY + $y * self::TILE_SIZE;
                    imagecopy($image, $tile, $destX, $destY, 0, 0, self::TILE_SIZE, self::TILE_SIZE);
                    imagedestroy($tile);
                }
            }
        }

        return $image;
    }

    private function fetchTile(int $z, int $x, int $y): ?\GdImage
    {
        $subdomain = self::SUBDOMAINS[($x + $y) % count(self::SUBDOMAINS)];
        $url = str_replace(['{s}', '{z}', '{x}', '{y}'], [$subdomain, $z, $x, $y], self::TILE_URL);

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TheGistYouTubePipeline/1.0',
                'timeout' => 15,
            ],
        ];
        $context = stream_context_create($opts);
        $data = @file_get_contents($url, false, $context);

        if ($data === false) {
            return null;
        }

        return @imagecreatefromstring($data) ?: null;
    }

    private function addMarker(\GdImage $image, float $lat, float $lon, int $zoom): void
    {
        $markerX = $this->width / 2;
        $markerY = $this->height / 2;

        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, (int) $markerX, (int) $markerY, 30, 30, $gold);
        imagefilledellipse($image, (int) $markerX, (int) $markerY, 14, 14, $white);
        
        $points = [
            (int) $markerX, (int) ($markerY + 15),
            (int) ($markerX - 15), (int) ($markerY),
            (int) ($markerX + 15), (int) ($markerY),
        ];
        imagefilledpolygon($image, $points, $gold);
    }

    private function addLocationLabel(\GdImage $image, string $location): void
    {
        $gold = imagecolorallocate($image, ...$this->style['primary_rgb'] ?? [212, 175, 55]);
        $fontSize = 64;
        
        $fontPath = dirname(__DIR__, 4) . '/public/fonts/noto/noto_sans_kr_bold_b1d8ccaef03cabe0c50be6a406ebee03.ttf';
        
        if (!file_exists($fontPath)) {
            imagestring($image, 5, (int) ($this->width / 2 - 50), $this->height - 200, $location, $gold);
            return;
        }

        $bbox = imagettfbbox($fontSize, 0, $fontPath, $location);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $x = ($this->width - $textWidth) / 2;
        $y = $this->height - 150;

        imagettftext($image, $fontSize, 0, (int) $x, (int) $y, $gold, $fontPath, $location);
    }

    private function lonToTileX(float $lon, int $zoom): float
    {
        return (($lon + 180) / 360) * pow(2, $zoom);
    }

    private function latToTileY(float $lat, int $zoom): float
    {
        return (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * pow(2, $zoom);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
