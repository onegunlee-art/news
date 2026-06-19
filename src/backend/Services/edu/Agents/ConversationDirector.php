<?php
/**
 * GIST EDU — Conversation harness orchestrator
 * Blueprint 완성도 기반 가변 대화 라우팅 (phase budget + depth gate)
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class ConversationDirector
{
    private const MAX_REASON_FOLLOWUPS = 2;
    private const MAX_EXCHANGES = 12;
    /** evidence phase: min chars for a substantive turn (article mention + opinion) */
    private const EVIDENCE_MIN_LEN = 15;

    private $llm;

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    /**
     * @param array<string, mixed> $blueprint
     * @param array<string, mixed> $quest
     * @param array<string, mixed> $eval
     * @return array<string, mixed>
     */
    public function decide(array $blueprint, array $quest, array $eval = []): array
    {
        $phase = (string) ($blueprint['phase'] ?? 'stance');
        $exchangeCount = (int) ($blueprint['exchange_count'] ?? 0);

        if ($exchangeCount >= self::MAX_EXCHANGES) {
            return $this->composeDecision($blueprint, '대화가 충분해졌어. 이제 네 글을 만들어볼게!');
        }

        if ($phase === 'stance') {
            return [
                'next_agent' => 'socratic',
                'action' => 'ask_why',
                'prompt_hint' => '입장을 선택했으니 이유를 물어봐',
                'should_compose' => false,
                'progress_pct' => 10,
            ];
        }

        if ($phase === 'reasoning') {
            $depth = (int) ($eval['depth_score'] ?? ($blueprint['reason_depth'] ?? 3));
            $followups = (int) ($blueprint['reason_followup_count'] ?? 0);
            $needsFollowup = !empty($eval['needs_followup']) && $depth < 3 && $followups < self::MAX_REASON_FOLLOWUPS;

            if ($needsFollowup) {
                return [
                    'next_agent' => 'socratic',
                    'action' => 'followup',
                    'prompt_hint' => '한 가지만 더 깊이 물어봐',
                    'should_compose' => false,
                    'progress_pct' => 25,
                ];
            }

            return [
                'next_agent' => 'socratic',
                'action' => 'ask_evidence',
                'prompt_hint' => '기사를 참고해 근거를 찾아보라고 안내',
                'should_compose' => false,
                'progress_pct' => 35,
                'advance_phase' => 'evidence',
            ];
        }

        if ($phase === 'evidence') {
            $depth = (int) ($eval['depth_score'] ?? 2);
            $evidenceText = trim((string) ($blueprint['evidence'] ?? ''));
            $evidenceLen = mb_strlen($evidenceText);
            $hasEvidence = !empty($eval['has_evidence']);
            $longEnough = $evidenceLen >= self::EVIDENCE_MIN_LEN;

            // Single substantive turn → hammer (no "근거 하나 더" nudge loop).
            // Substantive = min length + (LLM saw article/evidence OR depth ≥ 2).
            if ($longEnough && ($hasEvidence || $depth >= 2)) {
                return [
                    'next_agent' => 'hammer',
                    'action' => 'strike',
                    'prompt_hint' => '반론 제시',
                    'should_compose' => false,
                    'progress_pct' => 55,
                    'advance_phase' => 'hammer',
                ];
            }

            return [
                'next_agent' => 'socratic',
                'action' => 'nudge_evidence',
                'prompt_hint' => $longEnough
                    ? '기사에서 본 구체적 사실과 네 생각을 함께 적어달라고 안내'
                    : '기사에서 본 구체적 사실을 더 적어달라고 안내',
                'should_compose' => false,
                'progress_pct' => 45,
            ];
        }

        if ($phase === 'hammer') {
            if (empty($blueprint['counter_handled'])) {
                return [
                    'next_agent' => 'socratic',
                    'action' => 'await_rebuttal',
                    'prompt_hint' => '반론에 대한 생각을 물어봐',
                    'should_compose' => false,
                    'progress_pct' => 65,
                ];
            }

            return [
                'next_agent' => 'reflection',
                'action' => 'summarize',
                'prompt_hint' => '3줄 정리',
                'should_compose' => false,
                'progress_pct' => 75,
                'advance_phase' => 'reflection',
            ];
        }

        if ($phase === 'reflection') {
            if (empty($blueprint['reflection_confirmed'])) {
                return [
                    'next_agent' => 'reflection',
                    'action' => 'confirm',
                    'prompt_hint' => '정리 확인',
                    'should_compose' => false,
                    'progress_pct' => 85,
                ];
            }

            return $this->composeDecision($blueprint, '좋아! 이제 네 생각을 글로 정리해볼게.');
        }

        return $this->composeDecision($blueprint, '이제 네만의 글을 만들어볼게!');
    }

    /**
     * @param array<string, mixed> $blueprint
     * @return array<string, mixed>
     */
    private function composeDecision(array $blueprint, string $hint): array
    {
        return [
            'next_agent' => 'composer',
            'action' => 'compose',
            'prompt_hint' => $hint,
            'should_compose' => true,
            'progress_pct' => 95,
            'advance_phase' => 'compose',
        ];
    }

    /**
     * Optional Haiku refinement for assistant tone.
     *
     * @param array<string, mixed> $quest
     */
    public function refinePrompt(string $basePrompt, array $quest, int $progressPct): string
    {
        if ($basePrompt === '') {
            return $basePrompt;
        }

        $systemPrompt = <<<PROMPT
중학생 대화 코치. 주어진 문장을 친근하고 간결하게 다듬어 (2문장 이내).
답을 주지 말고 질문/안내만. 질문은 1개만, 물음표 1개만. 전문용어·추상어는 쉬운 말로 바꿔. 물음표를 추가하지 마.
JSON: {"prompt": "..."}
PROMPT;

        $response = $this->llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => "진행률 {$progressPct}%\n퀘스트: {$quest['quest_title']}\n원문: {$basePrompt}"],
        ], 150);

        if (!empty($response['error'])) {
            return $basePrompt;
        }
        $content = $response['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $parsed = json_decode($match[0], true);
            if (!empty($parsed['prompt'])) {
                return trim((string) $parsed['prompt']);
            }
        }
        return $basePrompt;
    }
}
