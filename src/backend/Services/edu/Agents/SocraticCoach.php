<?php
/**
 * GIST EDU Agent A1: Socratic Coach
 * 학생의 입장과 이유를 심화 질문으로 이끌어냄
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class SocraticCoach
{
    private $llm;

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    public function askWhy(array $quest, string $stance, string $initialReason = ''): array
    {
        $stanceLabel = $stance === 'pro' ? '찬성' : '반대';
        $stanceLine = $stance === 'pro' ? $quest['pro_line'] : $quest['con_line'];
        $alignment = $quest['alignment_summary'] ?? '';
        $conflict = $quest['conflict_summary'] ?? '';

        $systemPrompt = <<<PROMPT
너는 소크라테스식 대화법을 쓰는 교육 코치야. 학생이 "{$stanceLabel}" 입장을 선택했어.

역할:
- 학생의 이유를 더 깊이 파고드는 질문을 해
- 답을 주지 말고, 학생이 스스로 생각하게 유도해
- 친근하고 격려하는 말투를 써
- 질문은 1개만, 간결하게 (2문장 이내)
- Foreign Affairs·Economist 수준의 사고를 끌어내는 질문을 해

학생 입장: {$stanceLabel} - {$stanceLine}
배경(일치): {$alignment}
갈등(불일치): {$conflict}
PROMPT;

        $userMessage = empty($initialReason) 
            ? "학생이 \"{$stanceLabel}\" 입장을 선택했어. 왜 그런지 물어봐줘."
            : "학생이 이유로 \"{$initialReason}\"라고 답했어. 더 깊이 파고드는 후속 질문을 해줘.";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 256, 0.7);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'question' => '왜 그렇게 생각해요?',
            ];
        }

        return [
            'success' => true,
            'question' => trim($response['content'] ?? '왜 그렇게 생각해요?'),
            'agent' => 'socratic_coach',
        ];
    }

    public function evaluateReason(string $stance, string $reason, array $quest): array
    {
        return $this->evaluateResponse($stance, $reason, $quest, 'reason');
    }

    public function evaluateResponse(string $stance, string $response, array $quest, string $phase = 'reason'): array
    {
        $phaseLabel = match ($phase) {
            'evidence' => '근거',
            'rebuttal' => '반론에 대한 재답변',
            default => '이유',
        };

        $systemPrompt = <<<PROMPT
학생의 답변을 평가해. JSON으로 응답해:
{
  "depth_score": 1-5 (1=피상적, 5=깊이있음),
  "has_evidence": true/false,
  "clarity": 1-5,
  "needs_followup": true/false,
  "feedback_hint": "간단한 피드백 한 줄"
}
PROMPT;

        $userMessage = "퀘스트: {$quest['quest_title']}\n학생 입장: {$stance}\n평가 대상({$phaseLabel}): {$response}";

        $response = $this->llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 200);

        if (!empty($response['error'])) {
            return [
                'depth_score' => 3,
                'has_evidence' => false,
                'clarity' => 3,
                'needs_followup' => false,
            ];
        }

        $content = $response['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            return json_decode($match[0], true) ?? [
                'depth_score' => 3,
                'has_evidence' => false,
                'clarity' => 3,
                'needs_followup' => false,
            ];
        }

        return [
            'depth_score' => 3,
            'has_evidence' => false,
            'clarity' => 3,
            'needs_followup' => false,
        ];
    }
}
