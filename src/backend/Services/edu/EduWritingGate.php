<?php
/**
 * GIST EDU — structured essay verify + 1x polish
 */
declare(strict_types=1);

namespace Services\Edu;

use Services\Edu\Agents\GistStyleComposer;

class EduWritingGate
{
    private const MIN_SECTION_PARA_CHARS = 40;
    private const MIN_NARRATION_PARA_CHARS = 30;
    private const MIN_FULL_CHARS = 350;

    /**
     * @param array<string, mixed> $draft
     * @return array{passed: bool, structure_score: int, violations: list<string>, hints: list<string>}
     */
    public function verify(array $draft): array
    {
        if ($this->isNarrationDraft($draft)) {
            return $this->verifyNarration($draft);
        }

        $violations = [];
        $title = trim((string) ($draft['title'] ?? ''));
        if ($title === '') {
            $violations[] = 'title_missing';
        }

        $sections = $draft['sections'] ?? [];
        if (!is_array($sections) || count($sections) < 3) {
            $violations[] = 'sections_insufficient';
        } else {
            foreach ($sections as $i => $sec) {
                if (!is_array($sec)) {
                    continue;
                }
                if (trim((string) ($sec['heading'] ?? '')) === '') {
                    $violations[] = "section_{$i}_heading_missing";
                }
                $paragraphs = $sec['paragraphs'] ?? [];
                $paraText = is_array($paragraphs) ? implode(' ', $paragraphs) : (string) $paragraphs;
                if (mb_strlen(trim($paraText)) < self::MIN_SECTION_PARA_CHARS) {
                    $violations[] = "section_{$i}_short";
                }
            }
        }

        $conclusion = $draft['conclusion_paragraphs'] ?? [];
        $conclusionText = is_array($conclusion) ? implode(' ', $conclusion) : (string) $conclusion;
        if (mb_strlen(trim($conclusionText)) < self::MIN_SECTION_PARA_CHARS) {
            $violations[] = 'conclusion_short';
        }

        $fullText = trim((string) ($draft['full_text'] ?? ''));
        if (mb_strlen($fullText) < self::MIN_FULL_CHARS) {
            $violations[] = 'full_text_short';
        }

        $hints = $this->buildRuleHints($fullText, false);
        $structureScore = 5;
        if (count($violations) > 2) {
            $structureScore = 2;
        } elseif ($violations !== []) {
            $structureScore = 3;
        }
        if ($hints !== []) {
            $structureScore = max(1, $structureScore - 1);
        }

        return [
            'passed' => $violations === [] && $structureScore >= 3,
            'structure_score' => $structureScore,
            'violations' => $violations,
            'hints' => array_merge($hints, $this->violationToHints($violations, false)),
        ];
    }

    /**
     * @param array<string, mixed> $draft
     * @return array{passed: bool, structure_score: int, violations: list<string>, hints: list<string>}
     */
    private function verifyNarration(array $draft): array
    {
        $violations = [];
        $title = trim((string) ($draft['title'] ?? ''));
        if ($title === '') {
            $violations[] = 'title_missing';
        }

        $paragraphs = $draft['body_paragraphs'] ?? [];
        if (!is_array($paragraphs)) {
            $paragraphs = [];
        }
        $paragraphs = array_values(array_filter(array_map(static fn ($p) => trim((string) $p), $paragraphs)));
        if (count($paragraphs) < 2 || count($paragraphs) > 3) {
            $violations[] = 'body_paragraphs_count';
        }
        foreach ($paragraphs as $i => $p) {
            if (mb_strlen($p) < self::MIN_NARRATION_PARA_CHARS) {
                $violations[] = "body_paragraph_{$i}_short";
            }
        }

        $fullText = trim((string) ($draft['full_text'] ?? ''));
        if (mb_strlen($fullText) < self::MIN_FULL_CHARS) {
            $violations[] = 'full_text_short';
        }

        $hints = $this->buildRuleHints($fullText, true);
        $structureScore = $violations === [] ? 5 : (count($violations) > 2 ? 2 : 3);
        if ($hints !== []) {
            $structureScore = max(1, $structureScore - 1);
        }

        return [
            'passed' => $violations === [] && $structureScore >= 3,
            'structure_score' => $structureScore,
            'violations' => $violations,
            'hints' => array_merge($hints, $this->violationToHints($violations, true)),
        ];
    }

    /** @param array<string, mixed> $draft */
    private function isNarrationDraft(array $draft): bool
    {
        if (!empty($draft['narration_mode'])) {
            return true;
        }
        $paragraphs = $draft['body_paragraphs'] ?? null;

        return is_array($paragraphs) && count($paragraphs) >= 2;
    }

    /** @return list<string> */
    private function buildRuleHints(string $text, bool $narration): array
    {
        $hints = [];
        if ($text === '') {
            return $hints;
        }
        if (preg_match('/(반드시|확실|틀림없|무조건)/u', $text)) {
            $hints[] = '[확신_과잉] 단정적 표현을 완화하세요.';
        }
        if (!preg_match('/(하지만|그런데|반면|다른 시각|한편|물론|다만)/u', $text)) {
            $hints[] = '[관점_편향] 반론·다른 시각을 인정하는 문장을 넣으세요.';
        }
        if (!$narration && !preg_match('/(~거든요|~있어요|~이에요|~해요)/u', $text)) {
            $hints[] = '[톤] the gist 해설체(존댓말)로 통일하세요.';
        }

        return $hints;
    }

