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
    private const MIN_FULL_CHARS = 350;

    /**
     * @param array<string, mixed> $draft
     * @return array{passed: bool, structure_score: int, violations: list<string>, hints: list<string>}
     */
    public function verify(array $draft): array
    {
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

        $hints = $this->buildRuleHints($fullText);
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
            $hints[] = '[확신_과잉] 단정적 표현을 완화하세요.';
        }
        if (!preg_match('/(하지만|그런데|반면|다른 시각|한편)/u', $text)) {
            $hints[] = '[관점_편향] 반론·다른 시각을 인정하는 문장을 넣으세요.';
        }
        if (!preg_match('/(~거든요|~있어요|~이에요|~해요)/u', $text)) {
            $hints[] = '[톤] the gist 해설체(존댓말)로 통일하세요.';
        }
        return $hints;
    }

    /** @param list<string> $violations @return list<string> */
    private function violationToHints(array $violations): array
    {
        $hints = [];
        foreach ($violations as $v) {
            if (str_contains($v, 'short') || str_contains($v, 'missing') || str_contains($v, 'insufficient')) {
                $hints[] = '제목·소제목 3개 이상·각 섹션 2문단·결론을 충분히 채워주세요.';
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
            $fullText = $composer->renderPlainText($title, $subtitle, $sections, $conclusionHeading, $conclusionParagraphs);
        }

        return array_merge($draft, [
            'title' => $title,
            'subtitle' => $subtitle,
            'sections' => $sections,
            'conclusion_heading' => $conclusionHeading,
            'conclusion_paragraphs' => $conclusionParagraphs,
            'full_text' => $fullText,
            'hero_sentence' => trim((string) ($parsed['hero_sentence'] ?? $draft['hero_sentence'] ?? '')),
            'polished' => true,
        ]);
    }
}
