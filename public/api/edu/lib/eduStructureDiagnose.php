<?php
/**
 * P2-B 1단계 — 학생 글/대화 구조 진단 (내부 전용, 점수·등급 없음)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';
require_once __DIR__ . '/eduCoachGuide.php';

const EDU_STRUCTURE_DIAGNOSE_VERSION = 'p2-phase2-llm-v1';

/** @return list<string> */
function eduStructureDiagnoseForbiddenOutputKeys(): array
{
    return [
        'score', 'grade', 'rating', 'percent', 'rank', 'level',
        'well', 'poor', 'bad', 'good', 'fail', 'pass',
        '점수', '등급', '잘함', '못함', '우수', '미흡',
    ];
}

/**
 * @param array<string, mixed> $quest
 * @return array{hinge: array<string, mixed>, axes: list<array<string, string>>}
 */
function eduStructureDiagnoseReference(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $hinge = is_array($hints['_hinge'] ?? null) ? $hints['_hinge'] : [];
    $axes = eduCoachGuideAxes($quest);

    return ['hinge' => $hinge, 'axes' => $axes];
}

/**
 * @param list<array<string, mixed>> $dialogue
 * @return list<string>
 */
function eduStructureDiagnoseStudentTexts(array $dialogue): array
{
    $texts = [];
    foreach ($dialogue as $turn) {
        if (!is_array($turn) || ($turn['role'] ?? '') !== 'student') {
            continue;
        }
        $t = trim((string) ($turn['content'] ?? ''));
        if ($t !== '') {
            $texts[] = $t;
        }
    }

    return $texts;
}

/**
 * @param list<array<string, string>> $axes
 * @param array<string, mixed> $blueprint
 * @return list<array<string, mixed>>
 */
function eduStructureDiagnoseAxisCoverage(array $blueprint, array $axes): array
{
    $answers = is_array($blueprint['guide_axis_answers'] ?? null)
        ? $blueprint['guide_axis_answers']
        : [];
    $out = [];

    foreach ($axes as $axis) {
        $axisId = (string) ($axis['axis_id'] ?? '');
        if ($axisId === '') {
            continue;
        }
        $point = (string) ($axis['point'] ?? $axisId);
        $skippedKey = $axisId . '_skipped';
        $quote = null;
        $status = 'missing';
        $covered = false;

        if (isset($answers[$skippedKey])) {
            $quote = trim((string) $answers[$skippedKey]);
            $status = 'skipped';
        } elseif (isset($answers[$axisId])) {
            $quote = trim((string) $answers[$axisId]);
            if ($quote !== '' && mb_strlen($quote) >= 10) {
                $status = 'engaged';
                $covered = true;
            } elseif ($quote !== '') {
                $status = 'shallow';
            }
        }

        $out[] = [
            'axis_id' => $axisId,
            'point' => $point,
            'covered' => $covered,
            'status' => $status,
            'student_quote' => $quote !== '' && $quote !== null ? mb_substr($quote, 0, 120) : null,
        ];
    }

    return $out;
}

