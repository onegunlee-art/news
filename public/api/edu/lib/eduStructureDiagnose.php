<?php
/**
 * P2-B 1단계 — 학생 글/대화 구조 진단 (내부 전용, 점수·등급 없음)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';
require_once __DIR__ . '/eduCoachGuide.php';

const EDU_STRUCTURE_DIAGNOSE_VERSION = 'p2-b-v1-rough';

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
        'structure_note' => $structureNote !== '' ? $structureNote : '구조 진단 서술 없음',
    ];
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

    return [
        'tension_engaged' => $tension,
        'tension_note' => '규칙 기반 fallback (LLM 미사용)',
        'conclusion_clarity' => $clarity,
        'conclusion_quote' => $conclusion !== '' ? mb_substr($conclusion, 0, 120) : null,
        'evidence_linked' => $linked ? 'yes' : 'no',
        'evidence_note' => $linked ? '기사 fact 키워드가 학생 발화에 일부 연결됨' : 'fact 연결 신호 약함',
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
채점·등급·점수·잘함/못함 판정 금지. "the gist 구조(경첩·축)의 어디를 채웠고 어디가 비었나"만 진단한다.
결론 내용의 옳고 그름은 평가하지 않는다. 명확성·구조만.

JSON만 출력:
{
  "tension_engaged": "양면"|"한쪽"|"없음",
  "tension_note": "한 줄",
  "conclusion_clarity": "명확"|"모호",
  "conclusion_quote": "학생 결론 인용 일부",
  "evidence_linked": "yes"|"no",
  "evidence_note": "한 줄",
  "structure_note": "2~4문장. 축·긴장·결론·근거 연결 중 무엇이 채워졌고 비었는지 서술. 등급/점수 금지"
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
        'essay_text' => $essayText !== '' ? mb_substr($essayText, 0, 2000) : null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $resp = $llm->haiku($system, [
        ['role' => 'user', 'content' => (string) $user],
    ], 700);

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

    if (!empty($llmPart['error'])) {
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
        'structure_note' => $llmPart['structure_note'],
        'diagnose_mode' => $llmPart['diagnose_mode'] ?? 'llm',
        'hinge_ref' => [
            'side_a' => $hinge['side_a'] ?? null,
            'side_b' => $hinge['side_b'] ?? null,
        ],
    ];
}
