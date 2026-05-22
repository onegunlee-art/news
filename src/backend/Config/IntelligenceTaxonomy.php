<?php
declare(strict_types=1);

namespace App\Config;

class IntelligenceTaxonomy
{
    public const REGIONS = [
        'UNITED_STATES', 'CHINA', 'RUSSIA', 'EUROPE', 'MIDDLE_EAST',
        'ASIA_PACIFIC', 'TAIWAN', 'INDIA', 'AFRICA', 'LATIN_AMERICA', 'GLOBAL',
    ];

    public const TOPICS = [
        'trade', 'technology', 'semiconductor', 'ai', 'energy', 'finance',
        'military', 'diplomacy', 'supply_chain', 'cybersecurity', 'climate', 'domestic',
    ];

    public const EVENT_TYPES = [
        'sanction', 'export_control', 'military_action', 'diplomatic_meeting',
        'treaty', 'regulation', 'election', 'economic_data', 'corporate', 'incident',
    ];

    public static function isValidRegion(string $value): bool
    {
        return in_array($value, self::REGIONS, true);
    }

    public static function isValidTopic(string $value): bool
    {
        return in_array($value, self::TOPICS, true);
    }

    public static function isValidEventType(string $value): bool
    {
        return in_array($value, self::EVENT_TYPES, true);
    }

    public static function filterRegions(array $values): array
    {
        return array_values(array_filter($values, fn($v) => self::isValidRegion((string) $v)));
    }

    public static function filterTopics(array $values): array
    {
        return array_values(array_filter($values, fn($v) => self::isValidTopic((string) $v)));
    }
}
