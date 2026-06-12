<?php
/**
 * Smoke test for EduWritingGate (structured essay)
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
    'title' => '짧은 제목',
    'full_text' => '너무 짧다.',
    'sections' => [
        ['heading' => 'A', 'paragraphs' => ['짧']],
    ],
    'conclusion_paragraphs' => ['짧'],
];

$deep = [
    'title' => '중국 AI 규제, 경쟁이 먼저인가',
    'subtitle' => '강규제와 성장 사이에서 학생이 본 균형',
    'sections' => [
        [
            'heading' => '왜 지금 중요한가',
            'paragraphs' => [
                '중국 정부는 AI를 국가 경쟁력의 핵심으로 보고 있어요. 동시에 안전과 규제 필요성도 함께 거론되고 있거든요.',
                '그래서 단순히 막는 것보다 경쟁에서 이기는 전략이 먼저라는 시각도 분명히 존재해요.',
            ],
        ],
        [
            'heading' => '견해가 갈리는 지점',
            'paragraphs' => [
                '한편 강한 규제가 혁신을 늦출 수 있다는 우려가 있어요. 반면 무분별한 확장은 리스크를 키울 수 있거든요.',
            ],
        ],
        [
            'heading' => '나는 반대한다',
            'paragraphs' => [
                '나는 지나친 규제에 반대해요. 중미 AI 경쟁은 소련과 미국의 우주 경쟁처럼, 결국 경쟁에서 이기는 쪽이 우선이라고 봐요.',
            ],
        ],
    ],
    'conclusion_heading' => '결론',
    'conclusion_paragraphs' => [
        '대화를 통해 나의 입장은 더 분명해졌어요. 규제보다 경쟁력 확보가 먼저라는 생각을 유지하지만, 리스크도 함께 봐야 한다는 점은 인정해요.',
    ],
    'full_text' => str_repeat('중국 AI ', 80),
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
