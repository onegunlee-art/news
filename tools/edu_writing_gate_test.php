<?php
/**
 * Smoke test for EduWritingGate
 * php tools/edu_writing_gate_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/';
require_once $root . 'src/backend/autoload.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Services\\Edu\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $root . 'src/backend/Services/edu/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Services\Edu\EduWritingGate;

$gate = new EduWritingGate();

$shallow = [
    'full_text' => '짧다.',
    'scqa_parts' => [
        'situation' => '짧',
        'complication' => '',
        'question' => '',
        'answer' => '',
        'conclusion' => '',
    ],
];

$deep = [
    'full_text' => str_repeat('나는 찬성한다. 왜냐하면 일자리가 중요하기 때문이다. ', 5),
    'scqa_parts' => [
        'situation' => 'AI가 일자리에 영향을 주는 상황이 벌어지고 있다.',
        'complication' => '하지만 성장과 안전 사이에서 의견이 갈린다.',
        'question' => '우리는 어떤 균형을 택해야 할까?',
        'answer' => '나는 찬성한다. 왜냐하면 선제 대응이 필요하기 때문이다.',
        'conclusion' => '결론적으로 나의 입장은 대화를 통해 더 분명해졌다.',
    ],
];

$r1 = $gate->verify($shallow);
$r2 = $gate->verify($deep);

echo "Shallow passed: " . ($r1['passed'] ? 'yes' : 'no') . " score={$r1['structure_score']}\n";
echo "Deep passed: " . ($r2['passed'] ? 'yes' : 'no') . " score={$r2['structure_score']}\n";

if (!$r1['passed'] && $r2['passed']) {
    echo "OK\n";
    exit(0);
}

echo "FAIL\n";
exit(1);
