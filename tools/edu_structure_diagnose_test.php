<?php
/**
 * P2-B structure diagnose — rule path (LLM 없음)
 * Usage: php tools/edu_structure_diagnose_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';
require_once $root . '/public/api/edu/lib/eduStructureDiagnose.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

$fixturePath = $root . '/docs/structure_diagnoses/fixture-630-sample.json';
$fixture = is_file($fixturePath)
    ? json_decode((string) file_get_contents($fixturePath), true)
    : null;
if (!is_array($fixture)) {
    $fixture = [
        'session_id' => 'fixture-630-inline',
        'quest' => [
            'quest_code' => 'Q-AUTO-NUKE-630',
            'hammer_hints' => eduCoachGuideAttachHints([]),
        ],
        'blueprint' => [
            'guide_axis_answers' => [
                'military' => '우크라이나 사례 보면 재래식 보복만 했다',
                'norms' => '인도 파키스탄 규범 약속 이야기',
                'defense' => '방공과 기지 방호에 먼저 쓸 것 같다',
            ],
            'guide_student_conclusion' => '나는 핵 억지는 크게는 되지만 재래식까지는 약하다고 본다',
        ],
        'dialogue' => [],
        'essay_text' => '',
    ];
}
$diag = eduStructureDiagnoseSession(
    (string) ($fixture['session_id'] ?? 'fixture'),
    $fixture['quest'],
    $fixture['blueprint'],
    $fixture['dialogue'],
    null,
    (string) ($fixture['essay_text'] ?? '')
);

ok('internal_only flag', ($diag['internal_only'] ?? false) === true);
ok('no score key', !array_key_exists('score', $diag));
ok('no grade key', !array_key_exists('grade', $diag));
ok('3 axes in coverage', count($diag['axes_covered'] ?? []) === 3);
$covered = array_filter($diag['axes_covered'] ?? [], static fn ($a) => !empty($a['covered']));
ok('fixture 3 axes covered', count($covered) === 3);
ok('tension enum', in_array($diag['tension_engaged'] ?? '', ['양면', '한쪽', '없음'], true));
ok('clarity enum', in_array($diag['conclusion_clarity'] ?? '', ['명확', '모호'], true));
ok('evidence enum', in_array($diag['evidence_linked'] ?? '', ['yes', 'no'], true));
ok('rule fallback has level', is_int($diag['exploration_depth_level'] ?? null) && ($diag['exploration_depth_level'] ?? 0) >= 1);
ok('diagnose_mode rule_fallback', ($diag['diagnose_mode'] ?? '') === 'rule_fallback');
$note = (string) ($diag['structure_note'] ?? '');
ok('structure_note no grade smell', !preg_match('/\d+\s*\/\s*\d|등급|점수|잘함|못함/u', $note));

$row = eduStructureInsightRowFromDiagnose('00000000-0000-0000-0000-000000000001', $diag);
ok('insight row no score column', !array_key_exists('score', $row));
ok('insight row axes 3/3', ($row['axes_engaged_count'] ?? 0) === 3 && ($row['axes_total'] ?? 0) === 3);
ok('insight row internal_only', ($row['internal_only'] ?? false) === true);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
