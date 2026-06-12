<?php
/**
 * GIST EDU — student essay verify + 1x polish (StrategicReport pattern, 축소판)
 */
declare(strict_types=1);

namespace Services\Edu;

class EduWritingGate
{
    private const MIN_PART_CHARS = 15;
    private const MIN_FULL_CHARS = 120;

    /**
     * @param array<string, mixed> $draft
     * @return array{passed: bool, structure_score: int, violations: list<string>, hints: list<string>}
     */
    public function verify(array $draft): array
    {
        $parts = $draft['scqa_parts'] ?? [];
        $violations = [];
        $filled = 0;

        foreach (['situation', 'complication', 'question', 'answer', 'conclusion'] as $key) {
            $text = trim((string) ($parts[$key] ?? ''));
            if ($text === '') {
                $violations[] = "scqa_{$key}_missing";
                continue;
            }
            $filled++;
            if (mb_strlen($text) < self::MIN_PART_CHARS) {
                $violations[] = "scqa_{$key}_short";
            }
        }

        $fullText = trim((string) ($draft['full_text'] ?? ''));
        if (mb_strlen($fullText) < self::MIN_FULL_CHARS) {
            $violations[] = 'full_text_short';
        }

        $hints = $this->buildRuleHints($fullText);
        $structureScore = $filled >= 5 && empty(array_filter($violations, fn ($v) => str_contains($v, 'short') || str_contains($v, 'missing')))
            ? 5
            : max(1, min(4, $filled));

        if ($hints !== []) {
            $structureScore = max(1, $structureScore - 1);
        }

        return [
            'passed' => $violations === [] && $structureScore >= 3,
            'structure_score' => $structureScore,
            'violations' => $violations,
            'hints' => array_merge($hints, $this->violationToHints($violations)),
        ];
    }

    /** @return list<string> */
    private function buildRuleHints(string $text): array
    {
        $hints = [];
        if ($text === '') {
            return $hints;
        }
        if (preg_match('/(반드시|확실|틀림없|무조건)/u', $text)) {
            $hints[] = '[확신_과잉] 단정적 표현 대신 "나는 ~라고 생각해"처럼 써보세요.';
        }
        if (!preg_match('/(왜냐하면|때문에|그래서|따라서)/u', $text)) {
            $hints[] = '[인과_비약] 이유와 결론을 연결하는 문장을 한 줄 넣어보세요.';
        }
        if (!preg_match('/(하지만|그런데|반면|다른 시각)/u', $text)) {
            $hints[] = '[관점_편향] 반론을 인정하는 한 문장을 넣으면 더 설득력 있어요.';
        }
        return $hints;
    }

    /** @param list<string> $violations @return list<string> */
    private function violationToHints(array $violations): array
    {
        $hints = [];
        foreach ($violations as $v) {
            if (str_contains($v, 'short') || str_contains($v, 'missing')) {
                $hints[] = 'SCQA 다섯 슬롯을 각각 한 문장 이상 채워주세요.';
                break;
            }
        }
        return $hints;
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<string> $hints
     * @param array<string, mixed> $quest
     * @return array<string, mixed>
     */
    public function polish($llm, array $draft, array $hints, array $quest): array
    {
        if ($hints === []) {
            return $draft;
        }

        $hintBlock = implode("\n", array_slice($hints, 0, 5));
        $systemPrompt = <<<PROMPT
학생 글을 SCQA 5문장 구조로 다듬어. 학생의 입장과 근거는 유지하고 표현만 개선해.
새로운 사실을 추가하지 마.

JSON으로 응답:
{
  "situation": "...",
  "complication": "...",
  "question": "...",
  "answer": "...",
  "conclusion": "...",
  "full_text": "...",
  "hero_sentence": "..."
}
PROMPT;

        $userMessage = "퀘스트: {$quest['quest_title']}\n\n현재 글:\n" . ($draft['full_text'] ?? '')
            . "\n\n수정 힌트:\n{$hintBlock}";

        $response = $llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ], 600);

        if (!empty($response['error'])) {
            return $draft;
        }

        $content = $response['content'] ?? '';
        if (!preg_match('/\{[\s\S]*\}/', $content, $match)) {
            return $draft;
        }
        $parsed = json_decode($match[0], true);
        if (!is_array($parsed)) {
            return $draft;
        }

        $parts = [
            'situation' => trim((string) ($parsed['situation'] ?? $draft['scqa_parts']['situation'] ?? '')),
            'complication' => trim((string) ($parsed['complication'] ?? $draft['scqa_parts']['complication'] ?? '')),
            'question' => trim((string) ($parsed['question'] ?? $draft['scqa_parts']['question'] ?? '')),
            'answer' => trim((string) ($parsed['answer'] ?? $draft['scqa_parts']['answer'] ?? '')),
            'conclusion' => trim((string) ($parsed['conclusion'] ?? $draft['scqa_parts']['conclusion'] ?? '')),
        ];

        return [
            'full_text' => trim((string) ($parsed['full_text'] ?? implode(' ', array_filter($parts)))),
            'scqa_parts' => $parts,
            'hero_sentence' => trim((string) ($parsed['hero_sentence'] ?? $draft['hero_sentence'] ?? '')),
            'polished' => true,
        ];
    }
}