/** @param array<string, mixed> $parsed */
function eduStructureDiagnoseSanitizeLlm(array $parsed): array
{
    $allowedTension = ['양면', '한쪽', '없음'];
    $allowedClarity = ['명확', '모호'];

    $tension = (string) ($parsed['tension_engaged'] ?? '없음');
    if (!in_array($tension, $allowedTension, true)) {
        $tension = '없음';
    }

    $clarity = (string) ($parsed['conclusion_clarity'] ?? '모호');
    if (!in_array($clarity, $allowedClarity, true)) {
        $clarity = '모호';
    }

    $evidence = strtolower((string) ($parsed['evidence_linked'] ?? 'no'));
    $evidenceLinked = in_array($evidence, ['yes', 'y', 'true', '1'], true) ? 'yes' : 'no';

    $levelRaw = $parsed['exploration_depth_level'] ?? null;
    $level = null;
    if (is_numeric($levelRaw)) {
        $level = max(1, min(7, (int) $levelRaw));
    }

    $structureNote = trim((string) ($parsed['structure_note'] ?? ''));
    foreach (eduStructureDiagnoseForbiddenOutputKeys() as $bad) {
        if ($structureNote !== '' && stripos($structureNote, $bad) !== false) {
            $structureNote = preg_replace('/' . preg_quote($bad, '/') . '/iu', '', $structureNote) ?? $structureNote;
        }
    }

    return [
        'tension_engaged' => $tension,
        'tension_note' => trim((string) ($parsed['tension_note'] ?? '')),
        'conclusion_clarity' => $clarity,
        'conclusion_quote' => mb_substr(trim((string) ($parsed['conclusion_quote'] ?? '')), 0, 120),
        'evidence_linked' => $evidenceLinked,
        'evidence_note' => trim((string) ($parsed['evidence_note'] ?? '')),
        'exploration_depth_level' => $level,
        'level_rationale' => mb_substr(trim((string) ($parsed['level_rationale'] ?? '')), 0, 240),
        'structure_note' => $structureNote !== '' ? $structureNote : '구조 진단 서술 없음',
    ];
}

/**
 * @param list<array<string, mixed>> $axesCovered
 */
function eduStructureDiagnoseRuleFallbackLevel(
    string $tension,
    array $axesCovered,
    string $evidenceLinked
): int {
    $engaged = 0;
    foreach ($axesCovered as $axis) {
        if (!empty($axis['covered'])) {
            $engaged++;
        }
    }

    if ($tension === '양면' && $engaged >= 3 && $evidenceLinked === 'yes') {
        return 7;
    }
    if ($tension === '양면' || ($engaged >= 2 && $evidenceLinked === 'yes')) {
        return 4;
    }
    if ($engaged >= 1) {
        return 2;
    }

    return 1;
}

/**
 * @param list<array<string, string>> $axes
 * @param list<array<string, mixed>> $axesCovered
 */
function eduStructureDiagnoseRuleFallback(
    array $hinge,
    array $axes,
    array $axesCovered,
    array $blueprint,
    array $studentTexts
): array {
    $sideA = trim((string) ($hinge['side_a'] ?? ''));
    $sideB = trim((string) ($hinge['side_b'] ?? ''));
    $blob = mb_strtolower(implode(' ', $studentTexts));
    $aHit = $sideA !== '' && eduStructureDiagnoseTextHit($blob, $sideA);
    $bHit = $sideB !== '' && eduStructureDiagnoseTextHit($blob, $sideB);
    $tension = ($aHit && $bHit) ? '양면' : (($aHit || $bHit) ? '한쪽' : '없음');

    $conclusion = trim((string) ($blueprint['guide_student_conclusion'] ?? ''));
    $clarity = ($conclusion !== '' && mb_strlen($conclusion) >= 12) ? '명확' : '모호';

    $facts = [];
    foreach ($axes as $axis) {
        $f = trim((string) ($axis['article_fact'] ?? ''));
        if ($f !== '') {
            $facts[] = $f;
        }
    }
    $linked = false;
    foreach ($facts as $fact) {
        if (eduStructureDiagnoseFactLinked($blob, $fact)) {
            $linked = true;
            break;
        }
    }

    $engaged = array_filter($axesCovered, static fn ($a) => !empty($a['covered']));
    $missing = array_filter($axesCovered, static fn ($a) => empty($a['covered']));
    $structureNote = count($engaged) . '개 축에 학생 발화가 있고, '
        . count($missing) . '개 축은 비었거나 얕음. 긴장 '
        . $tension . ', 결론 ' . $clarity . '.';

    $evidenceStr = $linked ? 'yes' : 'no';
    $fallbackLevel = eduStructureDiagnoseRuleFallbackLevel($tension, $axesCovered, $evidenceStr);

    return [
        'tension_engaged' => $tension,
        'tension_note' => '규칙 기반 fallback (LLM 미사용)',
        'conclusion_clarity' => $clarity,
        'conclusion_quote' => $conclusion !== '' ? mb_substr($conclusion, 0, 120) : null,
        'evidence_linked' => $evidenceStr,
        'evidence_note' => $linked ? '기사 fact 키워드가 학생 발화에 일부 연결됨' : 'fact 연결 신호 약함',
        'exploration_depth_level' => $fallbackLevel,
        'level_rationale' => 'rule fallback — 축·긴장·근거 키워드로 추정 (LLM 미사용)',
        'structure_note' => $structureNote,
    ];
}

