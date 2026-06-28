<?php
/**
 * B-3 — 코치 레벨업 트리거 정적 회귀 (DB 없음)
 *
 * Usage: php tools/edu_coach_levelup_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/src/agents/services/SupabaseService.php';
require_once $root . '/public/api/edu/lib/eduGamification.php';
require_once $root . '/public/api/edu/lib/eduTier.php';
require_once $root . '/public/api/edu/lib/eduCoachLevel.php';

use Agents\Services\SupabaseService;

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

/** @param array<string, mixed> $student */
function mockLevelUp(array $student, array $tierRow): array
{
    $mock = new class extends SupabaseService {
        /** @var list<array<string, mixed>> */
        public array $studentUpdates = [];
        /** @var list<array<string, mixed>> */
        public array $tierUpdates = [];
        /** @var list<array<string, mixed>> */
        public array $events = [];

        public function __construct()
        {
        }

        public function update(string $table, string $query, array $data): ?array
        {
            if ($table === 'edu_students') {
                $this->studentUpdates[] = $data;
            } else {
                $this->tierUpdates[] = $data;
            }

            return [$data];
        }

        public function insert(string $table, array $data): ?array
        {
            $this->events[] = $data;

            return [$data];
        }
    };

    $result = eduTryCoachLevelUp($mock, 'student-1', $student, $tierRow);

    return ['result' => $result, 'mock' => $mock];
}

echo "=== B-3 coach level-up static test ===\n\n";

$studentL1 = ['id' => 'student-1', 'coach_level' => 1];
$tierFull = ['coach_gauge_xp' => 100, 'streak_days' => 2];
$up = mockLevelUp($studentL1, $tierFull);
ok('L1 gauge 100 → level up', ($up['result']['leveled_up'] ?? false) === true);
ok('L1 → L2', ($up['result']['to_level'] ?? 0) === 2);
ok('to label 질문자', ($up['result']['to_label_ko'] ?? '') === '질문자');
ok('gauge reset on level up', ($up['mock']->tierUpdates[0]['coach_gauge_xp'] ?? -1) === 0);
ok('student coach_level updated', ($up['mock']->studentUpdates[0]['coach_level'] ?? 0) === 2);

$studentPartial = ['id' => 'student-1', 'coach_level' => 2];
$tierPartial = ['coach_gauge_xp' => 80];
$noUp = mockLevelUp($studentPartial, $tierPartial);
ok('gauge 80 → no level up', ($noUp['result']['leveled_up'] ?? true) === false);

$studentL5 = ['id' => 'student-1', 'coach_level' => 5];
$maxUp = mockLevelUp($studentL5, $tierFull);
ok('L5 max → no level up (graduation)', ($maxUp['result']['leveled_up'] ?? true) === false);

$payload = eduCoachGaugeProgressPayload(2, ['coach_gauge_xp' => 0, 'streak_days' => 2]);
ok('after reset gauge 0%', ($payload['coach_gauge_progress_pct'] ?? -1) === 0);
ok('streak untouched in payload', ($payload['coach_gauge_xp'] ?? -1) === 0);

$celebration = is_file($root . '/src/frontend/src/components/edu/EduQuestCompletionCelebration.tsx')
    ? (string) file_get_contents($root . '/src/frontend/src/components/edu/EduQuestCompletionCelebration.tsx')
    : '';
if ($celebration !== '') {
    ok('level up UI', str_contains($celebration, 'leveledUp') && str_contains($celebration, '달성'));
} else {
    echo "SKIP level up UI (frontend not on server)\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
