<?php
/**
 * EDU 7단계 Phase 1 — 레벨별 구조 깊이 추출 (검증 전용, 프로덕션 무관)
 *
 * 같은 글에서 coach_level 1/4/7 깊이로 경첩·축을 다르게 뽑을 수 있는지 실험.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';

const EDU_LEVEL_DEPTH_VERIFY_LEVELS = [1, 2, 3, 4, 5, 6, 7];
const EDU_LEVEL_DEPTH_VERIFY_LEVELS_PHASE1 = [1, 4, 7];
const EDU_LEVEL_DEPTH_PROMPT_VERSION = 'level-depth-verify-v2-phase3';

/** @param list<int>|null $override */
function eduLevelDepthVerifyLevels(?array $override = null): array
{
    if ($override !== null && $override !== []) {
        return array_values(array_unique(array_map('intval', $override)));
    }

    return EDU_LEVEL_DEPTH_VERIFY_LEVELS;
}

/** @return list<int> */
function eduLevelDepthParseLevelsArg(string $arg): array
{
    $parts = array_map('trim', explode(',', $arg));
    $levels = [];
    foreach ($parts as $p) {
        if ($p !== '' && is_numeric($p)) {
            $levels[] = (int) $p;
        }
    }

    return $levels;
}

function eduLevelDepthScaffoldingScore(string $scaffolding): int
{
    return match ($scaffolding) {
        'heavy' => 4,
        'heavyish' => 3,
        'medium_high' => 3,
        'medium' => 2,
        'medium_low' => 1,
        'light' => 1,
        'minimal' => 0,
        default => 2,
    };
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
            'label' => 'L1 초등',
            'audience' => '초5~6 / 만 10~12세',
            'hinge_mode' => 'single_question',
            'axis_min' => 1,
            'axis_max' => 2,
            'scaffolding' => 'heavy',
            'thinking' => '단순 — "~일까?" 한 층, 통념 vs 본문 한 fact',
        ],
        2 => [
            'level' => 2,
            'label' => 'L2 초등+',
            'audience' => '초6 / 만 11~12세',
            'hinge_mode' => 'dual_intro',
            'axis_min' => 2,
            'axis_max' => 2,
            'scaffolding' => 'heavyish',
            'thinking' => '양면 입문 — "A처럼 보이지만 B" 짧게, 2축, 비계 많음 (L1보다 살짝 깊게)',
        ],
        3 => [
            'level' => 3,
            'label' => 'L3 중등-',
            'audience' => '중1~2',
            'hinge_mode' => 'dual_sided_soft',
            'axis_min' => 2,
            'axis_max' => 3,
            'scaffolding' => 'medium_high',
            'thinking' => '양면 — 2~3축, 비계 중상 (L4 바로 아래, L2보다 깊음)',
        ],
        4 => [
            'level' => 4,
            'label' => 'L4 중등',
            'audience' => '중2~3',
            'hinge_mode' => 'dual_sided',
            'axis_min' => 3,
            'axis_max' => 3,
            'scaffolding' => 'medium',
            'thinking' => '양면 — A vs B, 3축, 사실+질문 균형',
        ],
        5 => [
            'level' => 5,
            'label' => 'L5 중등+',
            'audience' => '중3 / 고1',
            'hinge_mode' => 'evidence_triple',
            'axis_min' => 3,
            'axis_max' => 3,
            'scaffolding' => 'medium_low',
            'thinking' => '3축+근거 강조 — article_fact 구체적, 비계 중하 (L4 위)',
        ],
        6 => [
            'level' => 6,
            'label' => 'L6 고등-',
            'audience' => '고1~2',
            'hinge_mode' => 'multi_layer_intro',
            'axis_min' => 3,
            'axis_max' => 4,
            'scaffolding' => 'light',
            'thinking' => '다층 입문 — 3~4축, 반론 각도 시작, 비계 적음 (L7 바로 아래)',
        ],
        7 => [
            'level' => 7,
            'label' => 'L7 고등',
            'audience' => '고2~3',
            'hinge_mode' => 'multi_layer',
            'axis_min' => 3,
            'axis_max' => 4,
            'scaffolding' => 'minimal',
            'thinking' => '다층 — 반론·반론의 반론, 축 간 연결, 비계 최소',
        ],
    ];

    if (!isset($specs[$level])) {
        throw new InvalidArgumentException("Unsupported verify level: {$level} (use 1–7)");
    }

    $spec = $specs[$level];
    $spec['scaffolding_score'] = eduLevelDepthScaffoldingScore((string) $spec['scaffolding']);

    return $spec;
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
        2 => <<<'RULES'
