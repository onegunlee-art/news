<?php
/**
 * GIST EDU Agent A5: Writing Builder
 * SCQA 기반 5문장 글 구성 지원
 * 
 * S: Situation (상황)
 * C: Complication (문제/갈등)
 * Q: Question (질문)
 * A: Answer (답/주장)
 * + Conclusion (결론)
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class WritingBuilder
{
    private $llm;

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    public function buildOutline(
        string $finalStance,
        string $finalReason,
        array $reflectionSummary,
        array $quest
    ): array {
        $stanceLabel = $finalStance === 'pro' ? '찬성' : '반대';
        $questTitle = $quest['quest_title'] ?? '';
        $alignmentSummary = $quest['alignment_summary'] ?? '';
        
        $systemPrompt = <<<PROMPT
학생이 "{$stanceLabel}" 입장으로 5문장 글을 쓸 거야. SCQA 구조로 뼈대를 잡아줘.

SCQA 설명:
- S (Situation): 배경/상황 설명
- C (Complication): 왜 이게 문제인지, 충돌점
- Q (Question): 핵심 질문
- A (Answer): 학생의 답/주장
- 결론: 마무리

각 항목을 학생이 채울 수 있는 문장 시작 형태로 제안해.
학생 답변만으로 글이 완성되어야 해 (새 정보 추가 금지).

JSON으로 응답:
{
  "situation_starter": "문장 시작...",
  "complication_starter": "문장 시작...",
  "question_starter": "문장 시작...",
  "answer_starter": "문장 시작...",
  "conclusion_starter": "문장 시작..."
}
PROMPT;

        $userMessage = <<<MSG
퀘스트: {$questTitle}
배경: {$alignmentSummary}
학생 최종 입장: {$stanceLabel}
학생 이유: {$finalReason}
MSG;

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage]
        ], 400, 0.5);

        $defaultOutline = [
            'situation_starter' => '이 주제는... ',
            'complication_starter' => '하지만 논쟁이 있는데... ',
            'question_starter' => '그래서 중요한 질문은... ',
            'answer_starter' => '나는 ' . $stanceLabel . '한다. 왜냐하면... ',
            'conclusion_starter' => '결론적으로... ',
        ];

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'outline' => $defaultOutline,
            ];
        }

        $content = $response['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $parsed = json_decode($match[0], true);
            if (is_array($parsed)) {
                return [
                    'success' => true,
                    'outline' => array_merge($defaultOutline, $parsed),
                    'agent' => 'writing_builder',
                ];
            }
        }

        return [
            'success' => true,
            'outline' => $defaultOutline,
            'agent' => 'writing_builder',
        ];
    }

    public function composeFromParts(array $parts): array
    {
        $situation = trim($parts['situation'] ?? '');
        $complication = trim($parts['complication'] ?? '');
        $question = trim($parts['question'] ?? '');
        $answer = trim($parts['answer'] ?? '');
        $conclusion = trim($parts['conclusion'] ?? '');
        
        $sentences = array_filter([
            $situation,
            $complication,
            $question,
            $answer,
            $conclusion,
        ], fn($s) => $s !== '');
        
        $wordCount = mb_strlen(implode(' ', $sentences));
        
        return [
            'full_text' => implode(' ', $sentences),
            'sentences' => $sentences,
            'word_count' => $wordCount,
            'scqa_parts' => [
                'situation' => $situation,
                'complication' => $complication,
                'question' => $question,
                'answer' => $answer,
                'conclusion' => $conclusion,
            ],
        ];
    }

    public function evaluateWriting(string $fullText, array $quest): array
    {
        $systemPrompt = <<<PROMPT
학생 글을 평가해줘. JSON으로:
{
  "quality_score": 1-100,
  "structure_score": 1-5 (SCQA 구조 잘 따랐는지),
  "argument_clarity": 1-5,
  "feedback": "칭찬 위주 피드백 1-2문장",
  "hero_sentence": "글에서 가장 좋은 문장 하나 (공유카드용)"
}
PROMPT;

        $response = $this->llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => "퀘스트: {$quest['quest_title']}\n\n학생 글:\n{$fullText}"]
        ], 300);

        if (!empty($response['error'])) {
            return [
                'quality_score' => 70,
                'feedback' => '잘 정리했어요!',
                'hero_sentence' => mb_substr($fullText, 0, 50) . '...',
            ];
        }

        $content = $response['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            return json_decode($match[0], true) ?? [
                'quality_score' => 70,
                'feedback' => '잘 정리했어요!',
                'hero_sentence' => mb_substr($fullText, 0, 50) . '...',
            ];
        }

        return [
            'quality_score' => 70,
            'feedback' => '잘 정리했어요!',
            'hero_sentence' => mb_substr($fullText, 0, 50) . '...',
        ];
    }
}
