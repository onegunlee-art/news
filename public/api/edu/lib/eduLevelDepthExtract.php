<?php
/**
 * EDU 7단계 Phase 1 — 레벨별 구조 깊이 추출 (검증 전용, 프로덕션 무관)
 *
 * 같은 글에서 coach_level 1/4/7 깊이로 경첩·축을 다르게 뽑을 수 있는지 실험.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';

const EDU_LEVEL_DEPTH_VERIFY_LEVELS = [1, 4, 7];
const EDU_LEVEL_DEPTH_PROMPT_VERSION = 'level-depth-verify-v1';

/** @return list<int> */
function eduLevelDepthVerifyLevels(): array
{
    return EDU_LEVEL_DEPTH_VERIFY_LEVELS;
}

function eduLevelDepthVerifyDir(): string
{
    return eduHingeProjectRoot() . '/docs/level_depth_verify';
}

function eduLevelDepthVerifyPath(int $newsId): string
{
    return eduLevelDepthVerifyDir() . '/' . $newsId . '.json';
}

/** @return array<string, mixed> */
function eduLevelDepthSpec(int $level): array
{
    $specs = [
        1 => [
            'level' => 1,
            'label' => '초등 (level 1)',
            'audience' => '초5~6 / 만 10~12세',
            'hinge_mode' => 'single_question',
            'axis_min' => 1,
            'axis_max' => 2,
            'scaffolding' => 'heavy',
            'thinking' => '단순 — "~일까?" 한 층, 통념 vs 본문 한 fact',
        ],
        4 => [
            'level' => 4,
            'label' => '중등 (level 4)',
            'audience' => '중2~3',
            'hinge_mode' => 'dual_sided',
            'axis_min' => 3,
            'axis_max' => 3,
            'scaffolding' => 'medium',
            'thinking' => '양면 — A vs B, 3축, 사실+질문 균형',
        ],
        7 => [
            'level' => 7,
            'label' => '고등 (level 7)',
            'audience' => '고2~3',
            'hinge_mode' => 'multi_layer',
            'axis_min' => 3,
            'axis_max' => 4,
            'scaffolding' => 'minimal',
            'thinking' => '다층 — 반론·반론의 반론, 축 간 연결, 비계 최소',
        ],
    ];

    if (!isset($specs[$level])) {
        throw new InvalidArgumentException("Unsupported verify level: {$level} (use 1, 4, or 7)");
    }

    return $specs[$level];
}