경첩 규칙 (level 2 — 양면 입문):
- hinge: **짧은 양면** "A처럼 보이지만/그런데 B" — L4보다 짧고 쉬운 말. C층 금지.
- side_a: 쉬운 통념/질문 틀.
- side_b: 본문이 살짝 흔드는 반대쪽 (한 줄, 추상 금지).
- hook_student: side_a에서 시작하는 질문.
- shake_prompt: side_b 힌트 + 본문 fact 1개 (부드럽게).
RULES,
        3 => <<<'RULES'
경첩 규칙 (level 3 — 양면 중하):
- hinge: **양면** "A이지만 B" — L4와 비슷하지만 문장·어휘 더 쉽게. 세 번째 층(C) 금지.
- side_a / side_b: 본문 근거, L4보다 짧게.
- hook_student: 양면을 여는 질문.
- shake_prompt: side_b + fact 1개.
RULES,
        4 => <<<'RULES'
경첩 규칙 (level 4 — 중등):
- hinge: **양면** "A이지만/그러나 B" 한 문장. side_a·side_b 모두 본문 근거.
- side_a: 표면 통념 또는 질문 틀.
- side_b: 본문이 드러내는 더 복잡한 진실.
- hook_student: side_a에서 시작하는 질문.
- shake_prompt: side_b 쪽으로 흔드는 한 문장 + 본문 fact 1개.
RULES,
        5 => <<<'RULES'
경첩 규칙 (level 5 — 3축+근거):
- hinge: 양면 + **근거가 갈리는 지점** 한 문장 ("숫자/사건 때문에 A vs B").
- side_a / side_b: L4보다 구체적 (행위자·수치).
- hook_student: 근거를 따져야 하는 질문.
- shake_prompt: 반대 근거 fact 1개 (숫자·고유명사 포함).
RULES,
        6 => <<<'RULES'
경첩 규칙 (level 6 — 다층 입문):
- hinge: **2층 긴장** "A이지만 B, 더 나아가 C" — L7보다 짧고 C는 힌트 수준.
- side_a / side_b: 고등 입문 토론 수준.
- hook_student: 긴장을 관통하는 질문.
- shake_prompt: 반론 fact + "그럼에도" 뉘앙스 (결론 금지).
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
        2 => <<<RULES
축(axes) 규칙 (level 2):
- 정확히 {$axisMin}개. 서로 다른 쉬운 측면.
- core_question: "~일까?" / "~해야 할까?" (L1보다 살짝 열린 질문).
- article_fact: 본문 사실 1개.
- scaffolding_note: **비계 2줄 분량**을 한 줄에 압축 (많음).
- counter_angle: null.
RULES,
        3 => <<<RULES
축(axes) 규칙 (level 3):
- {$axisMin}~{$axisMax}개. L2보다 측면 더 분화.
- core_question: 양면을 여는 질문.
- article_fact: 본문 사실 1개.
- scaffolding_note: 중상 비계 (한 줄, L2보다 짧게).
- counter_angle: 최대 1축만 가벼운 반론 각도 (답 금지).
RULES,
        4 => <<<RULES
축(axes) 규칙 (level 4):
- 정확히 {$axisMin}개. 서로 다른 측면.
- core_question: 양면을 열어주는 질문 ("~일까?" / "~해야 할까?").
- article_fact: 본문 사실 1개.
- scaffolding_note: 짧은 힌트 1줄 (중간).
- counter_angle: 예상 반론 한 줄 (답 아님).
RULES,
        5 => <<<RULES
축(axes) 규칙 (level 5):
- 정확히 {$axisMin}개. **근거 중심** — article_fact에 숫자·사건명·고유명사 1개 이상.
- core_question: 근거를 따지는 질문.
- scaffolding_note: 짧음 (중하) — 힌트만, 답 주지 말 것.
- counter_angle: 1~2축에 예상 반론.
RULES,
        6 => <<<RULES
