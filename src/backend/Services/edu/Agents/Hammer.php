<?php
/**
 * GIST EDU Agent A3: Hammer
 * 학생 입장에 대한 설득력 있는 반론 생성
 * 
 * "망치는 깨려고 치는 게 아니라 단단하게 만들려고 친다"
 * 
 * 모드:
 * - adversarial (기본): 찬성/반대 입장 대립
 * - convergent: 같은 결론, 다른 근거 축 — pivot_question으로 층위 구분
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class Hammer
{
    private $llm;

    /** 탐구조 톤 — convergent/adversarial/meta 공통 */
    private function warmExplorationToneBlock(): string
    {
        return <<<'TONE'
탐구 톤 (반드시):
- 1문장: 학생이 실제로 말한 구체적 이유·키워드를 짚어서 인정 (학생 말을 그대로 반영)
  · 좋음: "적국 사이라 스스로 지킬 힘이 필요하다는 거구나" (학생이 말한 이유/사례를 구체적으로)
  · 나쁨: "그렇게 보는구나", "~게 보는구나~" — 형식적·영혼 없는 인정 (금지)
- 1~2문장: 다른 시각/층위 소개 — "~일 수도", "~게 보는 사람도 있어" (단정·공격 금지)
- 학생 문장에서 핵심 표현·이유를 따옴표로 인용하거나 자연스럽게 재진술
- 금지: "반론", "토론 상대", "받아쳐", "~가 아니야 ~야", "강력한 반론", "토론 상대방", "그렇게 보는구나"
TONE;
    }

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    public function strike(
        string $stance,
        string $studentReason,
        array $quest,
        string $intensity = 'medium',
        ?array $scoreAnalysis = null,
        string $mixupContext = ''
    ): array {
        $hints = $quest['hammer_hints'] ?? [];
        if (is_string($hints)) {
            $hints = json_decode($hints, true) ?? [];
        }

        $mode = $hints['mode'] ?? 'adversarial';

        if ($mode === 'convergent') {
            return $this->strikeConvergent($studentReason, $quest, $hints, $intensity);
        }

        return $this->strikeAdversarial($stance, $studentReason, $quest, $hints, $intensity, $scoreAnalysis, $mixupContext);
    }

    /**
     * 수렴형 hammer: 같은 결론, 다른 근거 축
     * 학생 근거의 "층위"를 파고들어 pivot_question으로 양자택일 강제
     */
    private function strikeConvergent(
        string $studentReason,
        array $quest,
        array $hints,
        string $intensity
    ): array {
        $axes = $hints['axes'] ?? [];
        if (count($axes) < 2) {
            return $this->fallbackToAdversarial($quest, $hints);
        }

        $sharedConclusion = $hints['shared_conclusion'] ?? '';
        $isDecisionInquiry = ($hints['quest_frame'] ?? '') === 'decision_inquiry';
        $detection = $this->detectStudentAxis($studentReason, $axes, $sharedConclusion, $isDecisionInquiry);

        if ($detection['margin_gate'] || $detection['confidence'] === 'low' || $detection['axis'] === null) {
            $meta = $this->buildMetaAskResponse($axes, $sharedConclusion, $isDecisionInquiry);
            $meta['classification_scores'] = $detection['scores'] ?? [];
            $meta['classification_cue'] = $detection['cue'] ?? '';
            $meta['margin_gate'] = $detection['margin_gate'] ?? true;
            $meta['margin_gate_reason'] = $detection['margin_gate_reason'] ?? '';
            return $meta;
        }

        $studentAxis = $detection['axis'];
        $counterAxis = $this->pickCounterAxis($axes, $studentAxis['axis_id'], $hints);

        if ($counterAxis === null) {
            return $this->buildMetaAskResponse($axes, $sharedConclusion, $isDecisionInquiry);
        }

        $contrast = $counterAxis['contrast_prompt'] ?? [];
        $distinguishText = $contrast['distinguishes_from'][$studentAxis['axis_id']] ?? '';
        $pivotQuestion = $contrast['pivot_question'] ?? '';
        $namesAxis = $contrast['names_axis'] ?? $counterAxis['axis_label'];
        $toneBlock = $this->warmExplorationToneBlock();

        if ($isDecisionInquiry) {
            $systemPrompt = <<<PROMPT
너는 탐구 코치야. 대상은 만 14세 중학생이야.

이미 벌어진 결정(사실): "{$sharedConclusion}"
학생은 이 결정을 **{$studentAxis['axis_label']}** 관점에서 평가했어.
{$counterAxis['author']}은 같은 결정을 완전히 다른 시각으로 봐:
**{$namesAxis}**

{$distinguishText}

학생에게 물어봐:
{$pivotQuestion}

{$toneBlock}
- "학생이 ~라고 썼어", "우리 둘 다 ~에 동의" 같은 표현 금지 — 이미 벌어진 결정을 평가하는 대화야
- "전쟁이 왜 안 끝나나" 같은 결과 예측 질문 금지
- 축 라벨(tech/politics/structure)이나 "기술적 관점" 같은 메타 라벨은 직접 말하지 마
- 중학생이 쓰는 쉬운 말만. 전문용어·추상어 금지
- 존중하는 말투로, 2~3문장으로
- 마지막 문장은 pivot_question을 자연스럽게 녹여서 학생이 자기 평가 관점을 의식하게 해
PROMPT;
        } else {
            $systemPrompt = <<<PROMPT
너는 탐구 코치야. 학생이 "{$sharedConclusion}"라고 썼어.

학생은 **{$studentAxis['axis_label']}** 관점에서 접근했어.
{$counterAxis['author']}은 같은 결론을 완전히 다른 시각으로 봐:
**{$namesAxis}**

{$distinguishText}

학생에게 물어봐:
{$pivotQuestion}

{$toneBlock}
- 축 라벨(tech/politics/structure)이나 "기술적 관점" 같은 메타 라벨은 직접 말하지 마
- 존중하는 말투로, 2-3문장으로
- 마지막 문장은 pivot_question을 자연스럽게 녹여서 학생이 자기 근거의 층위를 의식하게 해
PROMPT;
        }

        $userMessage = "학생의 근거: \"{$studentReason}\"\n\n1문장 인정은 학생이 실제 말한 이유·키워드를 구체적으로 짚어서. \"그렇게 보는구나\" 같은 형식적 인정은 금지.";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 400, 0.7);

        if (!empty($response['error'])) {
            return $this->buildMetaAskResponse($axes, $sharedConclusion, $isDecisionInquiry);
        }

        return [
            'success' => true,
            'counter_argument' => trim($response['content'] ?? ''),
            'mode' => 'convergent',
            'student_axis' => $studentAxis['axis_id'],
            'counter_axis' => $counterAxis['axis_id'],
            'pivot_question' => $pivotQuestion,
            'classification_scores' => $detection['scores'] ?? [],
            'classification_cue' => $detection['cue'] ?? '',
            'margin_gate' => false,
            'margin_gate_reason' => '',
            'agent' => 'hammer_convergent',
        ];
    }

    /**
     * 학생 답변에서 근거 층위 감지 (결론 매칭 금지 — 무기/정치/전쟁 층위)
     * @return array{
     *   axis: ?array,
     *   confidence: string,
     *   scores: array<string,float>,
     *   cue: string,
     *   margin_gate: bool,
     *   margin_gate_reason: string
     * }
     */
    private function detectStudentAxis(string $reason, array $axes, string $sharedConclusion = '', bool $isDecisionInquiry = false): array
    {
        $empty = [
            'axis' => null,
            'confidence' => 'low',
            'scores' => [],
            'cue' => '',
            'margin_gate' => true,
            'margin_gate_reason' => 'empty_input',
        ];

        if (trim($reason) === '' || count($axes) < 2) {
            return $empty;
        }

        $axisIds = array_map(fn($ax) => $ax['axis_id'], $axes);

        if ($this->isExplicitlyVagueReason($reason)) {
            $vagueScores = [];
            foreach ($axisIds as $id) {
                $vagueScores[$id] = 0.2;
            }
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $vagueScores,
                'cue' => '모호한 표현',
                'margin_gate' => true,
                'margin_gate_reason' => 'vague_no_layer_cue',
            ];
        }
        $axisIdsStr = implode('|', $axisIds);

        $sharedBlock = $sharedConclusion !== ''
            ? ($isDecisionInquiry
                ? "이미 \"{$sharedConclusion}\"라는 결정이 있었다. 분류 기준은 사실 자체가 아니라 \"학생이 그 결정을 어떻게 평가했는지\"의 관점이다.\n\n"
                : "학생은 이미 \"{$sharedConclusion}\"에 동의했다. 분류 기준은 결론이 아니라 \"학생이 왜 그렇다고 했는지\"의 층위다.\n\n")
            : '';

        $layerDefinitions = $this->buildAxisLayerDefinitions($axes, $isDecisionInquiry);

        $scorePairs = [];
        foreach ($axisIds as $id) {
            $scorePairs[] = "\"{$id}\": 0.0";
        }
        $scoresExample = '{' . implode(', ', $scorePairs) . '}';

        $systemPrompt = <<<PROMPT
{$sharedBlock}학생 근거의 층위를 분류해. 전문가 축의 결론과 비슷한지는 보지 마.

