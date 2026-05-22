<?php
return [
    'api_key' => getenv('GUARDIAN_API_KEY') ?: '',
    'base_url' => 'https://content.guardianapis.com',
    'sections' => ['world', 'us-news', 'business', 'technology'],
    'page_size' => 20,
];