축(axes) 규칙 (level 6):
- {$axisMin}~{$axisMax}개. 축 간 연결 notes에 한 줄.
- core_question: 반론을 전제로 한 질문.
- article_fact: 본문 사실 1개 (비계 적음).
- scaffolding_note: null 또는 매우 짧음.
- counter_angle: **2축 이상**에 반론 각도 (L7보다 얕게).
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
            'scaffolding_score' => $spec['scaffolding_score'] ?? eduLevelDepthScaffoldingScore((string) $spec['scaffolding']),
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

/** @param list<array<string, mixed>> $byLevel keyed by level */
function eduLevelDepthCompareSummary(array $byLevel, ?array $levels = null): array
{
    $order = $levels ?? eduLevelDepthVerifyLevels(array_keys($byLevel));
    sort($order);
    $rows = [];
    foreach ($order as $level) {
        $ex = $byLevel[$level] ?? null;
        if (!is_array($ex)) {
            continue;
        }
        $spec = $ex['depth_spec'] ?? [];
        $scaffoldAxes = 0;
        $scaffoldChars = 0;
        foreach ($ex['axes'] ?? [] as $ax) {
            if (!is_array($ax)) {
                continue;
            }
            $note = trim((string) ($ax['scaffolding_note'] ?? ''));
            if ($note !== '') {
                $scaffoldAxes++;
                $scaffoldChars += mb_strlen($note);
            }
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
            'scaffolding' => (string) ($spec['scaffolding'] ?? ''),
            'scaffolding_score' => (int) ($spec['scaffolding_score'] ?? 0),
            'scaffold_axes' => $scaffoldAxes,
            'scaffold_chars' => $scaffoldChars,
            'thinking_summary' => (string) ($ex['thinking_summary'] ?? ''),
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $compare
 * @return array<string, mixed>
 */
function eduLevelDepthStaircaseAnalysis(array $compare): array
{
    $warnings = [];
    $adjacent = [];
    $monotonic = ['hinge_len' => true, 'axis_count' => true, 'scaffolding_score' => true];

    for ($i = 1, $n = count($compare); $i < $n; $i++) {
        $prev = $compare[$i - 1];
        $cur = $compare[$i];
        $pair = ($prev['level'] ?? '?') . '→' . ($cur['level'] ?? '?');

        if ((int) ($cur['hinge_len'] ?? 0) < (int) ($prev['hinge_len'] ?? 0)) {
            $monotonic['hinge_len'] = false;
        }
        if ((int) ($cur['axis_count'] ?? 0) < (int) ($prev['axis_count'] ?? 0)) {
            $monotonic['axis_count'] = false;
        }
        if ((int) ($cur['scaffolding_score'] ?? 0) > (int) ($prev['scaffolding_score'] ?? 0)) {
            $monotonic['scaffolding_score'] = false;
        }

        $hingeDelta = (int) ($cur['hinge_len'] ?? 0) - (int) ($prev['hinge_len'] ?? 0);
        $axisDelta = (int) ($cur['axis_count'] ?? 0) - (int) ($prev['axis_count'] ?? 0);
        $scaffoldDelta = (int) ($prev['scaffolding_score'] ?? 0) - (int) ($cur['scaffolding_score'] ?? 0);

        $distinct = $hingeDelta !== 0 || $axisDelta !== 0 || $scaffoldDelta !== 0
            || (bool) ($cur['has_side_b'] ?? false) !== (bool) ($prev['has_side_b'] ?? false)
            || (int) ($cur['counter_axes'] ?? 0) !== (int) ($prev['counter_axes'] ?? 0);

        $adjacent[] = [
            'pair' => $pair,
            'hinge_delta' => $hingeDelta,
            'axis_delta' => $axisDelta,
            'scaffold_delta' => $scaffoldDelta,
            'distinct' => $distinct,
        ];

        if (!$distinct) {
            $warnings[] = "{$pair}: 인접 레벨이 거의 동일 (흐릿)";
        }
    }

    $criticalPairs = ['2→3', '5→6'];
    foreach ($adjacent as $row) {
        if (in_array($row['pair'], $criticalPairs, true) && !($row['distinct'] ?? false)) {
            $warnings[] = 'CRITICAL ' . $row['pair'] . ': 사이 단계 구분 실패';
        }
    }

    return [
        'monotonic' => $monotonic,
        'adjacent' => $adjacent,
        'warnings' => $warnings,
        'staircase_ok' => $monotonic['hinge_len'] && $monotonic['scaffolding_score'] && $warnings === [],
    ];
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
    $phase = (string) ($payload['phase'] ?? 'phase3');
    $md = "# EDU Level Depth Verify — {$newsId} {$title}\n\n";
    $md .= '> ' . ($phase === 'phase3' ? 'Phase 3' : 'Phase 1') . ' · ' . date('Y-m-d H:i:s') . ' · prompt ' . EDU_LEVEL_DEPTH_PROMPT_VERSION . "\n\n";

    if ($phase === 'phase3') {
        $md .= "## 핵심 질문\n\n";
        $md .= "같은 글에서 level **1~7**이 **계단**처럼 단조 증가하는가? 인접(2↔3, 5↔6)이 구분되는가?\n\n";
    } else {
        $md .= "## 핵심 질문\n\n";
        $md .= "같은 글에서 level 1 / 4 / 7 구조가 **진짜 다른 깊이**로 나오는가?\n\n";
    }

    $md .= "## 7단 계단 표 (자동 — 사람 눈 검수 필수)\n\n";
    $md .= "| L | label | hinge | side_b | axes | counter | scaffold | scaffold_axes | thinking |\n";
    $md .= "|---|-------|-------|--------|------|---------|----------|---------------|----------|\n";
    foreach ($payload['compare'] ?? [] as $row) {
        $md .= '| ' . ($row['level'] ?? '') . ' | '
            . ($row['label'] ?? '') . ' | '
            . ($row['hinge_len'] ?? '') . ' | '
            . (($row['has_side_b'] ?? false) ? 'Y' : 'N') . ' | '
            . ($row['axis_count'] ?? '') . ' | '
            . ($row['counter_axes'] ?? '') . ' | '
            . ($row['scaffolding'] ?? '') . ' | '
            . ($row['scaffold_axes'] ?? '') . ' | '
            . str_replace('|', '\\|', mb_substr((string) ($row['thinking_summary'] ?? ''), 0, 40)) . " |\n";
    }

    $stair = $payload['staircase'] ?? [];
    if ($stair !== []) {
        $md .= "\n## 계단 자동 판정 (참고)\n\n";
        $mono = $stair['monotonic'] ?? [];
        $md .= '- hinge_len 단조: ' . (!empty($mono['hinge_len']) ? 'OK' : 'FAIL') . "\n";
        $md .= '- axis_count 단조: ' . (!empty($mono['axis_count']) ? 'OK' : 'WARN(유연)') . "\n";
        $md .= '- scaffold 단조: ' . (!empty($mono['scaffolding_score']) ? 'OK' : 'FAIL') . "\n";
        $md .= '- staircase_ok: ' . (!empty($stair['staircase_ok']) ? '**YES**' : '**NO — 단계 수 재고 검토**') . "\n";
        foreach ($stair['warnings'] ?? [] as $w) {
            $md .= '- ⚠ ' . $w . "\n";
        }
        $md .= "\n### 인접 delta\n\n";
        $md .= "| pair | Δhinge | Δaxes | Δscaffold | distinct? |\n";
        $md .= "|------|--------|-------|-----------|----------|\n";
        foreach ($stair['adjacent'] ?? [] as $row) {
            $md .= '| ' . ($row['pair'] ?? '') . ' | '
                . ($row['hinge_delta'] ?? '') . ' | '
                . ($row['axis_delta'] ?? '') . ' | '
                . ($row['scaffold_delta'] ?? '') . ' | '
                . (($row['distinct'] ?? false) ? 'Y' : '**N**') . " |\n";
        }
    }

    $md .= "\n## 사람 검수 (이원근)\n\n";
    if ($phase === 'phase3') {
        $md .= "- [ ] 1→7 **계단**이 매끄러운가? (띄엄띄엄이면 5~4단 재설계)\n";
        $md .= "- [ ] **2 vs 3** 구분되는가?\n";
        $md .= "- [ ] **5 vs 6** 구분되는가?\n";
        $md .= "- [ ] 1·4·7 Phase 1 수준 유지되는가?\n\n";
    } else {
        $md .= "- [ ] level 1이 초등이 따질 만큼 **단순**한가?\n";
        $md .= "- [ ] level 7이 **고등 수준으로 깊은**가?\n";
        $md .= "- [ ] level 4가 **중간**인가?\n";
        $md .= "- [ ] 셋이 **서로 비슷하지 않은**가? (흐릿하면 재설계)\n\n";
    }

    $levelOrder = $payload['level_order'] ?? eduLevelDepthVerifyLevels();
    foreach ($levelOrder as $level) {
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
