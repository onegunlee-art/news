<?php
/**
 * Local PDF layout probe — page count + font registration smoke
 *
 * Usage: php tools/edu_parent_report_pdf_layout_probe.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduParentReportPdf.php';

$payload = [
    'student_name' => '김민준',
    'grade_label' => '중학교 2학년',
    'period_label' => '2026년 5월 · 1주 활동',
    'cover' => [
        'headline' => '3개 세상을 스스로 따졌어요',
    ],
    'coach_letter' => [
        'paragraphs' => [
            '민준은 처음에 "AI는 좋으니 규제하지 말자"는 직관에서 출발했습니다. 그런데 고용지표만으로 안심하면 안 된다는 기사와, 아직 대규모 실업은 없다는 반론을 읽고 — 전면 규제 대신 재교육·안전망 쪽으로 입장을 정교화했습니다.',
            'v1에서 4번과 5번이 모순이었던 글이, v2에서는 주장-근거-반론-결론 구조로 정리되었습니다. 이것은 단순히 더 좋은 답을 찾은 것이 아니라, 근거를 붙이고 모순을 스스로 고치는 힘이 생겼다는 신호입니다.',
            '앞으로도 기사 근거를 붙이며 반론을 듣고 입장을 다듬는 연습을 이어가면 좋겠습니다.',
        ],
        'generated' => false,
    ],
    'before_after' => [
        'before_label' => '한 달 전',
        'before_quest' => 'AI 일자리 안전망',
        'before_text' => 'AI 좋은 거니까 규제 말자.',
        'after_label' => '이번 주',
        'after_quest' => 'AI 일자리 안전망',
        'after_text' => '일자리가 바뀌면 정부가 미리 안전망을 깔아야 한다.',
    ],
    'student_quote' => '그래서 나는 전면 규제가 아니라 재교육·안전망 쪽에 찬성한다.',
    'growth_path' => [
        ['level' => 1, 'label_ko' => '호기심', 'current' => false, 'done' => true],
        ['level' => 2, 'label_ko' => '질문', 'current' => false, 'done' => true],
        ['level' => 3, 'label_ko' => '근거', 'current' => true, 'done' => false],
        ['level' => 4, 'label_ko' => '반론', 'current' => false, 'done' => false],
        ['level' => 5, 'label_ko' => '결론', 'current' => false, 'done' => false],
    ],
    'topic_tags' => ['AI', '일자리', '정부 역할'],
    'stats' => [
        'completed_count' => 3,
        'streak_days' => 12,
        'coach_label_ko' => '근거 탐구자',
    ],
];

$outDir = $root . '/docs/exports/gist-edu/parent-reports';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outPath = $outDir . '/_layout_probe.pdf';

try {
    $pdf = eduParentReportRenderPdf($payload);
    file_put_contents($outPath, $pdf);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL render: ' . $e->getMessage() . "\n");
    exit(1);
}

$pageCount = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
$hasFont = str_contains($pdf, '/Font');
$size = strlen($pdf);
echo "PDF → {$outPath}\n";
echo "bytes={$size} pages={$pageCount} hasFont=" . ($hasFont ? '1' : '0') . "\n";

$fontDir = $root . '/public/fonts/noto';
foreach (['NotoSansKR-Regular.otf', 'NotoSansKR-Bold.otf', 'NotoSansKR-Regular.ttf', 'NotoSansKR-Bold.ttf'] as $f) {
    echo ($f . ': ' . (is_file($fontDir . '/' . $f) ? 'yes' : 'NO') . "\n");
}

if ($pageCount > 3) {
    fwrite(STDERR, "WARN: expected ≤3 pages for sample, got {$pageCount}\n");
    exit(1);
}
if ($size > 5_000_000) {
    fwrite(STDERR, "WARN: PDF too large ({$size} bytes) — check font subsetting\n");
    exit(1);
}

echo "OK edu_parent_report_pdf_layout_probe\n";