function eduLevelDepthSystemPrompt(int $level): string
{
    $spec = eduLevelDepthSpec($level);
    $axisMin = (int) $spec['axis_min'];
    $axisMax = (int) $spec['axis_max'];
    $label = (string) $spec['label'];
    $thinking = (string) $spec['thinking'];
    $scaffolding = (string) $spec['scaffolding'];

    $hingeRules = match ((int) $level) {
        1 => <<<'RULES'
경첩 규칙 (level 1 — 초등):
- hinge: **한 면**만. "A이지만 B" 금지. 학생이 처음 던질 **단순 질문** 한 문장 ("~일까?", "~면 안전할까?").
- side_a: 그 질문 틀/통념 한 줄 (쉬운 말).
- side_b: null 또는 빈 문자열 (초등은 양면 분해 안 함).
- hook_student: side_a와 같은 톤의 질문 한 문장.
- shake_prompt: 본문 fact 1개만 덧붙인 부드러운 힌트 (결론·the gist 입장 금지).
RULES,
        4 => <<<'RULES'
경첩 규칙 (level 4 — 중등):
- hinge: **양면** "A이지만/그러나 B" 한 문장. side_a·side_b 모두 본문 근거.
- side_a: 표면 통념 또는 질문 틀.
- side_b: 본문이 드러내는 더 복잡한 진실.
- hook_student: side_a에서 시작하는 질문.
- shake_prompt: side_b 쪽으로 흔드는 한 문장 + 본문 fact 1개.
RULES,
        default => <<<'RULES'
경첩 규칙 (level 7 — 고등):
- hinge: **다층** 긴장 — "A이지만 B, 더 나아가 C" 또는 nested tension. 한 문장~두 문장.
- side_a / side_b: 고등 토론 수준 분해. 추상어 최소, 행위자·사건 명확.
- hook_student: 핵심 긴장을 관통하는 질문.
- shake_prompt: 반론을 깨는 구체 fact + "그럼에도" 뉘앙스.
RULES,
    };

    $axisRules = match ((int) $level) {
        1 => <<<RULES
축(axes) 규칙 (level 1):
- {$axisMin}~{$axisMax}개만. **아주 쉬운 point** (한 줄).
- core_question: "~일까?" 형태. 답·결론 금지.
- article_fact: 본문 사실 1개 — 학생이 읽고 생각할 재료 (해석 넣지 말 것).
- scaffolding_note: 이 축에서 코치가 줄 **비계 힌트** 한 줄 (듬뿍).
- counter_angle: null (초등은 반론 층 없음).
RULES,
        4 => <<<RULES
축(axes) 규칙 (level 4):
- 정확히 {$axisMin}개. 서로 다른 측면.
- core_question: 양면을 열어주는 질문 ("~일까?" / "~해야 할까?").
- article_fact: 본문 사실 1개.
- scaffolding_note: 짧은 힌트 1줄 (중간).
- counter_angle: 예상 반론 한 줄 (답 아님).
RULES,
        default => <<<RULES
축(axes) 규칙 (level 7):
- {$axisMin}~{$axisMax}개. **축 간 연결** notes에 한 줄로 명시.
- core_question: 깊은 질문 — "~라면 ~는?" / 반론을 전제로 한 질문.
- article_fact: 본문 사실 1개 (최소 비계).
- scaffolding_note: null 또는 매우 짧음 (비계 최소).
- counter_angle: **반론의 반론** 한 줄 (메타 반박 각도, 결론 금지).
- source_section: content 소제목 (없으면 null).
RULES,
    };

    return <<<PROMPT
당신은 the gist EDU **구조 깊이 검증** 추출기입니다.
입력: news.content만. why_important·the gist 결론 금지.

이번 추출 대상: **{$label}**
사고 깊이: {$thinking}
비계(scaffolding): {$scaffolding}

{$hingeRules}

{$axisRules}

공통 금지:
- 본문 없는 사실 invent
- the gist 결론을 축/경첩에 넣지 말 것
- level {$level}보다 깊거나 얕은 다른 레벨 혼합 금지

JSON만 출력:
{
  "news_id": 0,
  "coach_level": {$level},
  "hinge": "...",
  "side_a": "...",
  "side_b": null,
  "hook_student": "...",
  "shake_prompt": "...",
  "axes": [
    {
      "point": "...",
      "core_question": "...",
      "article_fact": "...",
      "scaffolding_note": "...",
      "counter_angle": null,
      "source_section": null
    }
  ],
  "axis_count": 0,
  "thinking_summary": "이 레벨에서 학생이 따지는 깊이를 한 줄로",
  "confidence": "high|medium|low",
  "notes": "불확실·레벨 적합성 한 줄"
}
PROMPT;
}

