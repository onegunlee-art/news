<?php
/**
 * GIST EDU — 대화 구조도 → the gist 스타일 장문 새로 작성
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

use Services\Edu\EduLlmJson;

use Services\Edu\EduRagService;
use Services\Edu\GistNarrationReader;

class GistStyleComposer
{
    private const STRUCTURE_MAX_TOKENS = 2000;
    private const STRUCTURE_RETRY_MAX_TOKENS = 2000;
    private const ARTICLE_MAX_TOKENS = 6000;

    private $llm;
    private EduRagService $rag;
    private GistNarrationReader $narration;

    public function __construct($llmClient, ?EduRagService $rag = null, ?GistNarrationReader $narration = null)
    {
        $this->llm = $llmClient;
        $this->rag = $rag ?? new EduRagService();
        $this->narration = $narration ?? new GistNarrationReader();
    }

    /**
     * @param array<string, mixed> $blueprint
     * @param array<string, mixed> $quest
     * @param list<array<string, mixed>> $dialogue
     * @return array<string, mixed>
     */
    /**
     * 대화 종료 직전 구조도만 생성 (compose Step1 단독)
     *
     * @param array<string, mixed> $blueprint
     * @param array<string, mixed> $quest
     * @param list<array<string, mixed>> $dialogue
     * @return array<string, mixed>
     */
    public function previewStructure(array $blueprint, array $quest, array $dialogue = []): array
    {
        $context = $this->buildContext($blueprint, $quest, $dialogue);
        return $this->buildStructureDiagram($context);
    }

    public function compose(array $blueprint, array $quest, array $dialogue = []): array
    {
        $context = $this->buildContext($blueprint, $quest, $dialogue);
        $existing = $blueprint['essay_structure'] ?? [];
        if ($this->isLlmGeneratedStructure($existing)) {
            $structure = $existing;
        } else {
            $structure = $this->buildStructureDiagram($context);
        }
        if ($this->isComposeFailure($structure)) {
            return $structure;
        }

        $article = $this->composeArticleFromStructure($structure, $context);
        if ($this->isComposeFailure($article)) {
            return $article;
        }

        return array_merge($article, [
            'success' => true,
            'essay_structure' => $structure,
            'agent' => 'gist_style_composer',
            'scqa_parts' => $this->structureToScqa($structure),
        ]);
    }

    /**
     * @param array<string, mixed> $blueprint
     * @param array<string, mixed> $quest
     * @param list<array<string, mixed>> $dialogue
     * @return array<string, mixed>
     */
    private function buildContext(array $blueprint, array $quest, array $dialogue): array
    {
        $stance = (string) ($blueprint['final_stance'] ?? $blueprint['stance'] ?? 'pro');
        $newsIds = [];
        foreach ($quest['articles'] ?? [] as $article) {
            $nid = (int) ($article['news_id'] ?? 0);
            if ($nid > 0) {
                $newsIds[] = $nid;
            }
        }

        $arc = $this->rag->findArcArticles($newsIds, (string) ($quest['conflict_summary'] ?? ''));
        $narrationBlock = $this->narration->formatFewShot($this->narration->readExcerpts($newsIds, 1200));

        $patterns = array_merge(
            $this->rag->getWritingPatterns((string) ($quest['quest_title'] ?? ''), 3),
            $this->rag->getJudgementPatterns(3)
        );

        $dialogueText = '';
        foreach ($dialogue as $turn) {
            if (($turn['role'] ?? '') !== 'student') {
                continue;
            }
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $dialogueText .= "학생: {$content}\n";
        }

        $reflection = $blueprint['reflection_lines'] ?? [];
        if (!is_array($reflection)) {
            $reflection = [];
        }

        return [
            'stance' => $stance,
            'stance_label' => $stance === 'pro' ? '찬성' : '반대',
            'reason' => (string) ($blueprint['reason'] ?? ''),
            'evidence' => (string) ($blueprint['evidence'] ?? ''),
            'rebuttal' => (string) ($blueprint['rebuttal'] ?? ''),
            'counter_argument' => (string) ($blueprint['counter_argument'] ?? ''),
            'reflection_lines' => $reflection,
            'dialogue_text' => $dialogueText,
            'quest' => $quest,
            'narration_block' => $narrationBlock,
            'arc_alignment' => $arc['alignment'] ?? '',
            'judgment_block' => $patterns !== [] ? $this->rag->formatWritingPatterns($patterns) : '',
        ];
    }

    /**
     * Step 1: 대화에서 글 구조도 추출
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function buildStructureDiagram(array $ctx): array
    {
        $quest = $ctx['quest'];
        $reflectionText = implode("\n", array_map('strval', $ctx['reflection_lines'] ?? []));

        $systemPrompt = <<<PROMPT
너는 the gist 편집장이다. 학생과의 대화를 바탕으로 **글 구조도**만 만든다.
학생이 말한 생각·근거·반론 반응을 섹션별로 배치하되, 아직 본문은 쓰지 않는다.

구조도 규칙:
- title: 학생 시각이 드러나는 제목 (the gist 헤드라인 톤)
- subtitle: 한 줄 핵심 요약
- sections: 3~4개, 각각 heading(소제목) + bullets(이 섹션에 넣을 핵심 생각 2~3개, 학생 발화 기반)
- conclusion_heading: "결론" 또는 맥락에 맞는 마무리 제목
- conclusion_bullets: 결론에 담을 핵심 2~3개

JSON만 응답:
{
  "title": "...",
  "subtitle": "...",
  "sections": [
    {"heading": "...", "role": "background|tension|stance|counter", "bullets": ["...", "..."]}
  ],
  "conclusion_heading": "결론",
  "conclusion_bullets": ["...", "..."]
}
PROMPT;

        $userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
배경: {$quest['alignment_summary']}
갈등: {$quest['conflict_summary']}
학생 입장: {$ctx['stance_label']}
이유: {$ctx['reason']}
근거: {$ctx['evidence']}
들은 반론: {$ctx['counter_argument']}
반론에 대한 생각: {$ctx['rebuttal']}
3줄 정리:
{$reflectionText}

대화:
{$ctx['dialogue_text']}
MSG;

        $parsed = $this->requestStructureParsed($systemPrompt, $userMessage);

        if ($parsed !== null) {
            return $this->normalizeStructure($parsed, $ctx);
        }

        return $this->composeFailure(
            'structure',
            '글 구조도를 만들지 못했어요. 잠시 후 다시 시도해 주세요.'
        );
    }

    /** @return array<string, mixed>|null */
    private function requestStructureParsed(string $systemPrompt, string $userMessage): ?array
    {
        $response = $this->llm->haiku(
            $systemPrompt,
            [['role' => 'user', 'content' => $userMessage]],
            self::STRUCTURE_MAX_TOKENS
        );
        $parsed = $this->parseJsonResponse($response);
        if ($parsed !== null) {
            return $parsed;
        }

        $retry = $this->llm->haiku(
            $systemPrompt,
            [['role' => 'user', 'content' => $userMessage]],
            self::STRUCTURE_RETRY_MAX_TOKENS
        );

        return $this->parseJsonResponse($retry);
    }

    /**
     * Step 2: 구조도만 보고 the gist 스타일 장문을 **완전 새로** 작성
     *
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function composeArticleFromStructure(array $structure, array $ctx): array
    {
        $structureJson = json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $quest = $ctx['quest'];

        $systemPrompt = <<<PROMPT
너는 the gist 편집자다. **구조도를 뼈대로** 학생의 생각이 담긴 지정학 해설 글을 처음부터 쓴다.

the gist 톤 (필수):
- 명확한 제목·소제목, 각 섹션 2~3문단
- "~거든요", "~있어요", "~이에요" 존댓말 해설체
- 갈등·다른 시각 인정 후 학생 입장으로 수렴
- 학생 대화에 없는 새 사실·수치 추가 금지
- 학생이 쓴 문장을 그대로 복붙하지 말고, gist 리듬으로 **새로 서술**

JSON만 응답:
{
  "title": "...",
  "subtitle": "...",
  "sections": [
    {"heading": "소제목", "paragraphs": ["문단1", "문단2"]}
  ],
  "conclusion_heading": "결론",
  "conclusion_paragraphs": ["문단1", "문단2"],
  "full_text": "제목부터 결론까지 읽기 좋게 이어 붙인 전체 (소제목 포함)",
  "hero_sentence": "공유카드용 핵심 문장 1개"
}
PROMPT;

        $userMessage = <<<MSG
구조도:
{$structureJson}

학생 최종 입장: {$ctx['stance_label']}

the gist 원문 톤 참고:
{$ctx['narration_block']}

arc 참고:
{$ctx['arc_alignment']}

편집 패턴:
{$ctx['judgment_block']}
MSG;

        $parsed = $this->parseJsonResponse(
            $this->llm->chat($systemPrompt, [['role' => 'user', 'content' => $userMessage]], self::ARTICLE_MAX_TOKENS, 0.55)
        );

        if ($parsed !== null) {
            return $this->normalizeArticle($parsed, $structure, $ctx);
        }

        return $this->composeFailure(
            'article',
            '글을 완성하지 못했어요. 잠시 후 다시 시도해 주세요.'
        );
    }

    /** @param array<string, mixed> $raw @param array<string, mixed> $ctx */
    private function normalizeStructure(array $raw, array $ctx): array
    {
        $sections = [];
        foreach ($raw['sections'] ?? [] as $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $bullets = $sec['bullets'] ?? [];
            if (!is_array($bullets)) {
                $bullets = [];
            }
            $sections[] = [
                'heading' => trim((string) ($sec['heading'] ?? '')),
                'role' => trim((string) ($sec['role'] ?? '')),
                'bullets' => array_values(array_filter(array_map('strval', $bullets))),
            ];
        }

        $conclusionBullets = $raw['conclusion_bullets'] ?? [];
        if (!is_array($conclusionBullets)) {
            $conclusionBullets = [];
        }

        return [
            'title' => trim((string) ($raw['title'] ?? $ctx['quest']['quest_title'] ?? '')),
            'subtitle' => trim((string) ($raw['subtitle'] ?? '')),
            'sections' => $sections,
            'conclusion_heading' => trim((string) ($raw['conclusion_heading'] ?? '결론')),
            'conclusion_bullets' => array_values(array_filter(array_map('strval', $conclusionBullets))),
            'student_stance' => $ctx['stance_label'],
            'generated_by' => 'llm',
        ];
    }

    /** @param array<string, mixed> $raw @param array<string, mixed> $structure @param array<string, mixed> $ctx */
    private function normalizeArticle(array $raw, array $structure, array $ctx): array
    {
        $sections = [];
        foreach ($raw['sections'] ?? [] as $sec) {
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

        $conclusionParagraphs = $raw['conclusion_paragraphs'] ?? [];
        if (!is_array($conclusionParagraphs)) {
            $conclusionParagraphs = [$conclusionParagraphs];
        }

        $title = trim((string) ($raw['title'] ?? $structure['title'] ?? ''));
        $subtitle = trim((string) ($raw['subtitle'] ?? $structure['subtitle'] ?? ''));
        $fullText = trim((string) ($raw['full_text'] ?? ''));
        if ($fullText === '') {
            $fullText = $this->renderPlainText(
                $title,
                $subtitle,
                $sections,
                trim((string) ($raw['conclusion_heading'] ?? $structure['conclusion_heading'] ?? '결론')),
                array_values(array_filter(array_map('trim', array_map('strval', $conclusionParagraphs))))
            );
        }

        $hero = trim((string) ($raw['hero_sentence'] ?? ''));
        if ($hero === '' && $sections !== []) {
            $hero = $sections[0]['paragraphs'][0] ?? mb_substr($fullText, 0, 80);
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'sections' => $sections,
            'conclusion_heading' => trim((string) ($raw['conclusion_heading'] ?? $structure['conclusion_heading'] ?? '결론')),
            'conclusion_paragraphs' => array_values(array_filter(array_map('trim', array_map('strval', $conclusionParagraphs)))),
            'full_text' => $fullText,
            'hero_sentence' => $hero,
        ];
    }

    /**
     * @param list<array{heading: string, paragraphs: list<string>}> $sections
     * @param list<string> $conclusionParagraphs
     */
    public function renderPlainText(
        string $title,
        string $subtitle,
        array $sections,
        string $conclusionHeading,
        array $conclusionParagraphs
    ): string {
        $lines = [];
        if ($title !== '') {
            $lines[] = $title;
        }
        if ($subtitle !== '') {
            $lines[] = $subtitle;
        }
        $lines[] = '';

        foreach ($sections as $sec) {
            if (($sec['heading'] ?? '') !== '') {
                $lines[] = $sec['heading'];
            }
            foreach ($sec['paragraphs'] ?? [] as $p) {
                if ($p !== '') {
                    $lines[] = $p;
                }
            }
            $lines[] = '';
        }

        if ($conclusionHeading !== '') {
            $lines[] = $conclusionHeading;
        }
        foreach ($conclusionParagraphs as $p) {
            if ($p !== '') {
                $lines[] = $p;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function extractHeroFromText(string $fullText): string
    {
        $fullText = trim($fullText);
        if ($fullText === '') {
            return '';
        }
        $parts = preg_split('/[.!?…]\s+/u', $fullText, 2);
        $first = trim((string) ($parts[0] ?? ''));
        if ($first !== '' && mb_strlen($first) >= 12) {
            return $first;
        }

        return mb_substr($fullText, 0, 80);
    }

    /** @param array<string, mixed> $structure */
    private function structureToScqa(array $structure): array
    {
        $sections = $structure['sections'] ?? [];
        $first = is_array($sections[0] ?? null) ? implode(' ', $sections[0]['bullets'] ?? []) : '';
        $second = is_array($sections[1] ?? null) ? implode(' ', $sections[1]['bullets'] ?? []) : '';
        $third = is_array($sections[2] ?? null) ? implode(' ', $sections[2]['bullets'] ?? []) : '';
        $fourth = is_array($sections[3] ?? null) ? implode(' ', $sections[3]['bullets'] ?? []) : '';
        $conclusion = implode(' ', $structure['conclusion_bullets'] ?? []);

        return [
            'situation' => $first,
            'complication' => $second,
            'question' => $third !== '' ? $third : ($structure['subtitle'] ?? ''),
            'answer' => $fourth !== '' ? $fourth : ($structure['title'] ?? ''),
            'conclusion' => $conclusion,
        ];
    }

    /** @param array<string, mixed> $result */
    private function isComposeFailure(array $result): bool
    {
        return array_key_exists('success', $result) && $result['success'] === false;
    }

    /** @param mixed $structure */
    private function isLlmGeneratedStructure($structure): bool
    {
        return is_array($structure)
            && !empty($structure['sections'])
            && ($structure['generated_by'] ?? '') === 'llm'
            && !$this->isComposeFailure($structure);
    }

    /** @return array{success: false, error: string, message: string, compose_step: string} */
    private function composeFailure(string $step, string $message): array
    {
        return [
            'success' => false,
            'error' => 'compose_' . $step . '_failed',
            'message' => $message,
            'compose_step' => $step,
        ];
    }

    /** @return array<string, mixed>|null */
    private function parseJsonResponse(array $response): ?array
    {
        return EduLlmJson::parse($response);
    }
}
