<?php
/**
 * P2 2단계 — news.content → 따지기 축(axes) 추출 (검증용, MySQL only)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';

function eduAxisExtractionsDir(): string
{
    return eduHingeProjectRoot() . '/docs/axis_extractions';
}

function eduAxisExtractionPath(int $newsId): string
{
    return eduAxisExtractionsDir() . '/' . $newsId . '.json';
}

function eduAxisSystemPrompt(): string
{
    return <<<'PROMPT'
당신은 the gist 기사 content(구조도)에서 "따지기 단계(축)"를 추출합니다.
입력은 news.content만입니다. why_important·judgement 금지.

경첩(hinge) 1개 아래에, 본문 소제목/섹션 구조에서 학생이 **순서대로 따져볼 포인트** N개(보통 2~4)를 뽑으세요.

각 축 규칙:
- point: 따지는 측면 한 줄 (주어·행위자 명확 — "약해진다" 단독 금지, 누가/무엇이)
- core_question: 그 축에서 학생에게 던질 **질문만** (답·결론·the gist 입장 금지)
- article_fact: 그 축을 뒷받침하는 본문 **사실 1개** (숫자·고유명·사건명 — 학생이 해석할 재료, 해석은 넣지 말 것)
- source_section: content에서 대응 소제목/섹션 제목 (없으면 null)

금지:
- the gist 결론을 축에 넣지 말 것 ("그래서 방어가 답" 등)
- core_question에 답·유도("~가 중요하지?" 금지, "~일까?" 형태)
- 본문 없는 사실 invent
- 축끼리 같은 포인트 중복

JSON만 출력:
{
  "news_id": 0,
  "hinge": "기존 경첩 한 문장 또는 content에서 A이지만 B",
  "axes": [
    {"point": "...", "core_question": "...", "article_fact": "...", "source_section": "..."}
  ],
  "confidence": "high|medium|low",
  "notes": "불확실·겹침 한 줄"
}
PROMPT;
}

/** @param array<string, mixed> $parsed */
function eduAxisNormalize(array $parsed, int $newsId, string $title, ?string $hingeFromFile = null): array
{
    $axes = [];
    foreach ($parsed['axes'] ?? [] as $ax) {
        if (!is_array($ax)) {
            continue;
        }
        $point = trim((string) ($ax['point'] ?? ''));
        if ($point === '') {
            continue;
        }
        $axes[] = [
            'point' => $point,
            'core_question' => trim((string) ($ax['core_question'] ?? '')),
            'article_fact' => trim((string) ($ax['article_fact'] ?? '')),
            'source_section' => trim((string) ($ax['source_section'] ?? '')) ?: null,
        ];
    }

    $hinge = trim((string) ($parsed['hinge'] ?? ''));
    if ($hinge === '' && $hingeFromFile !== null) {
        $hinge = $hingeFromFile;
    }

    $confidence = strtolower(trim((string) ($parsed['confidence'] ?? 'medium')));
    if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
        $confidence = 'medium';
    }

    $needsReview = $axes === [] || $confidence === 'low' || $hinge === '';

    return [
        'news_id' => $newsId,
        'title' => $title,
        'hinge' => $hinge !== '' ? $hinge : null,
        'axes' => $axes,
        'confidence' => $confidence,
        'notes' => trim((string) ($parsed['notes'] ?? '')),
        'needs_review' => $needsReview,
        'extracted_at' => date('c'),
        'source' => 'mysql.news.content',
        'prompt_version' => 'p2-axes-v1',
    ];
}

/** @return array{ok: bool, extraction?: array, error?: string, raw?: string} */
function eduAxisExtractFromContent($llm, int $newsId, string $title, string $content, ?string $hingeFromFile = null): array
{
    $userMessage = "news_id: {$newsId}\n제목: {$title}\n\ncontent:\n{$content}";

    $response = $llm->chat(eduAxisSystemPrompt(), [
        ['role' => 'user', 'content' => $userMessage],
    ], 2048, 0.2);

    if (!empty($response['error'])) {
        return ['ok' => false, 'error' => (string) ($response['message'] ?? $response['error'])];
    }

    $raw = (string) ($response['content'] ?? '');
    $parsed = null;
    if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
        $parsed = json_decode($m[0], true);
    }

    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'json_parse_failed', 'raw' => $raw];
    }

    return [
        'ok' => true,
        'extraction' => eduAxisNormalize($parsed, $newsId, $title, $hingeFromFile),
        'raw' => $raw,
    ];
}

function eduAxisEnsureDirs(): void
{
    $dir = eduAxisExtractionsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function eduAxisSaveExtraction(array $extraction): string
{
    eduAxisEnsureDirs();
    $newsId = (int) ($extraction['news_id'] ?? 0);
    $path = eduAxisExtractionPath($newsId);
    file_put_contents($path, json_encode($extraction, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    return $path;
}

function eduAxisLoadExtraction(int $newsId): ?array
{
    $path = eduAxisExtractionPath($newsId);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

function eduAxisLoadHingeForNews(int $newsId): ?string
{
    $hinge = eduHingeLoadExtraction($newsId);

    return is_array($hinge) ? trim((string) ($hinge['hinge'] ?? '')) : null;
}

/**
 * 630 수동 3축 vs 자동 추출 대조
 *
 * @param list<array<string, mixed>> $autoAxes
 * @return list<array<string, string>>
 */
function eduAxisCompare630Manual(array $autoAxes): array
{
    require_once eduHingeProjectRoot() . '/tools/edu_nuclear_axis_quest_fixture.php';

    $manual = eduNuke630Axes();
    $rows = [];

    foreach ($manual as $m) {
        $label = (string) ($m['axis_label'] ?? '');
        $id = (string) ($m['axis_id'] ?? '');
        $best = null;
        $bestScore = 0;
        foreach ($autoAxes as $ax) {
            $point = (string) ($ax['point'] ?? '');
            $score = eduAxisMatchScore($label . ' ' . ($m['thesis'] ?? ''), $point);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $ax;
            }
        }
        $verdict = $bestScore >= 2 ? 'match' : ($bestScore >= 1 ? 'partial' : 'miss');
        $rows[] = [
            'manual_id' => $id,
            'manual_label' => $label,
            'auto_point' => $best ? (string) ($best['point'] ?? '') : '(없음)',
            'verdict' => $verdict,
        ];
    }

    return $rows;
}

function eduAxisMatchScore(string $manual, string $auto): int
{
    $keywords = ['핵', '드론', '미사일', '재래식', '약속', '규범', '규칙', '방어', '방공', '기지', '억지', '투자'];
    $score = 0;
    foreach ($keywords as $kw) {
        if (str_contains($manual, $kw) && str_contains($auto, $kw)) {
            $score++;
        }
    }

    return min($score, 3);
}
