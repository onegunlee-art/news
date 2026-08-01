<?php
declare(strict_types=1);

$enabledRaw = $_ENV['ENABLE_DISCOVERY'] ?? getenv('ENABLE_DISCOVERY');
$enabled = is_string($enabledRaw) && strtolower(trim($enabledRaw)) === 'true';
$publicRaw = $_ENV['ENABLE_DISCOVERY_PUBLIC'] ?? getenv('ENABLE_DISCOVERY_PUBLIC');
$publicEnabled = is_string($publicRaw) && strtolower(trim($publicRaw)) === 'true';
$showSeedRaw = $_ENV['DISCOVERY_SHOW_SEED'] ?? getenv('DISCOVERY_SHOW_SEED');
$showSeed = is_string($showSeedRaw) && strtolower(trim($showSeedRaw)) === 'true';

return [
    'enabled' => $enabled,
    'public_enabled' => $publicEnabled,
    'show_seed' => $showSeed,
    'model' => $_ENV['DISCOVERY_LLM_MODEL'] ?? getenv('DISCOVERY_LLM_MODEL') ?: 'gpt-4o',
    'candidate_count' => 12,
    'target_changes' => 7,
    'min_changes' => 5,
    'max_age_hours' => 48,
    'category_targets' => [
        'geopolitics' => 4,
        'business' => 3,
        'tech' => 2,
        'climate' => 1,
        'other' => 2,
    ],
    'archive_days' => 30,
    'use_dummy_preview_stats' => true,
    'source_whitelist' => require __DIR__ . '/discovery_sources.php',
    'source_blocklist' => require __DIR__ . '/discovery_source_blocklist.php',
    'rss_feeds' => require __DIR__ . '/discovery_feeds.php',
    'min_catalog_articles' => 8,
];
