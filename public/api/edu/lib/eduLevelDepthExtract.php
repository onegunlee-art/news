<?php
/**
 * EDU 5단계 깊이 검증 — 레벨별 구조 추출 (검증 전용, 프로덕션 무관)
 *
 * L1~L5: 초6 → the gist. 7단 검증 FAIL 후 5단으로 재설계.
 */
declare(strict_types=1);

require_once __DIR__ . '/eduHingeExtract.php';

const EDU_LEVEL_DEPTH_VERIFY_LEVELS = [1, 2, 3, 4, 5];
const EDU_LEVEL_DEPTH_VERIFY_LEVELS_LEGACY7 = [1, 2, 3, 4, 5, 6, 7];
const EDU_LEVEL_DEPTH_VERIFY_LEVELS_PHASE1 = [1, 4, 7];
const EDU_LEVEL_DEPTH_PROMPT_VERSION = 'level-depth-verify-v3-5step';
const EDU_LEVEL_DEPTH_STEP_COUNT = 5;

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
            'label' => 'L1 초6',
            'audience' => '초6 / 만 11~12세',
            'hinge_mode' => 'single_question',
            'axis_min' => 1,
            'axis_max' => 2,
            'scaffolding' => 'heavy',
            'legacy_7' => 'L1',
            'thinking' => '한 면 — "~일까?" 일상어, 통념 vs 본문 fact 1개, 비계 듬뿍',
        ],
        2 => [
            'level' => 2,
            'label' => 'L2 초고~중1',
            'audience' => '초6~중1',
            'hinge_mode' => 'dual_intro',
            'axis_min' => 2,
            'axis_max' => 3,
            'scaffolding' => 'heavyish',
            'legacy_7' => 'L2~3',
            'thinking' => '양면 입문 — 짧은 A/B, 2~3축, 비계 많음 (L1보다 깊게)',
        ],
        3 => [
            'level' => 3,
            'label' => 'L3 중2~3',
            'audience' => '중2~3',
            'hinge_mode' => 'dual_sided',
            'axis_min' => 3,
            'axis_max' => 3,
            'scaffolding' => 'medium',
            'legacy_7' => 'L4',
            'thinking' => '양면 3축 — A vs B, 축마다 반론 1겹(counter), 비계 중',
        ],
        4 => [
            'level' => 4,
            'label' => 'L4 고1~2',
            'audience' => '고1~2',
            'hinge_mode' => 'evidence_multi_layer',
            'axis_min' => 3,
            'axis_max' => 3,
            'scaffolding' => 'light',
            'legacy_7' => 'L5~6',
            'thinking' => '3축+근거(수치·사건) + 다층 입문, 비계 적음',
        ],
        5 => [
            'level' => 5,
            'label' => 'L5 the gist',
            'audience' => '최상위 고3 / 졸업',
            'hinge_mode' => 'multi_layer',
            'axis_min' => 3,
            'axis_max' => 4,
            'scaffolding' => 'minimal',
            'legacy_7' => 'L7',
            'thinking' => 'the gist 원문 수준 — 다층+반론의 반론, 축 연결, 비계 0',
        ],
    ];

    if (!isset($specs[$level])) {
        throw new InvalidArgumentException("Unsupported verify level: {$level} (use 1–5)");
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
경첩 규칙 (L1 — 초6, 일상어):
- hinge: **한 면**만. "A이지만 B" 금지. **일상어** 단순 질문 ("~일까?", "~면 안전할까?").
- side_a: 쉬운 통념/질문 틀 (초등 어휘).
- side_b: null (양면 분해 안 함).
- hook_student: side_a와 같은 톤.
- shake_prompt: 본문 fact 1개 + 부드러운 힌트.
RULES,
        2 => <<<'RULES'
경첩 규칙 (L2 — 양면 입문):
- hinge: **짧은 양면** "A처럼 보이지만/그런데 B" — L3보다 짧고 쉬운 말. C층 금지.
- side_a / side_b: 본문 근거, 일상어 유지.
- hook_student: side_a에서 시작.
- shake_prompt: side_b 힌트 + fact 1개.
RULES,
        3 => <<<'RULES'
경첩 규칙 (L3 — 중등 양면):
- hinge: **양면** "A이지만/그러나 B" 한 문장. side_a·side_b 모두 본문 근거.
- hook_student: 양면을 여는 질문.
- shake_prompt: side_b + fact 1개.
RULES,
        4 => <<<'RULES'
경첩 규칙 (L4 — 근거+다층 입문):
- hinge: **2층 긴장** "A이지만 B, 더 나아가 C" — L5보다 짧고 C는 힌트. **근거(수치·사건)** 포함.
- side_a / side_b: 구체적 행위자·사건.
- hook_student: 근거와 긴장을 함께 따지는 질문.
- shake_prompt: 반론 fact + "그럼에도" (결론 금지).
RULES,
        default => <<<'RULES'
경첩 규칙 (L5 — the gist / 졸업):
- hinge: **다층** — "A이지만 B, 더 나아가 C" 또는 nested tension. the gist 원문에 가까운 밀도.
- side_a / side_b: 고등 토론 수준. 추상어 최소.
- hook_student: 핵심 긴장 관통 질문.
- shake_prompt: 반론 깨는 fact + "그럼에도".
RULES,
    };

    $axisRules = match ((int) $level) {
        1 => <<<RULES
축(axes) 규칙 (L1):
- {$axisMin}~{$axisMax}개. **일상어** point.
- core_question: "~일까?" 답·결론 금지.
- article_fact: 본문 사실 1개.
- scaffolding_note: **듬뿍** (한 줄이지만 구체적 힌트).
- counter_angle: null.
RULES,
        2 => <<<RULES
축(axes) 규칙 (L2):
- {$axisMin}~{$axisMax}개. L1보다 측면 분화.
- core_question: "~일까?" / "~해야 할까?"
- article_fact: 본문 사실 1개.
- scaffolding_note: **많음** (L1보다 짧게).
- counter_angle: null.
RULES,
        3 => <<<RULES
축(axes) 규칙 (L3):
- 정확히 {$axisMin}개. 서로 다른 측면.
- core_question: 양면 여는 질문.
- article_fact: 본문 사실 1개.
- scaffolding_note: 중간 (한 줄).
- counter_angle: **모든 축**에 예상 반론 1줄 (반론 1겹, 답 금지).
RULES,
        4 => <<<RULES
축(axes) 규칙 (L4):
- 정확히 {$axisMin}개. **근거 중심** — article_fact에 숫자·사건명·고유명사.
- core_question: 근거+다층을 따지는 질문.
- scaffolding_note: null 또는 매우 짧음 (light).
- counter_angle: 2축 이상 반론 각도.
RULES,
        default => <<<RULES
축(axes) 규칙 (L5 — the gist):
- {$axisMin}~{$axisMax}개. **축 간 연결** notes에 한 줄.
- core_question: "~라면 ~는?" / 반론 전제 질문.
- article_fact: 본문 사실 1개.
- scaffolding_note: null (비계 0).
- counter_angle: **반론의 반론** (메타 반박, 결론 금지).
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

    $criticalPairs = ['1→2', '2→3', '3→4', '4→5'];
    foreach ($adjacent as $row) {
        if (in_array($row['pair'], $criticalPairs, true) && !($row['distinct'] ?? false)) {
            $warnings[] = 'CRITICAL ' . $row['pair'] . ': 인접 레벨 구분 실패';
        }
    }

    $hingeWarn = !$monotonic['hinge_len'];
    if ($hingeWarn) {
        $warnings[] = 'hinge_len 비단조 (참고 — axis/scaffold/counter로 판정)';
    }

    $blockingWarnings = array_filter(
        $warnings,
        static fn ($w) => str_starts_with($w, 'CRITICAL') || str_contains($w, '거의 동일')
    );

    return [
        'monotonic' => $monotonic,
        'adjacent' => $adjacent,
        'warnings' => $warnings,
        'staircase_ok' => $monotonic['scaffolding_score']
            && $monotonic['axis_count']
            && count($blockingWarnings) === 0,
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
    $phase = (string) ($payload['phase'] ?? 'phase5');
    $stepCount = count($payload['level_order'] ?? eduLevelDepthVerifyLevels());
    $phaseLabel = match ($phase) {
        'phase5' => 'Phase 5 (5단 검증)',
        'phase3' => 'Phase 3 (7단)',
        default => 'Phase 1',
    };
    $md = "# EDU Level Depth Verify — {$newsId} {$title}\n\n";
    $md .= '> ' . $phaseLabel . ' · ' . date('Y-m-d H:i:s') . ' · prompt ' . EDU_LEVEL_DEPTH_PROMPT_VERSION . "\n\n";

    if ($phase === 'phase5') {
        $md .= "## 핵심 질문\n\n";
        $md .= "같은 글에서 level **1~5**가 **계단**처럼 단조 증가하는가? 인접(1↔2, 2↔3, 3↔4, 4↔5)이 **7단보다 또렷**한가?\n\n";
    } elseif ($phase === 'phase3') {
        $md .= "## 핵심 질문\n\n";
        $md .= "같은 글에서 level **1~7**이 **계단**처럼 단조 증가하는가? 인접(2↔3, 5↔6)이 구분되는가?\n\n";
    } else {
        $md .= "## 핵심 질문\n\n";
        $md .= "같은 글에서 level 1 / 4 / 7 구조가 **진짜 다른 깊이**로 나오는가?\n\n";
    }

    $md .= "## {$stepCount}단 계단 표 (자동 — 사람 눈 검수 필수)\n\n";
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
        $md .= '- staircase_ok: ' . (!empty($stair['staircase_ok']) ? '**YES**' : '**NO — 4단 재설계 검토**') . "\n";
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
    if ($phase === 'phase5') {
        $md .= "- [ ] 1→5 **계단**이 매끄러운가? (hinge/축/비계/counter)\n";
        $md .= "- [ ] **1 vs 2, 2 vs 3, 3 vs 4, 4 vs 5** 모두 구분되는가?\n";
        $md .= "- [ ] L1 단순 / L3 반론 1겹 / L5 the gist 수준인가?\n";
        $md .= "- [ ] FAIL이면 → **4단** 재설계\n\n";
    } elseif ($phase === 'phase3') {
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
