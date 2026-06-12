<?php
/**
 * GIST EDU — 대화 로그 + GIST DNA → 학생 맞춤 SCQA 글 자동 생성
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

use Services\Edu\EduRagService;
use Services\Edu\GistNarrationReader;

class GistStyleComposer
{
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
    public function compose(array $blueprint, array $quest, array $dialogue = []): array
    {
        $stance = (string) ($blueprint['final_stance'] ?? $blueprint['stance'] ?? 'pro');
        $stanceLabel = $stance === 'pro' ? '찬성' : '반대';
        $reason = (string) ($blueprint['reason'] ?? '');
        $evidence = (string) ($blueprint['evidence'] ?? '');
        $rebuttal = (string) ($blueprint['rebuttal'] ?? '');
        $reflection = $blueprint['reflection_lines'] ?? [];
        if (!is_array($reflection)) {
            $reflection = [];
        }

        $newsIds = [];
        foreach ($quest['articles'] ?? [] as $article) {
            $nid = (int) ($article['news_id'] ?? 0);
            if ($nid > 0) {
                $newsIds[] = $nid;
            }
        }

        $arc = $this->rag->findArcArticles($newsIds, (string) ($quest['conflict_summary'] ?? ''));
        $narrationBlock = $this->narration->formatFewShot($this->narration->readExcerpts($newsIds));

        $judgmentBlock = '';
        $patterns = $this->rag->getWritingPatterns((string) ($quest['quest_title'] ?? ''), 3);
        $weighted = $this->rag->getJudgementPatterns(3);
        $allPatterns = array_merge($patterns, $weighted);
        if ($allPatterns !== []) {
            $judgmentBlock = $this->rag->formatWritingPatterns($allPatterns);
        }

        $dialogueText = '';
        foreach (array_slice($dialogue, -12) as $turn) {
            $role = ($turn['role'] ?? '') === 'student' ? '학생' : '코치';
            $dialogueText .= "{$role}: " . ($turn['content'] ?? '') . "\n";
        }

        $reflectionText = implode("\n", array_map('strval', $reflection));

        $systemPrompt = <<<PROMPT
너는 the gist 스타일로 중학생의 생각을 SCQA 5문장 글로 완성하는 편집자야.

규칙:
- 학생 대화에 나온 입장·이유·근거·반론 반응만 사용 (새 사실 추가 금지)
- 부족한 슬롯은 퀘스트 배경(alignment/conflict)으로 자연스럽게 보완
- the gist 톤: 명확한 주장, 갈등 인정, 간결한 한국어
- 각 슬롯은 한 문장

JSON으로 응답:
{
  "situation": "S 문장",
  "complication": "C 문장",
  "question": "Q 문장",
  "answer": "A 문장",
  "conclusion": "결론 문장",
  "full_text": "다섯 문장을 이어 붙인 전체",
  "hero_sentence": "공유카드용 핵심 문장 하나"
}
PROMPT;

        $userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
배경(일치): {$quest['alignment_summary']}
갈등(불일치): {$quest['conflict_summary']}
학생 최종 입장: {$stanceLabel}
학생 이유: {$reason}
학생 근거: {$evidence}
반론에 대한 생각: {$rebuttal}
3줄 정리:
{$reflectionText}

대화 로그:
{$dialogueText}

the gist 서술 참고:
{$narrationBlock}

arc 분석 참고:
{$arc['alignment']}

편집 패턴:
{$judgmentBlock}
MSG;

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ], 800, 0.5);

        $fallback = $this->fallbackDraft($blueprint, $quest, $stanceLabel);

        if (!empty($response['error'])) {
            return array_merge($fallback, ['success' => false, 'error' => $response['error']]);
        }

        $content = $response['content'] ?? '';
        if (!preg_match('/\{[\s\S]*\}/', $content, $match)) {
            return array_merge($fallback, ['success' => true, 'agent' => 'gist_style_composer']);
        }

        $parsed = json_decode($match[0], true);
        if (!is_array($parsed)) {
            return array_merge($fallback, ['success' => true, 'agent' => 'gist_style_composer']);
        }

        $parts = [
            'situation' => trim((string) ($parsed['situation'] ?? '')),
            'complication' => trim((string) ($parsed['complication'] ?? '')),
            'question' => trim((string) ($parsed['question'] ?? '')),
            'answer' => trim((string) ($parsed['answer'] ?? '')),
            'conclusion' => trim((string) ($parsed['conclusion'] ?? '')),
        ];

        $fullText = trim((string) ($parsed['full_text'] ?? ''));
        if ($fullText === '') {
            $fullText = implode(' ', array_filter($parts));
        }

        return [
            'success' => true,
            'full_text' => $fullText,
            'scqa_parts' => $parts,
            'hero_sentence' => trim((string) ($parsed['hero_sentence'] ?? mb_substr($fullText, 0, 60))),
            'agent' => 'gist_style_composer',
        ];
    }

    /**
     * @param array<string, mixed> $blueprint
     * @param array<string, mixed> $quest
     * @return array<string, mixed>
     */
    private function fallbackDraft(array $blueprint, array $quest, string $stanceLabel): array
    {
        $reason = trim((string) ($blueprint['reason'] ?? ''));
        $alignment = (string) ($quest['alignment_summary'] ?? $quest['quest_title'] ?? '');
        $conflict = (string) ($quest['conflict_summary'] ?? '');

        $parts = [
            'situation' => mb_substr($alignment, 0, 80) ?: '이 주제는 요즘 많은 사람들이 주목하고 있어.',
            'complication' => mb_substr($conflict, 0, 80) ?: '하지만 서로 다른 의견이 충돌하고 있어.',
            'question' => '그래서 우리는 어떤 선택을 해야 할까?',
            'answer' => "나는 {$stanceLabel}한다. " . ($reason !== '' ? $reason : '내 생각이 더 설득력 있다고 느꼈기 때문이야.'),
            'conclusion' => '결론적으로, 나의 입장은 대화를 통해 더 분명해졌어.',
        ];

        $fullText = implode(' ', $parts);

        return [
            'full_text' => $fullText,
            'scqa_parts' => $parts,
            'hero_sentence' => $parts['answer'],
        ];
    }
}
