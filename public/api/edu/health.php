<?php
/**
 * GIST EDU — Health check endpoint
 * GET /api/edu/health
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '1.0.0-pilot',
    'services' => [],
];

try {
    $supabase = eduSupabase();
    $checks['services']['supabase'] = $supabase->isConfigured() ? 'ok' : 'not_configured';
} catch (Throwable $e) {
    $checks['services']['supabase'] = 'error';
}

$anthropicKey = getenv('EDU_ANTHROPIC_API_KEY') ?: getenv('ANTHROPIC_API_KEY');
$checks['services']['anthropic'] = !empty($anthropicKey) ? 'configured' : 'missing_key';

$checks['services']['llm_daily_cap'] = (int)(getenv('EDU_DAILY_LLM_CAP') ?: 1000);

if (in_array('error', $checks['services'], true) || in_array('not_configured', $checks['services'], true)) {
    $checks['status'] = 'degraded';
}

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
