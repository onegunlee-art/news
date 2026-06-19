<?php
/**
 * GIST EDU P2-A1 — 경첩(hinge) 추출 (MySQL news.content only)
 */
declare(strict_types=1);

function eduHingeProjectRoot(): string
{
    return dirname(__DIR__, 4);
}

function eduHingeExtractionsDir(): string
{
    return eduHingeProjectRoot() . '/docs/hinge_extractions';
}

function eduHingeReviewsFile(): string
{
    return eduHingeProjectRoot() . '/docs/hinge_reviews/reviews.jsonl';
}

function eduHingeExtractionPath(int $newsId): string
{
    return eduHingeExtractionsDir() . '/' . $newsId . '.json';
}

function eduHingeStripPlain(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
}

function eduHingeSystemPrompt(): string
{
    return <<<'PROMPT'
당신은 the gist 기사의 "경첩"(핵심 긴장) 추출기입니다.
입력은 news.content(원문 AI 분석)만입니다. why_important 등 다른 필드는 없습니다.

다음 JSON만 출력하세요. **본문에 근거가 있는 내용만** — 근거 없으면 hinge: null, confidence: "low".

규칙:
1. hinge: 이 글이 흔드는 긴장을 **한 문장**으로. 반드시 "A이지만/그러나 B" 또는 "A이면서 동시에 B" 형태.
2. side_a: 사람들이 당연하게 여기는 것 — 통념 / 표면 현상 / 단순 서사 / **질문 프레임(글이 던지는 질문의 틀 자체, 예: "~가 좋은가 나쁜가")** 중 하나 (넓게). 본문 근거 필수. 글이 찬반·평가 질문으로 시작하면 side_a는 그 **질문 틀**을 그대로 쓸 것(서사로 바꾸지 말 것).
3. side_b: 본문이 따져서 드러내는 더 복잡하거나 반대되는 진실. 본문 근거 필수.
4. hook_student: 14세용 한 문장, side_a에서 시작 (질문형 OK).
5. shake_prompt: 학생이 A만 말했을 때 코치가 B쪽으로 흔드는 한 문장. **반드시 본문의 구체적 사fact 하나** 포함 (숫자·사건명·고유명사 등).
6. article_form: content **하단** "이 글은 ~에 게재된" 출처에서 FA 또는 Economist 읽기. 없으면 "unknown". **섹션 수로 추정 금지**.
7. 금지: 본문에 없는 사실 invent, why_important 추측, 주어 없는 추상문.

JSON:
{
  "news_id": 0,
  "hinge": "A이지만 B",
  "side_a": "...",
  "side_b": "...",
  "hook_student": "...",
  "shake_prompt": "...",
  "article_form": "FA|economist|unknown",
  "confidence": "high|medium|low",
  "notes": "불확실한 부분 한 줄"
}
PROMPT;
}

/** @return array{news_id: int, title: string, content: string}|null */
function eduHingeLoadMysqlContent(PDO $pdo, int $newsId): ?array
{
    require_once __DIR__ . '/eduQuestArticleSnapshot.php';

    $row = eduSnapshotLoadNewsRow($pdo, $newsId);
    if ($row === null) {
        return null;
    }

    $content = eduHingeStripPlain((string) ($row['content'] ?? ''));
    if ($content === '') {
        return null;
    }

    return [
        'news_id' => $newsId,
        'title' => (string) ($row['title'] ?? ''),
        'content' => $content,
    ];
}

/** @param array<string, mixed> $parsed */
function eduHingeComputeNeedsReview(array $parsed): bool
{
    $hinge = $parsed['hinge'] ?? null;
    if ($hinge === null || trim((string) $hinge) === '') {
        return true;
    }

    $confidence = strtolower(trim((string) ($parsed['confidence'] ?? '')));
    if ($confidence === '' || $confidence === 'low') {
        return true;
    }

    return false;
}

