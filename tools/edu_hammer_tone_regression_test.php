<?php
/**
 * Hammer 탐구조 톤 + delivery suffix 회귀 (LLM 없음)
 *
 * Usage: php tools/edu_hammer_tone_regression_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

use Services\Edu\Agents\Hammer;

eduLoadAgents();

$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

function assertNotContains(string $label, string $haystack, string $needle): void
{
    assertTrue($label, !str_contains($haystack, $needle));
}

function assertContains(string $label, string $haystack, string $needle): void
{
    assertTrue($label, str_contains($haystack, $needle));
}

/** @return string */
function hammerToneBlock(): string
{
    $ref = new ReflectionClass(Hammer::class);
    $method = $ref->getMethod('warmExplorationToneBlock');
    $method->setAccessible(true);
    $hammer = $ref->newInstanceWithoutConstructor();
    return (string) $method->invoke($hammer);
}

class ToneCaptureLlm
{
    public string $lastSystem = '';

    public function chat(string $system, array $messages, int $maxTokens = 400, float $temp = 0.7): array
    {
        $this->lastSystem = $system;
        $user = $messages[0]['content'] ?? '';
        $quoted = '';
        if (preg_match('/"([^"]+)"/', $user, $m)) {
            $quoted = $m[1];
        }
        $content = "적국 사이라 \"{$quoted}\"에서 말한 것처럼 스스로 지킬 힘이 필요하다는 거구나. ";
        $content .= '다른 사람들은 다르게 보기도 해. 너는 어때?';
        return ['content' => $content];
    }

    public function haiku(string $system, array $messages, int $maxTokens = 100): array
    {
        $user = $messages[0]['content'] ?? '';
        if (str_contains($user, '방어')) {
            return ['content' => '{"axis_id":"defense","scores":{"defense":0.9,"military":0.2},"confidence":"high","cue":"방어"}'];
        }
        return ['content' => '{"axis_id":"military","scores":{"military":0.9,"defense":0.2},"confidence":"high","cue":"군사"}'];
    }
}

echo "=== Hammer tone regression (no LLM) ===\n\n";

$tone = hammerToneBlock();
assertContains('tone: concrete acknowledgment hint', $tone, '구체적');
assertContains('tone: bans formal empathy', $tone, '그렇게 보는구나');
assertContains('tone: example good ack', $tone, '스스로 지킬 힘');

$bannedDelivery = ['반론', '토론 상대', '받아쳐', '강력한 반론', '이 반론에 대해 어떻게 생각해'];
$sampleBody = '적국 사이라 스스로 지킬 힘이 필요하다는 거구나. 다른 사람들은 다르게 보기도 해.';
$formatted = eduFormatHammerDelivery($sampleBody, 'adversarial');
foreach ($bannedDelivery as $word) {
    assertNotContains("eduFormatHammerDelivery bans: {$word}", $formatted, $word);
}
assertContains('format: warm suffix', $formatted, '이런 시각도 있는데, 너는 어때?');

$metaFormatted = eduFormatHammerDelivery($sampleBody, 'convergent_meta_ask');
assertContains('meta_ask suffix', $metaFormatted, '어느 쪽이 더 와닿아?');

$turnPrompt = eduHammerInvitePrompt('');
assertNotContains('turn invite: no legacy counter prompt', $turnPrompt, '이 반론에 대해 어떻게 생각해');
assertContains('turn invite: warm explore', $turnPrompt, '이런 시각도 있는데');

$llm = new ToneCaptureLlm();
$hammer = new Hammer($llm);
$quest = eduNuke630QuestFixture();
$quest['hammer_hints'] = [
    'mode' => 'convergent',
    'axes' => [
        [
            'axis_id' => 'military',
            'axis_label' => '군사적 한계',
            'author' => '전문가 A',
            'contrast_prompt' => [
                'names_axis' => '군사적 한계',
                'distinguishes_from' => ['defense' => '방어 체계와는 다르게'],
                'pivot_question' => '군사적 한계 관점에서 보면 어때?',
            ],
        ],
        [
            'axis_id' => 'defense',
            'axis_label' => '방어 체계',
            'author' => '전문가 B',
            'contrast_prompt' => [
                'names_axis' => '방어 체계',
                'distinguishes_from' => ['military' => '군사적 한계와는 다르게'],
                'pivot_question' => '방어 체계 관점에서 보면 어때?',
            ],
        ],
    ],
    'counter_map' => ['defense' => 'military'],
];

$studentReason = '적국 사이라 핵은 방어 수단이야. 스스로 지킬 힘이 필요해.';
$strike = $hammer->strike('pro', $studentReason, $quest, 'medium', null, '');
assertContains('convergent system includes tone block', $llm->lastSystem, '구체적');
assertContains('convergent system bans formal empathy phrase', $llm->lastSystem, '형식적·영혼 없는 인정');

$advQuest = [
    'pro_line' => '핵은 억지력이다',
    'con_line' => '핵은 위험하다',
    'hammer_hints' => ['mode' => 'adversarial', 'pro' => '억지', 'con' => '위험'],
];
$adv = $hammer->strike('pro', $studentReason, $advQuest, 'medium', null, '');
assertTrue('adversarial strike succeeds', ($adv['success'] ?? false) === true);
assertContains('adversarial prompt in system', $llm->lastSystem, '형식적');

echo "\n=== Result: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
