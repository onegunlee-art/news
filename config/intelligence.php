<?php
return [
    'min_word_count' => 150,
    'min_word_count_tier_a' => 80,
    'min_relevance_score' => 60,
    'min_relevance_score_tier_a' => 50,
    'dedup_title_threshold' => 0.80,
    'rss_feeds' => [
        ['name' => 'Reuters', 'url' => 'https://feeds.reuters.com/reuters/worldNews', 'trust_tier' => 'A'],
        ['name' => 'AP News', 'url' => 'https://apnews.com/index.rss', 'trust_tier' => 'A'],
        ['name' => 'BBC World', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'trust_tier' => 'A'],
    ],
    'nyt_sections' => ['world', 'politics'],
    'guardian_sections' => ['world', 'us-news'],
    'jina' => [
        'base_url' => 'https://r.jina.ai/',
        'delay_ms' => 1000,
    ],
    'trust_tier' => [
        'nyt' => 'A',
        'guardian' => 'A',
        'rss' => 'B',
    ],
];
