<?php
/**
 * Supabase Configuration
 *
 * pgvector for RAG: conversations, messages, critiques, embeddings.
 * Use SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in .env / env.txt.
 *
 * @package Config
 */

$url = $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
$serviceKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? getenv('SUPABASE_SERVICE_ROLE_KEY');
$anonKey = $_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY');

if (!is_string($url)) {
    $url = '';
}
if (!is_string($serviceKey)) {
    $serviceKey = '';
}
if (!is_string($anonKey)) {
    $anonKey = '';
}

return [
    'url' => rtrim($url, '/'),
    'anon_key' => $anonKey,
    'service_role_key' => $serviceKey,
];
