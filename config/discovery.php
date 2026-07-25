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
    'target_changes' => 9,
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
];