    /** @param list<string> $violations @return list<string> */
    private function violationToHints(array $violations, bool $narration): array
    {
        $hints = [];
        foreach ($violations as $v) {
            if (str_contains($v, 'short') || str_contains($v, 'missing') || str_contains($v, 'insufficient') || str_contains($v, 'body_paragraphs')) {
                $hints[] = $narration
                    ? '제목과 2~3개 본문 단락을 학생 말투로 충분히 채워주세요. 소제목은 넣지 마세요.'
                    : '제목·소제목 3개 이상·각 섹션 2문단·결론을 충분히 채워주세요.';
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

        if ($this->isNarrationDraft($draft)) {
            return $this->polishNarration($llm, $draft, $hints, $quest);
        }

        $hintBlock = implode("\n", array_slice($hints, 0, 5));
        $systemPrompt = <<<PROMPT
the gist 스타일 학생 해설 글을 다듬어. 구조(제목·소제목·결론)는 유지하고 표현만 개선해.
학생 입장과 대화 내용은 유지, 새 사실 추가 금지.

JSON:
{
  "title": "...",
  "subtitle": "...",
  "sections": [{"heading": "...", "paragraphs": ["...", "..."]}],
  "conclusion_heading": "결론",
  "conclusion_paragraphs": ["..."],
  "full_text": "...",
  "hero_sentence": "..."
}
PROMPT;

        $userMessage = "퀘스트: {$quest['quest_title']}\n\n현재 글:\n" . ($draft['full_text'] ?? '')
            . "\n\n수정 힌트:\n{$hintBlock}";

        $response = $llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ], 1200);

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

        return $this->mergePolishedDraft($draft, $parsed, $llm);
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<string> $hints
     * @param array<string, mixed> $quest
     * @return array<string, mixed>
     */
    private function polishNarration($llm, array $draft, array $hints, array $quest): array
    {
        $hintBlock = implode("\n", array_slice($hints, 0, 5));
        $systemPrompt = <<<PROMPT
학생 1인칭 내래이션 글을 다듬어. **소제목 금지**, 2~3개 연속 단락 유지.
학생 말투·표현은 살리고, 새 사실·the gist 결론 주입 금지. 접속어로만 매끄럽게.

JSON:
{
  "title": "...",
  "subtitle": "...",
  "body_paragraphs": ["...", "..."],
  "sections": [],
  "conclusion_heading": "",
  "conclusion_paragraphs": [],
  "full_text": "...",
  "hero_sentence": "...",
  "narration_mode": true
}
PROMPT;

        $userMessage = "퀘스트: {$quest['quest_title']}\n\n현재 글:\n" . ($draft['full_text'] ?? '')
            . "\n\n수정 힌트:\n{$hintBlock}";

        $response = $llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ], 1200);

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

        $merged = $this->mergePolishedDraft($draft, $parsed, $llm);
        $merged['narration_mode'] = true;
        if (empty($merged['body_paragraphs']) && !empty($draft['body_paragraphs'])) {
            $merged['body_paragraphs'] = $draft['body_paragraphs'];
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    private function mergePolishedDraft(array $draft, array $parsed, $llm): array
    {
        $composer = new GistStyleComposer($llm);
        $sections = [];
        foreach ($parsed['sections'] ?? $draft['sections'] ?? [] as $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $paragraphs = $sec['paragraphs'] ?? [];
            if (!is_array($paragraphs)) {
                $paragraphs = [$paragraphs];
            }
            $sections[] = [
                'heading' => trim((string) ($sec['heading'] ?? '')),
                'paragraphs' => array_values(array_filter(array_map('trim', array_map('strval', $paragraphs)))),
            ];
        }

        $bodyParagraphs = [];
        foreach ($parsed['body_paragraphs'] ?? $draft['body_paragraphs'] ?? [] as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $bodyParagraphs[] = $t;
            }
        }

        $conclusionParagraphs = $parsed['conclusion_paragraphs'] ?? $draft['conclusion_paragraphs'] ?? [];
        if (!is_array($conclusionParagraphs)) {
            $conclusionParagraphs = [$conclusionParagraphs];
        }

        $title = trim((string) ($parsed['title'] ?? $draft['title'] ?? ''));
        $subtitle = trim((string) ($parsed['subtitle'] ?? $draft['subtitle'] ?? ''));
        $conclusionHeading = trim((string) ($parsed['conclusion_heading'] ?? $draft['conclusion_heading'] ?? '결론'));
        $conclusionParagraphs = array_values(array_filter(array_map('trim', array_map('strval', $conclusionParagraphs))));

        $fullText = trim((string) ($parsed['full_text'] ?? ''));
        if ($fullText === '') {
            if ($bodyParagraphs !== []) {
                $fullText = $composer->renderNarrationPlainText($title, $subtitle, $bodyParagraphs);
            } else {
                $fullText = $composer->renderPlainText($title, $subtitle, $sections, $conclusionHeading, $conclusionParagraphs);
            }
        }

        return array_merge($draft, [
            'title' => $title,
            'subtitle' => $subtitle,
            'sections' => $sections,
            'body_paragraphs' => $bodyParagraphs,
            'conclusion_heading' => $conclusionHeading,
            'conclusion_paragraphs' => $conclusionParagraphs,
            'full_text' => $fullText,
            'hero_sentence' => trim((string) ($parsed['hero_sentence'] ?? $draft['hero_sentence'] ?? '')),
            'polished' => true,
        ]);
    }
}
