<?php
return [
    'api_key' => (static function (): string {
        $key = $_ENV['GUARDIAN_API_KEY'] ?? getenv('GUARDIAN_API_KEY');
        return is_string($key) && $key !== '' ? $key : '';
    })(),
    'base_url' => 'https://content.guardianapis.com',
    'sections' => ['world', 'us-news', 'business', 'technology'],
    'page_size' => 20,
];
