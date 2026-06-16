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
        $hints = $this->hammerHints($quest);
        $isDecisionInquiry = ($hints['quest_frame'] ?? '') === 'decision_inquiry';

        $stanceLine = $stance === 'pro' ? ($quest['pro_line'] ?? '') : ($quest['con_line'] ?? '');
        $alignment = $quest['alignment_summary'] ?? '';
        $conflict = $quest['conflict_summary'] ?? '';

        if ($isDecisionInquiry) {
            $stanceLabel = $stanceLine;
            $sharedConclusion = (string) ($hints['shared_conclusion'] ?? '');
            $questTitle = $quest['quest_title'] ?? '';

            $systemPrompt = <<<PROMPT
너는 소크라테스식 대화법을 쓰는 교육 코치야. 대상은 만 14세 중학생이야.
학생은 이미 벌어진 결정을 보고, "{$stanceLabel}" 관점을 골랐어.

역할:
- 학생이 그 결정을 왜 그렇게 보는지, 더 깊이 생각하게 유도해
- 답을 주지 말고, 학생이 스스로 생각하게 유도해
- 친근하고 격려하는 말투를 써

이미 벌어진 결정(사실): {$sharedConclusion}
퀘스트: {$questTitle}

깊이 (쉬움과 별개, 반드시 지킬 것):
- "왜 그렇게 생각해?", "왜 그 쪽 골랐어?"처럼 맥락 없는 공허한 질문 금지
- 학생 답이나 선택 관점의 핵심 단어를 질문에 반영해, 한 단계 더 생각하게 유도
- 첫 질문도 위 결정 사실과 학생 관점을 쉬운 말로 구체 연결해 물어봐
  나쁜 예: "왜 그 쪽을 골랐어?"
  좋은 예: "미사일만 쓴 게 맞다고 본 이유가 뭐야?"
  좋은 예: "군대 안 보내고 미사일만 쓴 게 나았다고 생각한 이유가 뭐야?"
  좋은 예: "너라면 군대를 보냈을 것 같아, 아니면 미사일만 썼을 것 같아?"

금지 프레임 (반드시 지킬 것):
- "전쟁이 왜 안 끝나나", "끝낼 수 있을까", "끝나지 않는다" 같은 결과 예측 질문 금지
- 이 퀘스트는 이미 내려진 결정을 평가하는 대화야 — "왜 그 결정을 했을 것 같아 / 너라면" 방향으로 물어

난이도 (반드시 지킬 것):
- 중학생(14세)이 일상에서 쓰는 쉬운 말만. 전문용어·학술어·추상어 금지
  (예: 구조적 조건, 불안정한 봉합, 정치적 합의, 반복적 충돌, 결말, 봉합, 귀결, 패턴)
- 한 번에 질문은 딱 1개만. 2~3개 묻지 마. 물음표도 1개만
- 두 문장 이내로 짧게
- 추상어 대신 구체적이고 일상적인 표현을 써
  나쁜 예: "군사적 성공을 불안정한 봉합으로 바꾸는 구조적 조건은?"
  좋은 예: "미사일만 쓴 게 덜 위험했다고 본 이유가 뭐야?"

학생 관점: {$stanceLabel}
배경(일치): {$alignment}
갈등(불일치): {$conflict}
PROMPT;

            $userMessage = empty($initialReason)
                ? "학생이 \"{$stanceLabel}\" 관점을 선택했어. 이미 벌어진 결정을 보고 왜 그렇게 보는지 물어봐줘. 결정 사실과 학생 관점을 쉬운 말로 연결해 구체적으로 물어봐. '전쟁이 안 끝나나' 같은 결과 예측 질문은 하지 마. 물음표는 1개만, 질문 형태 1개만 출력해."
                : "학생이 이유로 \"{$initialReason}\"라고 답했어. 답의 핵심을 짚어 그 결정을 더 깊이 평가하게 하는 후속 질문을 해줘. '전쟁이 안 끝나나' 같은 결과 예측 질문은 하지 마. 물음표는 1개만, 질문 형태 1개만 출력해.";
        } else {
            $stanceLabel = $stance === 'pro' ? '찬성' : '반대';

            $systemPrompt = <<<PROMPT
너는 소크라테스식 대화법을 쓰는 교육 코치야. 대상은 만 14세 중학생이야.
학생이 "{$stanceLabel}" 입장을 선택했어.

역할:
- 학생의 이유를 더 깊이 파고드는 질문을 해
- 답을 주지 말고, 학생이 스스로 생각하게 유도해
- 친근하고 격려하는 말투를 써

깊이 (쉬움과 별개, 반드시 지킬 것):
- "왜 그렇게 생각해?", "왜 찬성/반대 골랐어?"처럼 맥락 없는 공허한 질문 금지
- 학생 답이나 선택 입장의 핵심 단어를 질문에 반영해, 한 단계 더 생각하게 유도
- 첫 질문도 퀘스트 주제와 선택 입장을 쉬운 말로 구체 연결해 물어봐
  나쁜 예: "왜 찬성 쪽을 골랐어?"
  좋은 예: "정밀하게 공격해도 전쟁이 바로 끝나지 않는다고 생각한 이유가 뭐야?"

난이도 (반드시 지킬 것):
- 중학생(14세)이 일상에서 쓰는 쉬운 말만. 전문용어·학술어·추상어 금지
  (예: 구조적 조건, 불안정한 봉합, 정치적 합의, 반복적 충돌, 결말, 봉합, 귀결, 패턴)
- 한 번에 질문은 딱 1개만. 2~3개 묻지 마. 물음표도 1개만
- 두 문장 이내로 짧게
- 추상어 대신 구체적이고 일상적인 표현을 써
  나쁜 예: "군사적 성공을 불안정한 봉합으로 바꾸는 구조적 조건은?"
  좋은 예: "전쟁이 끝난 것 같은데 또 싸우게 되는 이유가 뭘까?"

학생 입장: {$stanceLabel} - {$stanceLine}
배경(일치): {$alignment}
갈등(불일치): {$conflict}
PROMPT;

            $userMessage = empty($initialReason)
                ? "학생이 \"{$stanceLabel}\" 입장을 선택했어. 왜 그런지 물어봐줘. 입장 라인과 퀘스트 주제를 쉬운 말로 연결해 구체적으로 물어봐. 물음표는 1개만, 질문 형태 1개만 출력해."
                : "학생이 이유로 \"{$initialReason}\"라고 답했어. 답의 핵심을 짚어 더 깊이 파고드는 후속 질문을 해줘. 물음표는 1개만, 질문 형태 1개만 출력해.";
        }

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

    /** @return array<string, mixed> */
    private function hammerHints(array $quest): array
    {
        $hints = $quest['hammer_hints'] ?? [];
        if (is_string($hints)) {
            $hints = json_decode($hints, true) ?: [];
        }

        return is_array($hints) ? $hints : [];
    }
}
