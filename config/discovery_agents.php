<?php
declare(strict_types=1);

/**
 * Discovery multi-agent model configuration.
 * Each agent can use a different model; fallback used on API failure.
 */
return [
    'agents' => [
        'curator' => [
            'model' => $_ENV['DISCOVERY_CURATOR_MODEL'] ?? getenv('DISCOVERY_CURATOR_MODEL') ?: 'gpt-4o',
            'fallback' => 'gpt-4o',
        ],
        'briefer' => [
            'model' => $_ENV['DISCOVERY_BRIEFER_MODEL'] ?? getenv('DISCOVERY_BRIEFER_MODEL') ?: 'gpt-4o',
            'fallback' => 'gpt-4o',
        ],
        'factchecker' => [
            'model' => $_ENV['DISCOVERY_FACTCHECKER_MODEL'] ?? getenv('DISCOVERY_FACTCHECKER_MODEL') ?: 'gpt-4o-mini',
            'fallback' => null,
        ],
        'pollster' => [
            'model' => $_ENV['DISCOVERY_POLLSTER_MODEL'] ?? getenv('DISCOVERY_POLLSTER_MODEL') ?: 'gpt-4o-mini',
            'fallback' => 'gpt-4o',
        ],
    ],
    'pipeline' => [
        'curator_limit' => 15,
        'max_body_chars' => 12000,
        'extractor_timeout_sec' => 20,
    ],
];
