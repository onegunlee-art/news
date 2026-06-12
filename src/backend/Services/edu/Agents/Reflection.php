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
        array $quest
    ): array {
        $stanceChanged = $initialStance !== $finalStance;
        $changeLabel = $stanceChanged ? '입장을 바꿨어' : '입장을 유지했어';
        
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
초기 입장: {$initialStance} - {$initialReason}
받은 반론: {$counterArgument}
학생 재답변: {$studentRebuttal}
최종 입장: {$finalStance}

3줄 요약을 JSON 배열로 줘: ["첫째줄", "둘째줄", "셋째줄"]
MSG;

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 300, 0.6);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'summary_lines' => [
                    "처음엔 " . ($initialStance === 'pro' ? '찬성' : '반대') . "이었어",
                    "다른 관점을 들어봤어",
                    $stanceChanged ? "생각이 바뀌었어 - 그건 성장이야!" : "신념이 더 단단해졌어"
                ],
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
            $lines = [
                "처음엔 " . ($initialStance === 'pro' ? '찬성' : '반대') . "이었어",
                "다른 관점을 들어봤어",
                $stanceChanged ? "생각이 바뀌었어 - 그건 성장이야!" : "신념이 더 단단해졌어"
            ];
        }

        return [
            'success' => true,
            'summary_lines' => $lines,
            'stance_changed' => $stanceChanged,
            'key_insight' => $lines[1] ?? '',
            'agent' => 'reflection',
        ];
    }

    public function generateReflectionQuestion(string $stance, array $quest): string
    {
        $counterLine = $stance === 'pro' 
            ? ($quest['con_line'] ?? '반대 입장')
            : ($quest['pro_line'] ?? '찬성 입장');
        
        return "반론을 듣고 나서, 네 생각이 어떻게 됐어? " .
               "입장을 바꾸거나 수정할 부분이 있으면 알려줘.";
    }
}
