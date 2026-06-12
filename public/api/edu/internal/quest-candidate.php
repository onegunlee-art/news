<?php
/**
 * POST /api/edu/internal/quest-candidate.php
 * Cron/Admin trigger: scan recent published articles → draft quests (no auto-approve)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/_llm.php';
require_once __DIR__ . '/../lib/eduMysql.php';
require_once __DIR__ . '/../lib/eduAgents.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();
eduRequireAdminKey();

eduLoadAgents();

use Services\Edu\EduQuestFactory;

$body = eduJsonBody();
$maxCandidates = min(10, max(1, (int) ($body['limit'] ?? 3)));
$lookbackDays = min(180, max(7, (int) ($body['lookback_days'] ?? 90)));
$dryRun = !empty($body['dry_run']);

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Supabase not configured', 503);
}

try {
    $pdo = eduMysql();
    $llm = eduLlm();
} catch (Throwable $e) {
    eduSendError($e->getMessage(), 500);
}

$factory = new EduQuestFactory($pdo, $supabase, $llm);
$candidates = $factory->discoverCandidates($maxCandidates, $lookbackDays);

$results = [];
foreach ($candidates as $draft) {
    $saved = $factory->persistDraft($draft, $dryRun);
    if ($saved !== null) {
        $results[] = array_merge($saved, [
            'article_count' => count($draft['articles'] ?? []),
            'manual_arc' => $draft['manual_arc'] ?? null,
            'quest_title' => $draft['quest_title'] ?? '',
        ]);
    }
}

eduSendJson([
    'success' => true,
    'dry_run' => $dryRun,
    'candidates_found' => count($candidates),
    'quests_created' => count($results),
    'quests' => $results,
]);
