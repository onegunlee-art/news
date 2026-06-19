<?php
/**
 * P2-H — news.content 경첩(A지만 B) LLM 추출 + 사람 정답지 대조 (DB 저장 없음)
 *
 * Usage:
 *   php tools/edu_gist_hinge_extract_test.php
 *   php tools/edu_gist_hinge_extract_test.php 631 555 618
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

$defaultIds = [631, 555, 618, 546, 570, 621];
$numericArgs = array_values(array_filter($argv ?? [], static fn ($a) => is_numeric($a)));
$ids = $numericArgs !== [] ? array_map('intval', $numericArgs) : $defaultIds;

/** @var array<int, array{label: string, side_a: string, side_b: string}> */
$humanKeys = [
    631 => [
        'label' => '631',
        'side_a' => '중국은 장기계획에 능하다',
        'side_b' => '권위주의가 단기과시를 부추겨 장기목표를 그르침',
    ],
    555 => [
        'label' => '555',
        'side_a' => '첨단무기·AI면 전쟁을 빨리 끝낸다',
        'side_b' => '수단은 강해졌는데 정치적 출구는 더 막힘',
    ],
    618 => [
        'label' => '618',
        'side_a' => '미국 질서는 끝났으니 손 떼라',
        'side_b' => '물러나면 전쟁·핵확산·공급망 충격이라는 더 큰 비용',
    ],
    546 => [
        'label' => '546',
        'side_a' => '일본 재무장이 좋냐 나쁘냐',
        'side_b' => '이미 돌이킬 수 없고, 관건은 미국이 자산으로 쓰냐 비용으로 보냐',
    ],
    570 => [
        'label' => 'D(570)',
        'side_a' => '물가 반등했으니 중국 경제 회복',
        'side_b' => '반등이 좁아 임금·소비로 안 번지고 정책의지도 약함',
    ],
    621 => [
        'label' => 'E(621)',
        'side_a' => '중국이 불균형의 범인',
        'side_b' => '미국 재정적자도 원인이고 중국 흑자는 부동산 실책의 부산물',
    ],
];

function stripPlain(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
}

function loadContentOnly(int $newsId, $supabase): ?array
{
    $rows = $supabase->select('judgement_records', 'news_id=eq.' . $newsId . '&order=created_at.desc', 1) ?? [];
    if ($rows === []) {
        return null;
    }
    $human = is_string($rows[0]['human_output'] ?? null)
        ? json_decode($rows[0]['human_output'], true)
        : ($rows[0]['human_output'] ?? []);

    $content = stripPlain((string) ($human['content'] ?? ''));
    if ($content === '') {
        return null;
    }

    return [
        'title' => (string) ($human['title'] ?? ''),
        'content' => $content,
    ];
}

$systemPrompt = eduHingeSystemPrompt();

$sb = eduSupabase();
$llm = eduLlm();
$results = [];

$md = "# P2-H 경첩 LLM 검증 결과\n\n";
$md .= "> 입력: `human_output.content` only · why_important **금지** · temperature 0.1\n";
$md .= '> 실행: ' . date('Y-m-d H:i:s') . "\n\n";
$md .= "## 대상 ID\n\n";
$md .= implode(', ', $ids) . " (D=570 디플레이션, E=621 글로벌불균형·환율)\n\n";
$md .= "**375** judgement 없음 → E는 **621** 사용.\n\n---\n\n";

foreach ($ids as $nid) {
    $article = loadContentOnly($nid, $sb);
    $human = $humanKeys[$nid] ?? null;

    echo "Extracting news_id={$nid}...\n";

    $md .= "## [{$nid}]";
    if ($article !== null) {
        $md .= ' ' . $article['title'];
    }
    $md .= "\n\n";

    if ($human !== null) {
        $md .= "### 사람 정답지\n\n";
        $md .= '- **side_a:** ' . $human['side_a'] . "\n";
        $md .= '- **side_b:** ' . $human['side_b'] . "\n\n";
    }

    if ($article === null) {
        $md .= "content 없음 (skip)\n\n---\n\n";
        continue;
    }

    $userMessage = <<<USER
news_id: {$nid}
제목: {$article['title']}

--- content (추출 대상, why_important 없음) ---
{$article['content']}
USER;

    $response = $llm->chat($systemPrompt, [
        ['role' => 'user', 'content' => $userMessage],
    ], 2048, 0.1);

    if (isset($response['error'])) {
        $md .= 'LLM error: ' . ($response['message'] ?? $response['error']) . "\n\n---\n\n";
        continue;
    }

    $raw = (string) ($response['content'] ?? '');
    $parsed = null;
    if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
        $parsed = json_decode($m[0], true);
    }
    $results[(string) $nid] = $parsed;

    if (!is_array($parsed)) {
        $md .= "### LLM raw (파싱 실패)\n\n```\n{$raw}\n```\n\n";
    } else {
        $md .= "### LLM 추출\n\n";
        $md .= '| 필드 | 값 |' . "\n|------|-----|\n";
        foreach (['hinge', 'side_a', 'side_b', 'hook_student', 'shake_prompt', 'article_form', 'confidence', 'notes'] as $k) {
            $v = (string) ($parsed[$k] ?? '');
            $v = str_replace('|', '\\|', $v);
            $md .= "| {$k} | {$v} |\n";
        }
        $md .= "\n```json\n" . json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```\n\n";
    }

    $md .= "### 대조 판정 (사람 검수)\n\n";
    $md .= "- [ ] 경첩: ○ 일치 / △ 비슷하나 side 어긋남 / ✗ 다른 긴장·헛것\n";
    $md .= "- [ ] side_a/side_b 분해: ○ / △ / ✗\n";
    $md .= "- [ ] hook/shake 파생 (보너스): ○ / △ / ✗\n\n---\n\n";
}

$md .= "## 총평\n\n";
$md .= "- 경첩 ○: _/6\n";
$md .= "- **판정:** (5/6+ ○ → 통과 / 4/6 → 조건부 / 3/6↓ → 재설계)\n";

$outMd = $root . '/docs/P2_HINGE_VERIFY_RESULT.md';
$outJson = $root . '/docs/P2_HINGE_VERIFY_RESULT.json';
file_put_contents($outMd, $md);
file_put_contents($outJson, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Wrote {$outMd}\n";
echo "Wrote {$outJson}\n";
