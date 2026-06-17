<?php
/**
 * GIST EDU Agent A4: Reflection
 * 학생의 사고 과정을 3줄로 정리
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class Reflection
{
    private $llm;

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    public function summarize(
        string $initialStance,
        string $initialReason,
        string $counterArgument,
        string $studentRebuttal,
        string $finalStance,
        array $quest,
        array $blueprint = []
    ): array {
        $stanceChanged = $initialStance !== $finalStance;
        $changeLabel = $stanceChanged ? '입장을 바꿨어' : '입장을 유지했어';

        $isDecisionInquiry = function_exists('eduIsDecisionInquiryQuest') && eduIsDecisionInquiryQuest($quest);
        $isConvergent = function_exists('eduIsConvergentQuest') && eduIsConvergentQuest($quest);
        $axisLabel = null;

        $axisContext = array_merge($blueprint, [
            'reason' => $initialReason,
            'rebuttal' => $studentRebuttal,
        ]);
        if ($isConvergent && function_exists('eduResolveStudentAxis')) {
            $axis = eduResolveStudentAxis($axisContext, $quest);
            $axisLabel = $axis['axis_label'] ?? null;
        }

        if ($isDecisionInquiry) {
            $initialLabel = function_exists('eduDecisionStanceLabel')
                ? eduDecisionStanceLabel($initialStance, $quest)
                : $initialStance;
            $finalLabel = function_exists('eduDecisionStanceLabel')
                ? eduDecisionStanceLabel($finalStance, $quest)
                : $finalStance;

            $axisHint = $axisLabel !== null && $axisLabel !== ''
                ? "학생이 본 관점(층위): **{$axisLabel}**\n"
                : '';

            $systemPrompt = <<<PROMPT
학생의 토론 과정을 3줄로 정리해줘.

이 퀘스트는 **결정 탐구**다. 같은 사건(결정)에 대해 왜/괜찮았는지 입장이 갈린다.
- **pro, con, 찬성, 반대** 표기 **절대 금지** (입력·출력 모두)
- 학생 초기 입장 라벨: **{$initialLabel}**
- 학생 최종 입장 라벨: **{$finalLabel}**
{$axisHint}- 3줄째: 입장 유지면 "{finalLabel}을/를 지켰어" 느낌, 바뀌었으면 성장을 긍정적으로

형식:
1. 처음에 어떤 입장({$initialLabel})이었는지 + 이유
2. 반론을 듣고 생각한 점
3. 결론 — 입장 유지 또는 변화

말투: 학생에게 직접 "너는 ~" 형식, 각 줄 20자 내외
PROMPT;

            $userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
처음 입장: {$initialLabel}
처음 생각: {$initialReason}
받은 반론: {$counterArgument}
학생 재답변: {$studentRebuttal}
최종 입장: {$finalLabel}
학생이 {$changeLabel}.

3줄 요약을 JSON 배열로 줘: ["첫째줄", "둘째줄", "셋째줄"]
MSG;
        } elseif ($isConvergent && $axisLabel !== null) {
            $systemPrompt = <<<PROMPT
학생의 토론 과정을 3줄로 정리해줘.

이 퀘스트는 수렴형이다. 전문가들은 결론은 같지만 **근거 층위(관점)**가 다르다.
- **pro/con, 찬성/반대, con, pro 표기 절대 금지**
- 학생이 고른 관점: **{$axisLabel}**
- 3줄째는 이 관점을 유지/발전했는지로 마무리 (예: "너는 {$axisLabel} 관점을 더 단단히 했어")

형식:
1. 처음에 어떤 관점({$axisLabel})으로 봤는지
2. 반론(다른 층위)을 듣고 생각한 점
3. 결론 — 관점 유지 또는 더 명확해짐

말투: 학생에게 직접 말하듯, 친근하게 "너는 ~" 형식
각 줄은 20자 내외로 간결하게
PROMPT;

            $userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
학생 관점(축): {$axisLabel}
처음 생각: {$initialReason}
받은 반론(다른 층위): {$counterArgument}
학생 재답변: {$studentRebuttal}

3줄 요약을 JSON 배열로 줘: ["첫째줄", "둘째줄", "셋째줄"]
MSG;
        } else {
            $initialLabel = function_exists('eduStudentStanceLabel')
                ? eduStudentStanceLabel($initialStance, $quest)
                : ($initialStance === 'pro' ? '찬성' : '반대');
            $finalLabel = function_exists('eduStudentStanceLabel')
                ? eduStudentStanceLabel($finalStance, $quest)
                : ($finalStance === 'pro' ? '찬성' : '반대');

            $systemPrompt = <<<PROMPT
학생의 토론 과정을 3줄로 정리해줘.

형식:
1. 처음에 [입장]이었던 이유
2. 반론을 듣고 생각한 점
3. 결론 (입장 변화 여부와 그 이유)

학생이 {$changeLabel}. 이것을 긍정적으로 표현해줘.
입장 변화 = 성장, 입장 유지 = 신념의 강화

말투: 학생에게 직접 말하듯, 친근하게 "너는 ~" 형식으로
각 줄은 20자 내외로 간결하게
PROMPT;

            $userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
초기 입장: {$initialLabel} - {$initialReason}
받은 반론: {$counterArgument}
학생 재답변: {$studentRebuttal}
최종 입장: {$finalLabel}

3줄 요약을 JSON 배열로 줘: ["첫째줄", "둘째줄", "셋째줄"]
MSG;
        }

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ], 300, 0.6);

        if (!empty($response['error'])) {
            $fallback = $this->fallbackSummaryLines(
                $initialStance,
                $finalStance,
                $stanceChanged,
                $axisLabel,
                $quest
            );

            return [
                'success' => false,
                'error' => $response['error'],
                'summary_lines' => $fallback,
            ];
        }

        $content = $response['content'] ?? '';
        $lines = [];

        if (preg_match('/\[[\s\S]*\]/', $content, $match)) {
            $parsed = json_decode($match[0], true);
            if (is_array($parsed) && count($parsed) >= 3) {
                $lines = array_slice($parsed, 0, 3);
            }
        }

        if (empty($lines)) {
            $lines = $this->fallbackSummaryLines(
                $initialStance,
                $finalStance,
                $stanceChanged,
                $axisLabel,
                $quest
            );
        }

        if ($isDecisionInquiry) {
            $lines = $this->sanitizeDecisionLines($lines);
        }

        return [
            'success' => true,
            'summary_lines' => $lines,
            'stance_changed' => $stanceChanged,
            'key_insight' => $lines[1] ?? '',
            'agent' => 'reflection',
        ];
    }

    /** @return list<string> */
    private function fallbackSummaryLines(
        string $initialStance,
        string $finalStance,
        bool $stanceChanged,
        ?string $axisLabel,
        array $quest
    ): array {
        $isDecisionInquiry = function_exists('eduIsDecisionInquiryQuest') && eduIsDecisionInquiryQuest($quest);

        if ($isDecisionInquiry) {
            $initialLabel = eduDecisionStanceLabel($initialStance, $quest);
            $finalLabel = eduDecisionStanceLabel($finalStance, $quest);
            $line1 = ($axisLabel !== null && $axisLabel !== '')
                ? "너는 {$axisLabel} 관점으로, {$initialLabel} 쪽이었어"
                : "너는 {$initialLabel} 쪽이었어";

            return [
                $line1,
                '다른 측면의 반론을 들어봤어',
                $stanceChanged
                    ? "너는 {$finalLabel} 쪽으로 생각이 바뀌었어"
                    : "너는 {$finalLabel}을 지켰어",
            ];
        }

        if ($axisLabel !== null && $axisLabel !== '') {
            return [
                "너는 {$axisLabel} 관점으로 봤어",
                '다른 층위의 반론을 들어봤어',
                $stanceChanged ? '관점이 더 명확해졌어' : "너는 {$axisLabel} 관점을 더 단단히 했어",
            ];
        }

        $initialLabel = function_exists('eduStudentStanceLabel')
            ? eduStudentStanceLabel($initialStance, $quest)
            : ($initialStance === 'pro' ? '찬성' : '반대');

        return [
            "처음엔 {$initialLabel}이었어",
            '다른 관점을 들어봤어',
            $stanceChanged ? '생각이 바뀌었어 - 그건 성장이야!' : '신념이 더 단단해졌어',
        ];
    }

    /** @param list<string> $lines @return list<string> */
    private function sanitizeDecisionLines(array $lines): array
    {
        return array_map(static function (string $line): string {
            $line = preg_replace('/\b(pro|con)\b/ui', '', $line) ?? $line;
            $line = str_replace(['찬성', '반대'], '', $line);

            return trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        }, $lines);
    }

    public function generateReflectionQuestion(string $stance, array $quest): string
    {
        return '반론을 듣고 나서, 네 생각이 어떻게 됐어? ' .
               '입장을 바꾸거나 수정할 부분이 있으면 알려줘.';
    }
}