/** @param array<string, mixed> $parsed */
function eduLevelDepthNormalize(array $parsed, int $newsId, string $title, int $level): array
{
    $spec = eduLevelDepthSpec($level);
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
            'scaffolding_note' => trim((string) ($ax['scaffolding_note'] ?? '')) ?: null,
            'counter_angle' => trim((string) ($ax['counter_angle'] ?? '')) ?: null,
            'source_section' => trim((string) ($ax['source_section'] ?? '')) ?: null,
        ];
    }

    $sideB = $parsed['side_b'] ?? null;
    if ($sideB === null || trim((string) $sideB) === '') {
        $sideB = null;
    } else {
        $sideB = trim((string) $sideB);
    }

    $confidence = strtolower(trim((string) ($parsed['confidence'] ?? 'medium')));
    if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
        $confidence = 'medium';
    }

    $hinge = trim((string) ($parsed['hinge'] ?? ''));

    return [
        'news_id' => $newsId,
        'title' => $title,
        'coach_level' => $level,
        'level_label' => (string) $spec['label'],
        'hinge' => $hinge !== '' ? $hinge : null,
        'side_a' => trim((string) ($parsed['side_a'] ?? '')),
        'side_b' => $sideB,
        'hook_student' => trim((string) ($parsed['hook_student'] ?? '')),
        'shake_prompt' => trim((string) ($parsed['shake_prompt'] ?? '')),
        'axes' => $axes,
        'axis_count' => count($axes),
        'thinking_summary' => trim((string) ($parsed['thinking_summary'] ?? '')),
        'confidence' => $confidence,
        'notes' => trim((string) ($parsed['notes'] ?? '')),
        'extracted_at' => date('c'),
        'source' => 'mysql.news.content',
        'prompt_version' => EDU_LEVEL_DEPTH_PROMPT_VERSION,
        'depth_spec' => [
            'hinge_mode' => $spec['hinge_mode'],
            'axis_min' => $spec['axis_min'],
            'axis_max' => $spec['axis_max'],
            'scaffolding' => $spec['scaffolding'],
        ],
    ];
}

/**
 * @return array{ok: bool, extraction?: array<string, mixed>, error?: string, raw?: string}
 */
function eduLevelDepthExtract($llm, int $newsId, string $title, string $content, int $level): array
{
    if (!in_array($level, EDU_LEVEL_DEPTH_VERIFY_LEVELS, true)) {
        return ['ok' => false, 'error' => "invalid_level:{$level}"];
    }

    $userMessage = <<<USER
news_id: {$newsId}
coach_level: {$level}
제목: {$title}

--- content (추출 대상) ---
{$content}
USER;

    $response = $llm->chat(eduLevelDepthSystemPrompt($level), [
        ['role' => 'user', 'content' => $userMessage],
    ], 3072, 0.15);

    if (!empty($response['error'])) {
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
        return ['ok' => false, 'error' => 'json_parse_failed', 'raw' => $raw];
    }

    return [
        'ok' => true,
        'extraction' => eduLevelDepthNormalize($parsed, $newsId, $title, $level),
        'raw' => $raw,
    ];
}

/** @param list<array<string, mixed>> $byLevel keyed by level in outer array */
function eduLevelDepthCompareSummary(array $byLevel): array
{
    $rows = [];
    foreach (EDU_LEVEL_DEPTH_VERIFY_LEVELS as $level) {
        $ex = $byLevel[$level] ?? null;
        if (!is_array($ex)) {
            continue;
        }
        $rows[] = [
            'level' => $level,
            'label' => (string) ($ex['level_label'] ?? ''),
            'hinge_len' => mb_strlen((string) ($ex['hinge'] ?? '')),
            'has_side_b' => ($ex['side_b'] ?? null) !== null && trim((string) $ex['side_b']) !== '',
            'axis_count' => (int) ($ex['axis_count'] ?? count($ex['axes'] ?? [])),
            'counter_axes' => count(array_filter(
                $ex['axes'] ?? [],
                static fn ($ax) => is_array($ax) && !empty($ax['counter_angle'])
            )),
            'thinking_summary' => (string) ($ex['thinking_summary'] ?? ''),
        ];
    }

    return $rows;
}

