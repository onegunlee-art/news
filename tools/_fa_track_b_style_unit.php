<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/agents/autoload.php';

$ref = new ReflectionClass(Agents\Agents\AnalysisAgent::class);
$agent = $ref->newInstanceWithoutConstructor();

$normBullets = $ref->getMethod('normalizeTrackBContentSummaryBullets');
$normBullets->setAccessible(true);
$normText = $ref->getMethod('normalizeTrackBTextField');
$normText->setAccessible(true);
$build = $ref->getMethod('buildContentSummaryFromSections');
$build->setAccessible(true);
$splitB = $ref->getMethod('splitIntoSentencesForTrackB');
$splitB->setAccessible(true);

$results = [];

$boda = $splitB->invoke($agent, '유엔은 위기의 사후 대응보다 위기를 낳는 조건을 줄이는 데 초점을 옮겨야 하며, 효과적인 유엔은 다른 기구가 할 수 없는 일에 집중해야 한다.');
$results['boda_single_sentence'] = count($boda) === 1;
$deda = $splitB->invoke($agent, '개발 분야에서는 지속가능발전목표 이행이 크게 뒤처진 데다 부채 증가와 원조 축소가 겹치며 국가의 기본 서비스 제공 능력과 사회적 결속이 약화되고 있다.');
$results['deda_single_sentence'] = count($deda) === 1;
$period = $splitB->invoke($agent, '첫 문장이다. 둘째 문장이다.');
$results['period_splits_two'] = count($period) === 2;

$input = "1. TEST (TEST)\n  · indented bullet\n· normal bullet";
$fixed = $normBullets->invoke($agent, $input);
$results['bullet_indent_fixed'] = !preg_match('/^[ \t]+·/m', $fixed);

$variantInput = "1. TEST (TEST)\n  • bullet char U+2022\n・ katakana middle dot\n- hyphen bullet";
$variantFixed = $normBullets->invoke($agent, $variantInput);
$results['bullet_variants_to_u00b7'] = !preg_match('/^[ \t]*[•・\-]/m', $variantFixed)
    && preg_match('/^· /m', $variantFixed)
    && mb_substr_count($variantFixed, '·') >= 3;

$raw = "  · 멕시코는 정보 공유를 늘리고 있다.\n  · 추가 내용이다.";
$clean = $normText->invoke($agent, $raw);
$results['text_field_clean'] = str_starts_with($clean, '멕시코');

$rawVariant = "  • 첫 문장이다.\n  ・ 둘째 문장이다.";
$cleanVariant = $normText->invoke($agent, $rawVariant);
$results['text_field_strips_bullet_variants'] = str_starts_with($cleanVariant, '첫 문장')
    && str_contains($cleanVariant, '둘째 문장')
    && !preg_match('/[•・]/u', $cleanVariant);

$data = [
    'news_title' => '테스트 제목',
    'original_title' => 'Test Title',
    'introduction_summary' => '서론 요약 문장입니다.',
    'section_analysis' => [[
        'section_title' => 'SECTION ONE',
        'section_title_ko' => '섹션 하나',
        'summary' => '첫 문장입니다. 둘째 문장입니다.',
        'key_insight' => '핵심 인사이트입니다.',
    ]],
    'geopolitical_implication' => '함의 문장입니다.',
];
$cs = $build->invoke($agent, $data, false);
$csB = $build->invoke($agent, $data, true);
$results['nonfa_bullets_no_leading_space'] = !preg_match('/^[ \t]+·/m', $cs);
$results['nonfa_trackB_flag_off_same_as_a'] = $cs === $csB;
$results['nonfa_bullet_prefix'] = (bool) preg_match('/^· /m', $cs);

$diagPath = dirname(__DIR__) . '/docs/fa_track_b_section_diagnose.json';
if (is_file($diagPath)) {
    $diag = json_decode((string) file_get_contents($diagPath), true);
    $csDiag = (string) ($diag['content_summary'] ?? '');
    $forbidden = ['이 글은', '필자는', '저자는', '이 섹션의 핵심 쟁점', '이 글의 함의는'];
    $hits = [];
    foreach ($forbidden as $f) {
        if (str_contains($csDiag, $f)) {
            $hits[] = $f;
        }
    }
    $results['b_forbidden_intro_hits'] = $hits === [] ? true : $hits;
    $results['b_leading_indent_bullets'] = !preg_match('/^[ \t]+·/m', $csDiag);
    $results['b_author_judgment_kept'] = (bool) preg_match('/(라는 평가|로 볼 수|시사한다|추정|한계)/u', $csDiag);
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
$allPass = true;
foreach ($results as $k => $v) {
    if ($v !== true) {
        $allPass = false;
    }
}
exit($allPass ? 0 : 1);