function eduStructureDiagnoseTextHit(string $blob, string $needle): bool
{
    $n = mb_strtolower(preg_replace('/\s+/u', '', $needle) ?? $needle);
    if ($n === '') {
        return false;
    }
    $tokens = preg_split('/[\s,·]+/u', mb_substr($n, 0, 40)) ?: [];
    $hits = 0;
    foreach ($tokens as $tok) {
        if (mb_strlen($tok) >= 2 && str_contains($blob, $tok)) {
            $hits++;
        }
    }

    return $hits >= 2;
}

function eduStructureDiagnoseFactLinked(string $blob, string $fact): bool
{
    if (preg_match_all('/[\p{L}\p{N}]{3,}/u', $fact, $m) === false) {
        return false;
    }
    $hits = 0;
    foreach (array_unique($m[0] ?? []) as $tok) {
        if (mb_strlen($tok) >= 3 && str_contains($blob, mb_strtolower($tok))) {
            $hits++;
        }
    }

    return $hits >= 2;
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $blueprint
 * @param list<array<string, mixed>> $dialogue
 * @param list<array<string, mixed>> $axesCovered
 * @return array<string, mixed>
 */
function eduStructureDiagnoseWithLlm(
    $llm,
    array $quest,
    array $hinge,
    array $axes,
    array $blueprint,
    array $dialogue,
    array $axesCovered,
    string $essayText = ''
): array {
    $studentTexts = eduStructureDiagnoseStudentTexts($dialogue);
    $system = <<<'PROMPT'
너는 GIST EDU 내부 구조 진단기다. 학생에게 보이지 않는다.
채점·등급·점수·잘함/못함 판정 금지. 결론 내용의 옳고 그름은 평가하지 않는다.
"the gist 구조(경첩·축)의 어디를 채웠고, 얼마나 깊이 탐구했는지"만 진단한다.

[1 — 구조 평가]
- tension_engaged "양면": 경첩 side_a·side_b 긴장을 **둘 다** 학생이 인식·언급 (한쪽만이면 "한쪽", 둘 다 없으면 "없음")
- evidence_linked "yes": axes의 article_fact 또는 기사 구체 fact(숫자·지명·사건명)를 학생이 **연결**해 썼음 (키워드만 겹치면 yes, 추상만이면 no)
- conclusion_clarity: 학생 결론/입장이 한 문장으로 잡히면 "명확", 아니면 "모호"
- axes_covered_precomputed는 규칙 산출값 — 참고하되 학생_turns·essay_text로 tension/evidence는 직접 판정

[2 — 탐구 깊이 레벨 (1~7, Phase 4 레벨업 근거용 — 지금은 저장만)]
- 1: 단순 질문 한 층, 1~2축만 얕게
- 4: 양면 인식, ~3축, fact 일부 연결
- 7: 다층 긴장·반론·반박, 3~4축 깊게, fact 적극 연결
- 2,3,5,6: 중간 깊이도 가능 (1~7 정수)

JSON만 출력:
{
  "tension_engaged": "양면"|"한쪽"|"없음",
  "tension_note": "한 줄 — 왜 이 판정인지",
  "conclusion_clarity": "명확"|"모호",
  "conclusion_quote": "학생 결론 인용 일부",
  "evidence_linked": "yes"|"no",
  "evidence_note": "한 줄 — 어떤 fact 연결/미연결",
  "exploration_depth_level": 1,
  "level_rationale": "한 줄 — L1/L4/L7 기준으로 왜 이 레벨",
  "structure_note": "2~4문장. 축·긴장·결론·근거. 등급/점수 금지"
}
PROMPT;

    $user = json_encode([
        'quest_code' => $quest['quest_code'] ?? '',
        'hinge' => $hinge,
        'axes' => $axes,
        'axes_covered_precomputed' => $axesCovered,
        'student_opening' => $blueprint['guide_opening'] ?? $blueprint['reason'] ?? '',
        'student_conclusion' => $blueprint['guide_student_conclusion'] ?? '',
        'student_turns' => $studentTexts,
        'essay_text' => $essayText !== '' ? mb_substr($essayText, 0, 3000) : null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $resp = $llm->chat($system, [
        ['role' => 'user', 'content' => (string) $user],
    ], 900, 0.15);

    if (!empty($resp['error'])) {
        return ['error' => $resp['error'], 'message' => $resp['message'] ?? 'llm_error'];
    }

    $raw = trim((string) ($resp['content'] ?? ''));
    $raw = preg_replace('/^```json\s*|\s*```$/u', '', $raw) ?? $raw;
    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        return ['error' => 'invalid_json', 'message' => mb_substr($raw, 0, 200)];
    }

    return eduStructureDiagnoseSanitizeLlm($parsed);
}

/**
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $blueprint
 * @param list<array<string, mixed>> $dialogue
 * @return array<string, mixed>
 */
function eduStructureDiagnoseSession(
    string $sessionId,
    array $quest,
    array $blueprint,
    array $dialogue,
    $llm = null,
    string $essayText = ''
): array {
    $ref = eduStructureDiagnoseReference($quest);
    $hinge = $ref['hinge'];
    $axes = $ref['axes'];
    $axesCovered = eduStructureDiagnoseAxisCoverage($blueprint, $axes);
    $studentTexts = eduStructureDiagnoseStudentTexts($dialogue);

    $llmPart = ['error' => 'no_llm'];
    if ($llm !== null) {
        $llmPart = eduStructureDiagnoseWithLlm(
            $llm,
            $quest,
            $hinge,
            $axes,
            $blueprint,
            $dialogue,
            $axesCovered,
            $essayText
        );
    }

    $fallbackReason = null;
    if (!empty($llmPart['error'])) {
        $fallbackReason = (string) ($llmPart['error']);
        if (!empty($llmPart['message'])) {
            $fallbackReason .= ':' . (string) $llmPart['message'];
        }
        $llmPart = eduStructureDiagnoseRuleFallback($hinge, $axes, $axesCovered, $blueprint, $studentTexts);
        $llmPart['diagnose_mode'] = 'rule_fallback';
    } else {
        $llmPart['diagnose_mode'] = 'llm';
    }

    return [
        'diagnose_version' => EDU_STRUCTURE_DIAGNOSE_VERSION,
        'quest_code' => (string) ($quest['quest_code'] ?? ''),
        'session_id' => $sessionId,
        'internal_only' => true,
        'axes_covered' => $axesCovered,
        'tension_engaged' => $llmPart['tension_engaged'],
        'tension_note' => $llmPart['tension_note'] ?? '',
        'conclusion_clarity' => $llmPart['conclusion_clarity'],
        'conclusion_quote' => $llmPart['conclusion_quote'] ?? null,
        'evidence_linked' => $llmPart['evidence_linked'],
        'evidence_note' => $llmPart['evidence_note'] ?? '',
        'exploration_depth_level' => $llmPart['exploration_depth_level'] ?? null,
        'level_rationale' => $llmPart['level_rationale'] ?? '',
        'structure_note' => $llmPart['structure_note'],
        'diagnose_mode' => $llmPart['diagnose_mode'] ?? 'llm',
        'diagnose_fallback_reason' => $fallbackReason,
        'hinge_ref' => [
            'side_a' => $hinge['side_a'] ?? null,
            'side_b' => $hinge['side_b'] ?? null,
        ],
    ];
}