function eduLevelDepthEnsureDirs(): void
{
    $dir = eduLevelDepthVerifyDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/** @param array<string, mixed> $payload */
function eduLevelDepthSaveVerifyResult(array $payload): string
{
    eduLevelDepthEnsureDirs();
    $newsId = (int) ($payload['news_id'] ?? 0);
    $path = eduLevelDepthVerifyPath($newsId);
    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    return $path;
}

/** @param array<string, mixed> $payload */
function eduLevelDepthVerifyMarkdown(array $payload): string
{
    $newsId = (int) ($payload['news_id'] ?? 0);
    $title = (string) ($payload['title'] ?? '');
    $md = "# EDU Level Depth Verify — {$newsId} {$title}\n\n";
    $md .= '> Phase 1 검증 · ' . date('Y-m-d H:i:s') . ' · prompt ' . EDU_LEVEL_DEPTH_PROMPT_VERSION . "\n\n";
    $md .= "## 핵심 질문\n\n";
    $md .= "같은 글에서 level 1 / 4 / 7 구조가 **진짜 다른 깊이**로 나오는가?\n\n";

    $md .= "## 요약 비교\n\n";
    $md .= "| level | label | hinge 글자 | side_b | axes | counter_angle 축 | thinking_summary |\n";
    $md .= "|-------|-------|------------|--------|------|------------------|------------------|\n";
    foreach ($payload['compare'] ?? [] as $row) {
        $md .= '| ' . ($row['level'] ?? '') . ' | '
            . ($row['label'] ?? '') . ' | '
            . ($row['hinge_len'] ?? '') . ' | '
            . (($row['has_side_b'] ?? false) ? 'Y' : 'N') . ' | '
            . ($row['axis_count'] ?? '') . ' | '
            . ($row['counter_axes'] ?? '') . ' | '
            . str_replace('|', '\\|', (string) ($row['thinking_summary'] ?? '')) . " |\n";
    }

    $md .= "\n## 사람 검수 (이원근)\n\n";
    $md .= "- [ ] level 1이 초등이 따질 만큼 **단순**한가?\n";
    $md .= "- [ ] level 7이 **고등 수준으로 깊은**가?\n";
    $md .= "- [ ] level 4가 **중간**인가?\n";
    $md .= "- [ ] 셋이 **서로 비슷하지 않은**가? (흐릿하면 재설계)\n\n";

    foreach (EDU_LEVEL_DEPTH_VERIFY_LEVELS as $level) {
        $ex = $payload['levels'][(string) $level] ?? $payload['levels'][$level] ?? null;
        if (!is_array($ex)) {
            continue;
        }
        $md .= "### Level {$level} — " . ($ex['level_label'] ?? '') . "\n\n";
        $md .= '**hinge:** ' . ($ex['hinge'] ?? '') . "\n\n";
        if (!empty($ex['side_a'])) {
            $md .= '- **side_a:** ' . $ex['side_a'] . "\n";
        }
        if (!empty($ex['side_b'])) {
            $md .= '- **side_b:** ' . $ex['side_b'] . "\n";
        }
        if (!empty($ex['hook_student'])) {
            $md .= '- **hook:** ' . $ex['hook_student'] . "\n";
        }
        if (!empty($ex['thinking_summary'])) {
            $md .= '- **thinking:** ' . $ex['thinking_summary'] . "\n";
        }
        $md .= "\n| # | point | core_question | fact | scaffold | counter |\n";
        $md .= "|---|-------|---------------|------|----------|--------|\n";
        foreach ($ex['axes'] ?? [] as $i => $ax) {
            $md .= '| ' . ($i + 1) . ' | '
                . str_replace('|', '\\|', (string) ($ax['point'] ?? '')) . ' | '
                . str_replace('|', '\\|', (string) ($ax['core_question'] ?? '')) . ' | '
                . str_replace('|', '\\|', (string) ($ax['article_fact'] ?? '')) . ' | '
                . str_replace('|', '\\|', (string) ($ax['scaffolding_note'] ?? '')) . ' | '
                . str_replace('|', '\\|', (string) ($ax['counter_angle'] ?? '')) . " |\n";
        }
        $md .= "\n";
    }

    return $md;
}
