<?php
/**
 * decision_inquiry reflection + compose stance 라벨 격리 테스트
 *
 * Usage: php tools/edu_reflection_decision_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\Reflection;

/** LLM 실패 → fallback 경로 */
final class ReflectionFailLlm
{
    public function chat(string $system, array $messages, int $maxTokens = 500, float $temperature = 0.7): array
    {
        return ['error' => 'mock_fail'];
    }
}

function assertNoBannedTokens(string $label, string $text): void
{
    $banned = [' pro', ' con', 'pro ', 'con ', '찬성', '반대'];
    foreach ($banned as $token) {
        if (stripos($text, trim($token)) !== false) {
            fwrite(STDERR, "FAIL [{$label}]: banned token in: {$text}\n");
            exit(1);
        }
    }
    if (preg_match('/\b(pro|con)\b/i', $text)) {
        fwrite(STDERR, "FAIL [{$label}]: raw pro/con in: {$text}\n");
        exit(1);
    }
}

function assertContains(string $label, string $haystack, string $needle): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL [{$label}]: expected substring \"{$needle}\" in \"{$haystack}\"\n");
        exit(1);
    }
}

echo "=== decision_inquiry stance label test ===\n\n";

$iran = eduIranDecQuestFixture();
$g09 = eduG09DecQuestFixture();

// --- helper labels ---
$iranCon = eduDecisionStanceLabel('con', $iran);
$g09Con = eduDecisionStanceLabel('con', $g09);
echo "Iran con label: {$iranCon}\n";
echo "G09 con label:  {$g09Con}\n";

assertContains('iran label', $iranCon, '입장');
assertContains('g09 label', $g09Con, '입장');
assertNoBannedTokens('iran label', $iranCon);

// --- adversarial regression ---
$advQuest = [
    'pro_line' => '찬성 라인',
    'con_line' => '반대 라인',
    'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => 'adversarial'],
];
$advPro = eduStudentStanceLabel('pro', $advQuest);
if ($advPro !== '찬성') {
    fwrite(STDERR, "FAIL: adversarial pro should be 찬성, got {$advPro}\n");
    exit(1);
}
echo "Adversarial regression: pro → 찬성 OK\n";

// --- Reflection fallback (Iran con, stance maintained) ---
$reflection = new Reflection(new ReflectionFailLlm());
$iranSummary = $reflection->summarize(
    'con',
    '미사일만으로는 부족하다고 봤어요.',
    '다른 반론',
    '그래도 미사일만이 맞다고 생각해요.',
    'con',
    $iran,
    ['stance' => 'con', 'final_stance' => 'con']
);
$lines = $iranSummary['summary_lines'] ?? [];
echo "\nIran reflection fallback:\n";
foreach ($lines as $i => $line) {
    echo '  ' . ($i + 1) . ") {$line}\n";
    assertNoBannedTokens('iran reflection', $line);
}
assertContains('iran reflection L3', $lines[2] ?? '', '지켰');

// --- Reflection fallback (G09 con) ---
$g09Summary = $reflection->summarize(
    'con',
    '일본이 너무 무섭게 무기를 키우는 것 같아요.',
    '다른 반론',
    '그래도 주변 위험 때문에 필요하다고 봐요.',
    'con',
    $g09,
    ['stance' => 'con', 'final_stance' => 'con', 'student_axis' => 'tech']
);
$g09Lines = $g09Summary['summary_lines'] ?? [];
echo "\nG09 reflection fallback:\n";
foreach ($g09Lines as $i => $line) {
    echo '  ' . ($i + 1) . ") {$line}\n";
    assertNoBannedTokens('g09 reflection', $line);
}

// --- Compose stance_label (buildContext와 동일: eduStudentStanceLabel) ---
$iranStanceLabel = eduStudentStanceLabel('con', $iran);
echo "\nIran compose stance_label: {$iranStanceLabel}\n";
assertNoBannedTokens('iran compose stance_label', $iranStanceLabel);
assertContains('iran compose stance_label', $iranStanceLabel, '입장');

$advStanceLabel = eduStudentStanceLabel('pro', $advQuest);
if ($advStanceLabel !== '찬성') {
    fwrite(STDERR, "FAIL: adversarial compose stance_label should be 찬성, got {$advStanceLabel}\n");
    exit(1);
}
echo "Adversarial compose stance_label: 찬성 OK\n";

$iranPerspective = eduStudentPerspectiveLabel(['stance' => 'con', 'final_stance' => 'con'], $iran);
echo "Iran perspective (no axis): {$iranPerspective}\n";
assertNoBannedTokens('iran perspective', $iranPerspective);

echo "\nGATE: PASS\n";
