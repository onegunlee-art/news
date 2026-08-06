<?php
declare(strict_types=1);

namespace Youtube\Agents\Visuals;

/**
 * Loads Natural Earth country boundaries (public domain GeoJSON)
 * and resolves country names from location strings.
 */
final class CountryGeoData
{
    private string $geoJsonPath;
    /** @var array<string, array{properties: array<string, mixed>, geometry: array<string, mixed>}>|null */
    private ?array $index = null;

    /** @var array<string, string> */
    private const ALIASES = [
        'uk' => 'United Kingdom',
        'u.k.' => 'United Kingdom',
        'britain' => 'United Kingdom',
        'great britain' => 'United Kingdom',
        'england' => 'United Kingdom',
        'usa' => 'United States of America',
        'us' => 'United States of America',
        'u.s.' => 'United States of America',
        'u.s.a.' => 'United States of America',
        'united states' => 'United States of America',
        'south korea' => 'South Korea',
        'republic of korea' => 'South Korea',
        'korea' => 'South Korea',
        'north korea' => 'North Korea',
        'dprk' => 'North Korea',
        'uae' => 'United Arab Emirates',
        'czech republic' => 'Czechia',
        'czechia' => 'Czechia',
        'russia' => 'Russia',
        'russian federation' => 'Russia',
        'ukraine' => 'Ukraine',
        'china' => 'China',
        'prc' => 'China',
        'taiwan' => 'Taiwan',
        'iran' => 'Iran',
        'israel' => 'Israel',
        'saudi arabia' => 'Saudi Arabia',
        'syria' => 'Syria',
        'turkey' => 'Turkey',
        'türkiye' => 'Turkey',
    ];

    public function __construct(string $geoJsonPath)
    {
        $this->geoJsonPath = $geoJsonPath;
    }

    public function extractCountryName(string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $location));
        if (count($parts) >= 2) {
            return $parts[count($parts) - 1];
        }

        return $location;
    }

    /**
     * @return array{properties: array<string, mixed>, geometry: array<string, mixed>}|null
     */
    public function findCountry(string $countryName): ?array
    {
        $this->loadIndex();
        if ($this->index === null) {
            return null;
        }

        $normalized = $this->normalize($countryName);
        if ($normalized === '') {
            return null;
        }

        if (isset(self::ALIASES[$normalized])) {
            $normalized = $this->normalize(self::ALIASES[$normalized]);
        }

        if (isset($this->index[$normalized])) {
            return $this->index[$normalized];
        }

        return $this->fuzzyMatch($normalized);
    }

    /**
     * Pick the polygon ring(s) most relevant to a map marker.
     * For MultiPolygon countries (e.g. Russia + Kaliningrad), use the polygon containing the marker.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    public function getRelevantRings(array $geometry, ?float $lat = null, ?float $lon = null): array
    {
        $rings = $this->getExteriorRings($geometry);
        if ($rings === [] || ($lat === null || $lon === null)) {
            return $rings;
        }

        foreach ($rings as $ring) {
            if ($this->pointInRing($lon, $lat, $ring)) {
                return [$ring];
            }
        }

        return [$this->largestRing($rings)];
    }

    /**
     * @param list<list<array{0: float, 1: float}>> $rings
     * @return list<array{0: float, 1: float}>
     */
    private function largestRing(array $rings): array
    {
        $best = $rings[0];
        $bestArea = 0.0;

        foreach ($rings as $ring) {
            $area = $this->ringBBoxArea($ring);
            if ($area > $bestArea) {
                $bestArea = $area;
                $best = $ring;
            }
        }

        return $best;
    }

    /**
     * @param list<array{0: float, 1: float}> $ring
     */
    private function ringBBoxArea(array $ring): float
    {
        $bbox = $this->computeBoundingBox([$ring]);
        return ($bbox['maxLon'] - $bbox['minLon']) * ($bbox['maxLat'] - $bbox['minLat']);
    }

    /**
     * @param list<array{0: float, 1: float}> $ring
     */
    private function pointInRing(float $lon, float $lat, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $ring[$i][0];
            $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0];
            $yj = (float) $ring[$j][1];

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    public function getExteriorRings(array $geometry): array
    {
        $type = $geometry['type'] ?? '';
        $coords = $geometry['coordinates'] ?? [];

        if ($type === 'Polygon') {
            return isset($coords[0]) ? [$coords[0]] : [];
        }

        if ($type === 'MultiPolygon') {
            $rings = [];
            foreach ($coords as $polygon) {
                if (isset($polygon[0])) {
                    $rings[] = $polygon[0];
                }
            }
            return $rings;
        }

        return [];
    }

    /**
     * @param list<list<array{0: float, 1: float}>> $rings
     * @return array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     */
    public function computeBoundingBox(array $rings): array
    {
        $minLon = 180.0;
        $maxLon = -180.0;
        $minLat = 90.0;
        $maxLat = -90.0;

        foreach ($rings as $ring) {
            foreach ($ring as $point) {
                $lon = (float) $point[0];
                $lat = (float) $point[1];
                $minLon = min($minLon, $lon);
                $maxLon = max($maxLon, $lon);
                $minLat = min($minLat, $lat);
                $maxLat = max($maxLat, $lat);
            }
        }

        return compact('minLon', 'maxLon', 'minLat', 'maxLat');
    }

    private function loadIndex(): void
    {
        if ($this->index !== null) {
            return;
        }

        if (!is_file($this->geoJsonPath)) {
            $this->index = [];
            return;
        }

        $raw = file_get_contents($this->geoJsonPath);
        if ($raw === false) {
            $this->index = [];
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
            $this->index = [];
            return;
        }

        $index = [];
        foreach ($data['features'] as $feature) {
            if (!is_array($feature) || !isset($feature['properties'], $feature['geometry'])) {
                continue;
            }

            $props = $feature['properties'];
            $entry = [
                'properties' => $props,
                'geometry' => $feature['geometry'],
            ];

            foreach (['NAME', 'ADMIN', 'NAME_LONG', 'NAME_EN', 'ISO_A3'] as $field) {
                $value = trim((string) ($props[$field] ?? ''));
                if ($value === '' || $value === '-99') {
                    continue;
                }
                $index[$this->normalize($value)] = $entry;
            }
        }

        $this->index = $index;
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s\-\.\']/u', '', $value) ?? $value;
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function fuzzyMatch(string $normalized): ?array
    {
        if ($this->index === null) {
            return null;
        }

        foreach ($this->index as $key => $feature) {
            if (strlen($key) < 4) {
                continue;
            }
            if ($normalized === $key || str_ends_with($normalized, $key)) {
                return $feature;
            }
        }

        return null;
    }
}
