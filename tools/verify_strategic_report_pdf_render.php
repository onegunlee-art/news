<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/backend/autoload.php';

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use App\Services\StrategicReportDocumentService;
use App\Services\StrategicReportPdfService;

$fixtureScqa = [
    'core_question' => '테스트 질문',
    'executive_summary' => '요약',
    'situation' => ['narrative' => '상황'],
    'complication' => [
        'trigger' => '계기',
        'narrative_collisions' => [[
            'label' => '에너지 프레임 충돌',
            'actor_a' => '미국',
            'view_a' => '관점 A 내용',
            'actor_b' => '이ran',
            'view_b' => '관점 B 내용',
            'collision' => '충돌 해설 텍스트',
        ]],
        'perspectives' => [[
            'viewpoint' => '서구 언론 관점',
            'quote' => '인용문 예시',
        ]],
    ],
    'question' => '질문',
    'answer' => [
        'implication' => '시사점 본문',
        'why_it_matters_chain' => ['단계1', '단계2'],
        'scenarios' => [[
            'type' => 'base',
            'probability' => 60,
            'outcome' => '예상 결과 한글',
            'prediction_signal' => '관측 신호',
        ]],
        'action_matrix' => [
            'watch' => ['변수 A'],
            'consider' => ['옵션 B'],
            'act' => ['대응 C'],
        ],
    ],
];

$report = [
    'report_week' => '2026-W21',
    'period_start' => '2026-05-18',
    'period_end' => '2026-05-24',
    'scqa_raw_json' => json_encode($fixtureScqa, JSON_UNESCAPED_UNICODE),
    'meta_json' => json_encode([
        'gist_anchor_count' => 0,
        'matched_external_count' => 40,
        'article_total' => 40,
        'period_fallback' => 'week_articles_only',
        'confidence' => 'medium',
    ], JSON_UNESCAPED_UNICODE),
];

$doc = new StrategicReportDocumentService();
$html = $doc->renderHtml($report);

$checks = [
    '기본 시나리오' => str_contains($html, '기본 시나리오'),
    '60%' => str_contains($html, '60%'),
    '예상 결과 한글' => str_contains($html, '예상 결과 한글'),
    '관측 신호' => str_contains($html, '관측 신호'),
    '서구 언론 관점' => str_contains($html, '서구 언론 관점'),
    '에너지 프레임 충돌' => str_contains($html, '에너지 프레임 충돌'),
    '충돌 해설 텍스트' => str_contains($html, '충돌 해설 텍스트'),
    '시사점 본문' => str_contains($html, '시사점 본문'),
    '변수 A' => str_contains($html, '변수 A'),
    '외부(폴백)' => str_contains($html, '외부(폴백)'),
    'scenario outcome not italic' => !preg_match('/font-style:\s*italic[^>]*>[^<]*예상 결과/s', $html),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'FAILED checks: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    echo "HTML render checks OK (dompdf not installed, skipping PDF binary)\n";
    echo "W21 재생성(서버): php cron/generate_strategic_report.php 2026-W21\n";
    exit(0);
}

ini_set('memory_limit', '512M');
try {
    $pdf = new StrategicReportPdfService($doc);
    $binary = $pdf->generateFromReport($report);
    if (strlen($binary) < 1000 || !str_starts_with($binary, '%PDF')) {
        throw new RuntimeException('PDF too small or invalid header');
    }
    echo "All PDF render checks passed (" . strlen($binary) . " bytes)\n";
} catch (Throwable $e) {
    echo "HTML render checks OK (PDF skipped: " . $e->getMessage() . ")\n";
}
echo "W21 재생성(서버): php cron/generate_strategic_report.php 2026-W21\n";