/** @param array<string, mixed> $parsed */
function eduHingeNormalize(array $parsed, int $newsId, string $title): array
{
    $confidence = strtolower(trim((string) ($parsed['confidence'] ?? '')));
    if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
        $confidence = '';
    }

    $out = [
        'news_id' => $newsId,
        'title' => $title,
        'hinge' => isset($parsed['hinge']) && $parsed['hinge'] !== null
            ? trim((string) $parsed['hinge'])
            : null,
        'side_a' => trim((string) ($parsed['side_a'] ?? '')),
        'side_b' => trim((string) ($parsed['side_b'] ?? '')),
        'hook_student' => trim((string) ($parsed['hook_student'] ?? '')),
        'shake_prompt' => trim((string) ($parsed['shake_prompt'] ?? '')),
        'article_form' => trim((string) ($parsed['article_form'] ?? 'unknown')),
        'confidence' => $confidence !== '' ? $confidence : null,
        'notes' => trim((string) ($parsed['notes'] ?? '')),
        'extracted_at' => date('c'),
        'source' => 'mysql.news.content',
        'prompt_version' => 'p2-h-side_a_question_frame_v1',
    ];

    if ($out['hinge'] === '') {
        $out['hinge'] = null;
    }

    $out['needs_review'] = eduHingeComputeNeedsReview($out);

    return $out;
}

/**
 * @return array{ok: bool, extraction?: array<string, mixed>, error?: string, raw?: string}
 */
function eduHingeExtractFromContent($llm, int $newsId, string $title, string $content): array
{
    $userMessage = <<<USER
news_id: {$newsId}
제목: {$title}

--- content (추출 대상, why_important 없음) ---
{$content}
USER;

    $response = $llm->chat(eduHingeSystemPrompt(), [
        ['role' => 'user', 'content' => $userMessage],
    ], 2048, 0.1);

    if (isset($response['error'])) {
        return [
            'ok' => false,
            'error' => (string) ($response['message'] ?? $response['error']),
        ];
    }

    $raw = (string) ($response['content'] ?? '');
    $parsed = null;
    if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
        $parsed = json_decode($m[0], true);
    }

    if (!is_array($parsed)) {
        return [
            'ok' => false,
            'error' => 'LLM JSON parse failed',
            'raw' => $raw,
        ];
    }

    return [
        'ok' => true,
        'extraction' => eduHingeNormalize($parsed, $newsId, $title),
        'raw' => $raw,
    ];
}

function eduHingeEnsureDirs(): void
{
    foreach ([eduHingeExtractionsDir(), dirname(eduHingeReviewsFile())] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/** @param array<string, mixed> $extraction */
function eduHingeSaveExtraction(array $extraction): string
{
    eduHingeEnsureDirs();
    $newsId = (int) ($extraction['news_id'] ?? 0);
    $path = eduHingeExtractionPath($newsId);
    file_put_contents($path, json_encode($extraction, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    return $path;
}

/** @return array<string, mixed>|null */
function eduHingeLoadExtraction(int $newsId): ?array
{
    $path = eduHingeExtractionPath($newsId);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** @return list<array<string, mixed>> */
function eduHingeLoadReviews(): array
{
    $file = eduHingeReviewsFile();
    if (!is_file($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }
    return $out;
}

/** @param array<string, mixed> $review */
function eduHingeAppendReview(array $review): string
{
    eduHingeEnsureDirs();
    $file = eduHingeReviewsFile();
    $line = json_encode($review, JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    return $file;
}

/** @param list<string> $fields */
function eduHingePickFields(array $source, array $fields): array
{
    $out = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $source)) {
            $out[$f] = $source[$f];
        }
    }
    return $out;
}

/** @return list<string> */
function eduHingeHingeFieldNames(): array
{
    return ['hinge', 'side_a', 'side_b', 'hook_student', 'shake_prompt', 'article_form', 'notes'];
}

/** @param array<string, mixed> $before @param array<string, mixed> $after */
function eduHingeDiffFields(array $before, array $after): array
{
    $edited = [];
    foreach (eduHingeHingeFieldNames() as $field) {
        $b = $before[$field] ?? null;
        $a = $after[$field] ?? null;
        if ((string) $b !== (string) $a) {
            $edited[] = $field;
        }
    }
    return $edited;
}
