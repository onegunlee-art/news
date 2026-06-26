<?php
/**
 * Narration compose static checks (no LLM)
 * php tools/edu_compose_narration_static_verify.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function check(bool $ok, string $label): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

function read(string $rel): string
{
    global $root;
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string) file_get_contents($path) : '';
}

$composer = read('src/backend/Services/edu/Agents/GistStyleComposer.php');
$gate = read('src/backend/Services/edu/EduWritingGate.php');
$panel = read('src/frontend/src/components/edu/EduEssayCompletionPanel.tsx');

check(str_contains($composer, 'body_paragraphs'), 'composer: body_paragraphs schema');
check(str_contains($composer, '소제목·섹션 제목 금지'), 'composer: no subheading rule');
check(str_contains($composer, '학생 결론만'), 'composer: student conclusion preserve');
check(str_contains($composer, 'renderNarrationPlainText'), 'composer: narration plain text');

check(str_contains($gate, 'verifyNarration'), 'gate: narration verify path');
check(str_contains($gate, 'polishNarration'), 'gate: narration polish path');

check(str_contains($panel, '구조 보기'), 'ui: structure toggle');
check(str_contains($panel, "view === 'essay'"), 'ui: default essay view');

require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduWritingGate;

$composerRef = new ReflectionClass(GistStyleComposer::class);
$composerInstance = $composerRef->newInstanceWithoutConstructor();
$renderMethod = $composerRef->getMethod('renderNarrationPlainText');
$text = $renderMethod->invoke($composerInstance, '제목', '부제', ['1단락', '2단락']);
check(str_contains($text, '1단락') && !str_contains($text, '배경'), 'render: no structure labels in text');

$gateObj = new EduWritingGate();
$narrationDraft = [
    'title' => '테스트',
    'body_paragraphs' => [
        'AI 때문에 전기세가 걱정된다. 데이터센터가 늘어나는 걸 보면 더 그렇다.',
        '그래서 나는 전기를 더 만들어야 한다고 생각한다. 물론 전력망 문제도 있지만.',
    ],
    'full_text' => str_repeat('가', 400),
    'narration_mode' => true,
];
$verify = $gateObj->verify($narrationDraft);
check($verify['passed'] === true, 'gate: narration draft passes');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
