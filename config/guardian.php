<?php

if (!function_exists('guardianNormalizeApiKey')) {
    /** Guardian Open Platform API key (not JSON response body). */
    function guardianNormalizeApiKey(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }
        $key = trim($raw);
        if ($key === '') {
            return '';
        }
        // .env에 API 응답 JSON을 통째로 넣은 경우 방지
        if ($key[0] === '{' || str_starts_with($key, '{"response"')) {
            return '';
        }
        return $key;
    }
}

return [
    'api_key' => guardianNormalizeApiKey($_ENV['GUARDIAN_API_KEY'] ?? getenv('GUARDIAN_API_KEY')),
    'base_url' => 'https://content.guardianapis.com',
    'sections' => ['world', 'us-news', 'business', 'technology'],
    'page_size' => 20,
];
