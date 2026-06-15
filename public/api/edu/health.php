<?php
/**
 * GIST EDU — Health check endpoint
 * GET /api/edu/health
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/eduConfig.php';
require_once __DIR__ . '/lib/_llm.php';
require_once __DIR__ . '/lib/eduDraftStorage.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '1.1.0-pilot',
    'feature_flags' => [
        'edu_use_turn_fsm' => eduUseTurnFsm(),
        'edu_use_chat_engine' => eduUseChatEngine(),
        'edu_mixup_rag' => eduMixupRagEnabled(),
        'edu_judgment_writing' => eduJudgmentWritingEnabled(),
        'edu_strict_draft_storage' => eduStrictDraftStorage(),
        'edu_llm_provider' => eduLlmProvider(),
    ],
    'services' => [],
];

try {
    $supabase = eduSupabase();
    $checks['services']['supabase'] = $supabase->isConfigured() ? 'ok' : 'not_configured';
    if ($supabase->isConfigured()) {
        $checks['schema'] = eduProbeDraftStorageSchema($supabase);
        if (($checks['schema']['draft_storage'] ?? '') === 'blueprint_fallback_only') {
            $checks['status'] = 'degraded';
        }
    }
} catch (Throwable $e) {
    $checks['services']['supabase'] = 'error';
}

$provider = eduLlmProvider();
$checks['services']['llm_provider'] = $provider;

if ($provider === 'anthropic') {
    $anthropicKey = getenv('EDU_ANTHROPIC_API_KEY') ?: getenv('ANTHROPIC_API_KEY');
    $checks['services']['llm'] = !empty($anthropicKey) ? 'configured' : 'missing_key';
} else {
    $openaiKey = getenv('OPENAI_API_KEY');
    $checks['services']['llm'] = !empty($openaiKey) ? 'configured' : 'missing_key';
    $checks['services']['llm_model'] = getenv('EDU_OPENAI_MODEL') ?: 'gpt-5.4';
    $checks['services']['llm_fast_model'] = getenv('EDU_OPENAI_FAST_MODEL') ?: 'gpt-5.4-mini';
}

$checks['services']['llm_daily_cap'] = (int) (getenv('EDU_DAILY_LLM_CAP') ?: 1000);
$checks['services']['admin_api_key'] = getenv('EDU_ADMIN_API_KEY') ? 'configured' : 'missing_key';

$root = eduFindProjectRoot();
$newsHotpath = [
    'public/api/admin/news.php',
    'public/api/admin/ai-analyze.php',
    'src/agents/services/RAGService.php',
];
$checks['news_pipeline_frozen'] = [];
foreach ($newsHotpath as $rel) {
    $checks['news_pipeline_frozen'][$rel] = is_file($root . $rel) ? 'present' : 'missing';
}

if (in_array('error', $checks['services'], true) || in_array('not_configured', $checks['services'], true)) {
    $checks['status'] = 'degraded';
}

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
