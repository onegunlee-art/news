<?php
declare(strict_types=1);

namespace App\Config;

class EntityCanonicalMap
{
    public const COUNTRY_ALIASES = [
        'UNITED_STATES' => ['US', 'U.S.', 'USA', 'United States', 'America', '??'],
        'CHINA' => ['CN', 'PRC', 'China', '??', "People's Republic of China"],
        'RUSSIA' => ['RU', 'Russia', 'Russian Federation', '???'],
        'EUROPE' => ['EU', 'Europe', 'European Union', '??'],
        'MIDDLE_EAST' => ['Middle East', '??'],
        'TAIWAN' => ['TW', 'Taiwan', 'ROC', '??', '???'],
        'INDIA' => ['IN', 'India', '??'],
        'ASIA_PACIFIC' => ['Asia Pacific', 'APAC', '????'],
        'AFRICA' => ['Africa', '????'],
        'LATIN_AMERICA' => ['Latin America', '??'],
    ];

    public const ORG_ALIASES = [
        'TSMC' => ['Taiwan Semiconductor', 'TSMC', '???'],
        'NATO' => ['NATO', 'North Atlantic Treaty Organization', '??'],
        'NVIDIA' => ['NVIDIA', 'Nvidia', '????'],
        'EU' => ['European Union', 'EU', 'European Commission'],
    ];

    public static function canonicalizeText(string $text): string
    {
        $result = $text;
        foreach (array_merge(self::COUNTRY_ALIASES, self::ORG_ALIASES) as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias === '') {
                    continue;
                }
                $pattern = '/\b' . preg_quote($alias, '/') . '\b/u';
                $result = preg_replace($pattern, $canonical, $result) ?? $result;
            }
        }
        return $result;
    }
}