층위 정의:
{$layerDefinitions}

JSON만 응답:
{"axis_id": "{$axisIdsStr}", "scores": {$scoresExample}, "confidence": "high|medium|low", "cue": "학생 문장 단서 1개"}
PROMPT;

        $userMessage = "학생 근거: \"{$reason}\"";

        $response = $this->llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 512);

        if (!empty($response['error'])) {
            return $this->detectStudentAxisByKeyword($reason, $axes, $isDecisionInquiry);
        }

        $parsed = $this->parseClassificationJson($response['content'] ?? '', $axisIds);
        if ($parsed === null) {
            return $this->detectStudentAxisByKeyword($reason, $axes, $isDecisionInquiry);
        }

        return $this->finalizeAxisDetection($parsed, $axes);
    }

    /**
     * @param array{axis_id: ?string, scores: array<string,float>, confidence: string, cue: string} $parsed
     */
    private function finalizeAxisDetection(array $parsed, array $axes): array
    {
        $scores = $parsed['scores'];
        $margin = $this->evaluateMarginGate($scores);
        $axisId = $parsed['axis_id'];
        $confidence = $parsed['confidence'];

        if ($margin['triggered']) {
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $scores,
                'cue' => $parsed['cue'],
                'margin_gate' => true,
                'margin_gate_reason' => $margin['reason'],
            ];
        }

        if ($axisId === null || $confidence === 'low') {
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $scores,
                'cue' => $parsed['cue'],
                'margin_gate' => true,
                'margin_gate_reason' => 'low_confidence',
            ];
        }

        foreach ($axes as $ax) {
            if ($ax['axis_id'] === $axisId) {
                return [
                    'axis' => $ax,
                    'confidence' => $confidence,
                    'scores' => $scores,
                    'cue' => $parsed['cue'],
                    'margin_gate' => false,
                    'margin_gate_reason' => '',
                ];
            }
        }

        return [
            'axis' => null,
            'confidence' => 'low',
            'scores' => $scores,
            'cue' => $parsed['cue'],
            'margin_gate' => true,
            'margin_gate_reason' => 'unknown_axis_id',
        ];
    }

    /**
     * @return ?array{axis_id: ?string, scores: array<string,float>, confidence: string, cue: string}
     */
    private function parseClassificationJson(string $content, array $validAxisIds): ?array
    {
        if (!preg_match('/\{[\s\S]*\}/', $content, $m)) {
            return null;
        }

        $parsed = json_decode($m[0], true);
        if (!is_array($parsed)) {
            return null;
        }

        $axisId = $parsed['axis_id'] ?? null;
        if ($axisId === 'null') {
            $axisId = null;
        }

        $rawScores = $parsed['scores'] ?? [];
        $scores = [];
        foreach ($validAxisIds as $id) {
            $scores[$id] = (float) ($rawScores[$id] ?? 0.0);
        }

        if ($axisId !== null && !in_array($axisId, $validAxisIds, true)) {
            $axisId = null;
        }

        return [
            'axis_id' => $axisId,
            'scores' => $scores,
            'confidence' => (string) ($parsed['confidence'] ?? 'medium'),
            'cue' => (string) ($parsed['cue'] ?? ''),
        ];
    }

    /**
     * @param array<string,float> $scores
     * @return array{triggered: bool, reason: string}
     */
    private function evaluateMarginGate(array $scores): array
    {
        if ($scores === []) {
            return ['triggered' => true, 'reason' => 'no_scores'];
        }

        $sorted = $scores;
        arsort($sorted);
        $ids = array_keys($sorted);
        $top = (float) ($sorted[$ids[0]] ?? 0);
        $second = (float) ($sorted[$ids[1]] ?? 0);

        if ($top < 0.55) {
            return ['triggered' => true, 'reason' => "top_score_low:{$top}"];
        }
        if (($top - $second) < 0.20) {
            return ['triggered' => true, 'reason' => 'margin_narrow:' . round($top - $second, 2)];
        }

        return ['triggered' => false, 'reason' => ''];
    }

    /** 그냥/복잡 등 층위 단서 없는 모호 입력 */
    private function isExplicitlyVagueReason(string $reason): bool
    {
        $lower = mb_strtolower($reason);
        $hasVague = str_contains($lower, '그냥')
            || str_contains($lower, '복잡')
            || str_contains($lower, '모르겠')
            || str_contains($lower, '잘 모르');

        if (!$hasVague) {
            return false;
        }

        $layerCues = [
            '폭격', '미사일', '무기', '때리', '버티', '공격', '정권', '트럼프', '바이든',
            '정치', '시작', '끝내', '마음대로', '역사', '원래', '베트남', '구조', '패턴',
        ];
        foreach ($layerCues as $cue) {
            if (str_contains($lower, mb_strtolower($cue))) {
                return false;
            }
        }

        return true;
    }

    /**
     * 키워드 기반 축 감지 (fallback)
     * @return array{axis: ?array, confidence: string, scores: array<string,float>, cue: string, margin_gate: bool, margin_gate_reason: string}
     */
    private function detectStudentAxisByKeyword(string $reason, array $axes, bool $isDecisionInquiry = false): array
    {
        $keywords = $isDecisionInquiry
            ? [
                'tech' => ['미사일', '무기', '공습', '공격', '폭격', '드론', '타격', '정밀', '때리', '버티', '군대', '중국', '대만', '주변', '위험'],
                'politics' => ['정치', '여론', '반응', '사람들', '미국', '동맹', '트럼프', '정권', '대통령', '정부', '부담', '와줄'],
                'structure' => ['나중', '앞으로', '대가', '길어', '이어', '결국', '역사', '원래', '베트남', '패턴', '예전', '바뀌', '10년', '흐름'],
            ]
            : [
                'tech' => ['기술', '정밀', 'AI', '무기', '타격', '드론', '폭격', '미사일', '때리', '때려', '공격', '버티', '세게', '의지', '저항'],
                'politics' => ['정치', '여론', '선거', '정권', '국내', '의회', '대통령', '정부', '행정부', '시작', '끝내', '마음대로', '일관', '트럼프', '바이든', '미국'],
                'structure' => ['구조', '역사', '봉합', '패턴', '반복', '원래', '본질', '항상', '베트남'],
            ];

        $reasonLower = mb_strtolower($reason);
        $rawScores = [];
        $matchedCue = '';

        foreach ($axes as $ax) {
            $axisId = $ax['axis_id'];
            $kws = $keywords[$axisId] ?? [];
            $score = 0;
            foreach ($kws as $kw) {
                if (str_contains($reasonLower, mb_strtolower($kw))) {
                    $score++;
                    if ($matchedCue === '') {
                        $matchedCue = $kw;
                    }
                }
            }
            $rawScores[$axisId] = $score;
        }

        $maxRaw = max($rawScores) ?: 1;
        $scores = [];
        foreach ($rawScores as $id => $s) {
            $scores[$id] = round($s / $maxRaw, 2);
        }

        arsort($rawScores);
        $ids = array_keys($rawScores);
        $topId = $ids[0] ?? null;
        $topScore = $rawScores[$topId] ?? 0;
        $secondScore = $rawScores[$ids[1] ?? ''] ?? 0;

        if ($topScore === 0) {
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $scores,
                'cue' => '',
                'margin_gate' => true,
                'margin_gate_reason' => 'keyword_no_match',
            ];
        }

        if ($topScore === $secondScore) {
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $scores,
                'cue' => $matchedCue,
                'margin_gate' => true,
                'margin_gate_reason' => 'keyword_tie',
            ];
        }

        $margin = $this->evaluateMarginGate($scores);
        if ($margin['triggered']) {
            return [
                'axis' => null,
                'confidence' => 'low',
                'scores' => $scores,
                'cue' => $matchedCue,
                'margin_gate' => true,
                'margin_gate_reason' => $margin['reason'],
            ];
        }

        foreach ($axes as $ax) {
            if ($ax['axis_id'] === $topId) {
                return [
                    'axis' => $ax,
                    'confidence' => $topScore >= 2 ? 'medium' : 'low',
                    'scores' => $scores,
                    'cue' => $matchedCue,
                    'margin_gate' => false,
                    'margin_gate_reason' => '',
                ];
            }
        }

        return [
            'axis' => null,
            'confidence' => 'low',
            'scores' => $scores,
            'cue' => $matchedCue,
            'margin_gate' => true,
            'margin_gate_reason' => 'keyword_unknown',
        ];
    }

    /**
     * 학생 축과 다른 축 선택 — counter_map 우선, 없으면 랜덤
     */
    private function pickCounterAxis(array $axes, string $studentAxisId, array $hints = []): ?array
    {
        $counterMap = $hints['counter_map'] ?? [];
        if (isset($counterMap[$studentAxisId])) {
            $targetId = $counterMap[$studentAxisId];
            foreach ($axes as $ax) {
                if ($ax['axis_id'] === $targetId) {
                    return $ax;
                }
            }
        }

        $candidates = [];
        foreach ($axes as $ax) {
            if ($ax['axis_id'] !== $studentAxisId) {
                $candidates[] = $ax;
            }
        }
        if ($candidates === []) {
            return null;
        }
        return $candidates[array_rand($candidates)];
    }

    /**
     * 안전장치: confidence low 시 메타 질문으로 학생이 직접 축 선택
     */
    private function buildMetaAskResponse(array $axes, string $sharedConclusion, bool $isDecisionInquiry = false): array
    {
        $axisLabels = array_map(fn($ax) => $ax['axis_label'], $axes);
        $options = implode("\n- ", $axisLabels);

        if ($isDecisionInquiry && $sharedConclusion !== '') {
            $message = <<<MSG
네가 쓴 근거를 읽어봤어. "{$sharedConclusion}"라는 결정을 보면서 네 나름대로 생각을 정리한 것 같아.

같은 결정인데, 왜 그랬는지·괜찮았는지는 전문가마다 다르게 봐. 예를 들면:
- {$options}

어느 쪽으로 본 것 같아? 너는 어느 쪽에 더 가깝다고 느껴?
MSG;
        } else {
            $message = <<<MSG
네가 쓴 근거를 읽어봤어. "{$sharedConclusion}" 쪽으로 본 것 같아.

같은 결론인데 '왜' 그런지는 전문가마다 다르게 봐. 예를 들면:
- {$options}

어느 쪽으로 본 것 같아? 너는 어느 쪽에 더 가깝다고 느껴?
MSG;
        }

        return [
            'success' => true,
            'counter_argument' => $message,
            'mode' => 'convergent_meta_ask',
            'agent' => 'hammer_convergent',
        ];
    }

    private function buildAxisLayerDefinitions(array $axes, bool $isDecisionInquiry): string
    {
        if ($isDecisionInquiry) {
            $lines = [];
            foreach ($axes as $ax) {
                $id = $ax['axis_id'] ?? '';
                $label = $ax['axis_label'] ?? $id;
                $namesAxis = $ax['contrast_prompt']['names_axis'] ?? '';
                $detail = $namesAxis !== '' ? " — {$namesAxis}" : '';
                $lines[] = "- {$id}: {$label}{$detail}";
            }
            return implode("\n", $lines);
        }

        return <<<'DEFS'
- tech: 무기·군사수단·공격/타격/폭격·상대 저항력·"때리/버티" (수단 관점)
- politics: 정권·국내정치·정책 일관성·지도자·"시작/끝내/마음대로" (정치 관점)
- structure: 전쟁 보편 패턴·역사·구조·"원래/역사적으로" (무기·정권 없이 일반화)
DEFS;
    }

    /**
     * 수렴형 데이터 불완전 시 대립형 fallback
     */
    private function fallbackToAdversarial(array $quest, array $hints): array
    {
        $fallback = $hints['fallback_adversarial'] ?? [];
        $proLine = $fallback['pro'] ?? ($quest['pro_line'] ?? '');
        $conLine = $fallback['con'] ?? ($quest['con_line'] ?? '');
        $altLine = trim($conLine !== '' ? $conLine : $proLine);
        $wrapped = $altLine !== ''
            ? "네가 말한 것도 일리 있어. 다만 이런 시각도 있어 — {$altLine}"
            : '네가 말한 것도 일리 있어. 다만 다른 사람들은 다르게 보기도 해.';

        return [
            'success' => true,
            'counter_argument' => $wrapped,
            'mode' => 'adversarial_fallback',
            'agent' => 'hammer',
        ];
    }

    /**
     * 기존 대립형 hammer (adversarial)
     */
    private function strikeAdversarial(
        string $stance,
        string $studentReason,
        array $quest,
        array $hints,
        string $intensity,
        ?array $scoreAnalysis,
        string $mixupContext
    ): array {
        $stanceLabel = $stance === 'pro' ? '찬성' : '반대';
        $counterLabel = $stance === 'pro' ? '반대' : '찬성';
        $counterLine = $stance === 'pro' ? ($quest['con_line'] ?? '') : ($quest['pro_line'] ?? '');

        $hintKey = $stance === 'pro' ? 'con' : 'pro';
        $hammerHint = $hints[$hintKey] ?? '';

        $weakPoints = '';
        if (!empty($scoreAnalysis['weak_points'])) {
            $weakPoints = "학생 논거의 약점: " . implode(', ', $scoreAnalysis['weak_points']);
        }

        $intensityGuide = match($intensity) {
            'soft' => '부드럽게, 가능성을 제시하듯',
            'hard' => '핵심을 짚되 존중하는 말투로',
            default => '균형있게, 탐구하듯',
        };

        $toneBlock = $this->warmExplorationToneBlock();

        $systemPrompt = <<<PROMPT
너는 탐구 코치야. 학생이 "{$stanceLabel}" 쪽으로 생각했어.
다른 사람들은 "{$counterLabel}" 쪽으로 보기도 해 — 그 시각을 부드럽게 소개해.

{$toneBlock}
- 학생을 공격하지 말고, 다른 관점을 탐구하게 해
- 사실과 근거에 기반해서 다른 시각을 소개해
- 학생이 "음, 그런 관점도 있네"라고 느끼게 해
- 2-3문장으로 간결하게

다른 시각 방향: {$counterLine}
참고 힌트: {$hammerHint}
{$weakPoints}

다른 매체/시각 차이 (Mix-up, 사실 기반만 사용):
{$mixupContext}

강도: {$intensityGuide}
PROMPT;

        $userMessage = "학생의 입장: {$stanceLabel}\n학생의 이유: {$studentReason}\n\n1문장 인정은 학생이 실제 말한 이유·키워드를 구체적으로 짚어서. \"그렇게 보는구나\" 같은 형식적 인정은 금지. 그다음 다른 시각을 탐구조로 소개해줘.";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 400, 0.8);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'counter_argument' => $counterLine,
                'mode' => 'adversarial',
            ];
        }

        $counterArgument = trim($response['content'] ?? $counterLine);

        return [
            'success' => true,
            'counter_argument' => $counterArgument,
            'counter_stance' => $counterLabel,
            'intensity' => $intensity,
            'mode' => 'adversarial',
            'agent' => 'hammer',
        ];
    }

    public function followUp(string $studentRebuttal, string $originalCounter, array $quest): array
    {
        $systemPrompt = <<<PROMPT
학생이 다른 시각에 대해 재답변했어. 이제 두 가지 중 하나를 해:

1. 학생이 좋은 생각을 했다면: 인정하고, 생각이 깊어졌음을 칭찬해
2. 학생이 회피하거나 약한 답을 했다면: 한 번 더 핵심을 짚어줘 (마지막 기회, 탐구조로)

응답은 2문장 이내로 간결하게. "반론", "토론 상대" 같은 말은 쓰지 마.
PROMPT;

        $userMessage = "다른 시각: {$originalCounter}\n\n학생 재답변: {$studentRebuttal}";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 200, 0.7);

        return [
            'success' => !isset($response['error']),
            'follow_up' => trim($response['content'] ?? '좋은 생각이야. 이제 정리해볼까?'),
        ];
    }
}
