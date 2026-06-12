<?php
/**
 * GIST EDU Agent A3: Hammer
 * 학생 입장에 대한 설득력 있는 반론 생성
 * 
 * "망치는 깨려고 치는 게 아니라 단단하게 만들려고 친다"
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class Hammer
{
    private $llm;

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
        $stanceLabel = $stance === 'pro' ? '찬성' : '반대';
        $counterLabel = $stance === 'pro' ? '반대' : '찬성';
        $counterLine = $stance === 'pro' ? ($quest['con_line'] ?? '') : ($quest['pro_line'] ?? '');
        
        $hints = $quest['hammer_hints'] ?? [];
        if (is_string($hints)) {
            $hints = json_decode($hints, true) ?? [];
        }
        $hintKey = $stance === 'pro' ? 'con' : 'pro';
        $hammerHint = $hints[$hintKey] ?? '';
        
        $weakPoints = '';
        if (!empty($scoreAnalysis['weak_points'])) {
            $weakPoints = "학생 논거의 약점: " . implode(', ', $scoreAnalysis['weak_points']);
        }
        
        $intensityGuide = match($intensity) {
            'soft' => '부드럽게, 가능성을 제시하듯',
            'hard' => '날카롭게, 핵심을 찌르듯',
            default => '균형있게, 설득력있게',
        };

        $systemPrompt = <<<PROMPT
너는 토론 상대방이야. 학생이 "{$stanceLabel}" 입장을 취했어.
너의 역할은 "{$counterLabel}" 관점에서 강력하고 설득력 있는 반론을 제시하는 거야.

중요 원칙:
- 학생을 공격하지 말고, 논리를 공격해
- 사실과 근거에 기반해서 반론해
- 학생이 "음, 그런 관점도 있네"라고 느끼게 해
- 존중하는 말투를 유지해
- 반론은 2-3문장으로 간결하게

반론 방향: {$counterLine}
참고 힌트: {$hammerHint}
{$weakPoints}

다른 매체/시각 차이 (Mix-up, 사실 기반만 사용):
{$mixupContext}

강도: {$intensityGuide}
PROMPT;

        $userMessage = "학생의 입장: {$stanceLabel}\n학생의 이유: {$studentReason}\n\n반론을 해줘.";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 400, 0.8);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'counter_argument' => $counterLine,
            ];
        }

        $counterArgument = trim($response['content'] ?? $counterLine);

        return [
            'success' => true,
            'counter_argument' => $counterArgument,
            'counter_stance' => $counterLabel,
            'intensity' => $intensity,
            'agent' => 'hammer',
        ];
    }

    public function followUp(string $studentRebuttal, string $originalCounter, array $quest): array
    {
        $systemPrompt = <<<PROMPT
학생이 네 반론에 재답변했어. 이제 두 가지 중 하나를 해:

1. 학생이 좋은 반박을 했다면: 인정하고, 생각이 깊어졌음을 칭찬해
2. 학생이 회피하거나 약한 반박을 했다면: 한 번 더 핵심을 짚어줘 (마지막 기회)

응답은 2문장 이내로 간결하게.
PROMPT;

        $userMessage = "원래 반론: {$originalCounter}\n\n학생 재답변: {$studentRebuttal}";

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 200, 0.7);

        return [
            'success' => !isset($response['error']),
            'follow_up' => trim($response['content'] ?? '좋은 생각이야. 이제 정리해볼까?'),
        ];
    }
}
