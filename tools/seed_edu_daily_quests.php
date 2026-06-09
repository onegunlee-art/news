<?php
/**
 * GIST EDU — Seed edu_daily_quests + edu_quest_articles from GIST_EDU_QUESTS.json
 *
 * Usage:
 *   php tools/seed_edu_daily_quests.php           # Supabase insert
 *   php tools/seed_edu_daily_quests.php --dry-run   # Preview only
 *   php tools/seed_edu_daily_quests.php --students  # Also seed demo pilot students
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\SupabaseService;

$dryRun = in_array('--dry-run', $argv, true);
$seedStudents = in_array('--students', $argv, true);

$jsonPath = $root . '/docs/GIST_EDU_QUESTS.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing {$jsonPath}\n");
    exit(1);
}

$payload = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($payload) || empty($payload['quests'])) {
    fwrite(STDERR, "Invalid quests JSON\n");
    exit(1);
}

$supabase = new SupabaseService([]);
if (!$dryRun && !$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured. Use --dry-run or set config/supabase.php\n");
    exit(1);
}

function upsertQuest(SupabaseService $supabase, array $quest, bool $dryRun): ?string
{
    $code = $quest['quest_code'];
    $row = [
        'quest_code' => $code,
        'quest_title' => $quest['quest_title'],
        'grade_band' => $quest['grade_band'],
        'status' => $quest['status'] ?? 'approved',
        'manual_arc' => $quest['manual_arc'] ?? null,
        'pro_line' => $quest['pro_line'],
        'con_line' => $quest['con_line'],
        'alignment_summary' => $quest['alignment_summary'] ?? null,
        'conflict_summary' => $quest['conflict_summary'],
        'hammer_hints' => $quest['hammer_hints'] ?? new stdClass(),
        'fsm_stages' => $quest['fsm_stages'] ?? ['commit', 'hammer', 'reflection', 'writing', 'growth'],
        'pilot_priority' => $quest['pilot_priority'] ?? null,
        'scores' => $quest['scores'] ?? new stdClass(),
    ];

    if ($dryRun) {
        echo "[dry-run] quest {$code}\n";
        return 'dry-run-' . $code;
    }

    $existing = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($code), 1);
    if (!empty($existing[0]['id'])) {
        $id = $existing[0]['id'];
        $supabase->update('edu_daily_quests', 'id=eq.' . $id, $row);
        echo "Updated quest {$code}\n";
        return $id;
    }

    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        fwrite(STDERR, "Failed insert {$code}: " . $supabase->getLastError() . "\n");
        return null;
    }
    echo "Inserted quest {$code}\n";
    return $inserted[0]['id'];
}

function syncArticles(SupabaseService $supabase, string $questId, array $articles, bool $dryRun): void
{
    if ($dryRun) {
        echo "  [dry-run] " . count($articles) . " articles\n";
        return;
    }

    $supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
    $sort = 0;
    foreach ($articles as $article) {
        $supabase->insert('edu_quest_articles', [
            'quest_id' => $questId,
            'news_id' => (int) $article['news_id'],
            'role' => $article['role'],
            'sort_order' => $sort++,
            'title' => $article['title'] ?? null,
            'gist_url' => $article['gist_url'] ?? null,
        ]);
    }
}

$count = 0;
foreach ($payload['quests'] as $quest) {
    $questId = upsertQuest($supabase, $quest, $dryRun);
    if ($questId === null) {
        continue;
    }
    syncArticles($supabase, $questId, $quest['articles'] ?? [], $dryRun);
    $count++;
}

echo "Done: {$count} quests\n";

if ($seedStudents && !$dryRun) {
    $cohorts = $supabase->select('edu_pilot_cohorts', 'slug=eq.pilot-01', 1);
    $cohortId = $cohorts[0]['id'] ?? null;
    if ($cohortId === null) {
        fwrite(STDERR, "pilot-01 cohort not found. Run migration first.\n");
        exit(1);
    }

    $demoStudents = [
        ['display_name' => '데모학생01', 'grade_band' => 'middle', 'invite_code' => 'EDU-PILOT-01'],
        ['display_name' => '데모학생02', 'grade_band' => 'high', 'invite_code' => 'EDU-PILOT-02'],
    ];

    foreach ($demoStudents as $s) {
        $existing = $supabase->select('edu_students', 'invite_code=eq.' . rawurlencode($s['invite_code']), 1);
        if (!empty($existing[0]['id'])) {
            echo "Student {$s['invite_code']} exists\n";
            continue;
        }
        $token = bin2hex(random_bytes(16));
        $inserted = $supabase->insert('edu_students', [
            'cohort_id' => $cohortId,
            'display_name' => $s['display_name'],
            'grade_band' => $s['grade_band'],
            'invite_code' => $s['invite_code'],
            'access_token_hash' => hash('sha256', $token),
        ]);
        if ($inserted === null) {
            fwrite(STDERR, "Failed student: " . $supabase->getLastError() . "\n");
            continue;
        }
        $studentId = $inserted[0]['id'];
        $supabase->insert('edu_user_tier', [
            'student_id' => $studentId,
            'tier_id' => 'observer',
            'xp_current' => 0,
            'streak_days' => 0,
        ]);
        echo "Student {$s['invite_code']} token (save once): {$token}\n";
    }
}
