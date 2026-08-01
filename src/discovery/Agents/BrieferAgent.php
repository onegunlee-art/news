<?php
declare(strict_types=1);

namespace Discovery\Agents;

use Discovery\Agents\Contracts\DiscoveryAgentInterface;
use Discovery\Agents\Contracts\DiscoveryAgentResult;

final class BrieferAgent implements DiscoveryAgentInterface
{
    private const SYSTEM_FULL = <<<'SYS'
You are a world-news briefing writer for a Korean B2C product "오늘의 발견".
You receive the FULL ARTICLE TEXT. Write a rich, accurate briefing in Korean (합니다체).

RULES:
- Use ONLY facts, numbers, names, and quotes from the provided article text.
- Do NOT invent numbers, amounts, dates, or details not in the text.
- Each briefing section: 2-3 FULL sentences with specific details from the article.
- Extract background, context, stakes, and implications that ARE in the article.
- Return STRICT JSON only — no markdown fences.

Output schema:
{
  "category": "geopolitics|business|tech|climate|other",
  "title": "한 줄 제목 (핵심 + 수치가 있으면 포함, 기사 근거만)",
  "summary": "카드용 2~3줄 요약",
  "briefing": {
    "what_changed": "2-3문장",
    "why_changed": "2-3문장",
    "why_important": "2-3문장",
    "future_impact": "2-3문장",
    "highlights": ["사실1", "사실2", "사실3", "사실4"]
  },
  "poll": {
    "question": "중립적 질문",
    "options": ["선택지1", "선택지2", "선택지3", "선택지4"]
  }
}
SYS;

    private const SYSTEM_SUMMARY = <<<'SYS'
You are a world-news briefing writer for a Korean B2C product "오늘의 발견".
You receive ONLY an RSS summary (full article text unavailable). Write a restrained briefing in Korean (합니다체).

RULES:
- Use ONLY what is stated in the summary. Do NOT infer or invent details.
- Each section: 1-2 sentences. Be factual but modest — do not speculate.
- Do NOT invent numbers not in the summary.
- Return STRICT JSON only.

Same output schema as full-article mode (category, title, summary, briefing, poll).
SYS;

    public function __construct(
        private readonly LLMClient $llm,
    ) {
    }

    public function getName(): string
    {
        return 'briefer';
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $config */
    public function process(array $input, array $config): DiscoveryAgentResult
    {
        /** @var list<array<string, mixed>> $articles */
        $articles = $input['articles'] ?? [];
        $output = [];
        $discarded = [];

        foreach ($articles as $article) {
            $title = trim((string) ($article['title'] ?? ''));
            $body = trim((string) ($article['body_text'] ?? ''));
            if ($title === '' || $body === '') {
                $discarded[] = [
                    'title' => $title ?: '(no title)',
                    'reason' => 'missing_body_text',
                ];
                continue;
            }

            try {
                $parsed = $this->generateBriefing($article);
                if (!$this->isValidChange($parsed)) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'invalid_briefing_output',
                    ];
                    continue;
                }

                $change = $this->buildChange($article, $parsed);
                $output[] = $change;
            } catch (\Throwable $e) {
                $discarded[] = [
                    'title' => $title,
                    'reason' => 'briefer_failed',
                    'detail' => $e->getMessage(),
                ];
            }
        }

        return new DiscoveryAgentResult(
            ['changes' => $output],
            count($articles),
            count($output),
            $discarded,
        );
    }

    /** @param array<string, mixed> $article @return array<string, mixed> */
    private function generateBriefing(array $article): array
    {
        $status = (string) ($article['extraction_status'] ?? 'full');
        $system = $status === 'summary_only' ? self::SYSTEM_SUMMARY : self::SYSTEM_FULL;

        $user = sprintf(
            "Source: %s\nURL: %s\nOriginal title: %s\nPublished: %s\n\n--- ARTICLE TEXT ---\n%s\n\nWrite JSON briefing.",
            (string) ($article['source_name'] ?? 'Unknown'),
            (string) ($article['url'] ?? ''),
            (string) ($article['title'] ?? ''),
            (string) ($article['published_at'] ?? ''),
            (string) ($article['body_text'] ?? ''),
        );

        $response = $this->llm->complete($system, $user, ['max_output_tokens' => 4000]);
        return LLMClient::parseJsonFromText($response->text);
    }

    /** @param array<string, mixed> $parsed */
    private function isValidChange(array $parsed): bool
    {
        $title = trim((string) ($parsed['title'] ?? ''));
        $summary = trim((string) ($parsed['summary'] ?? ''));
        $briefing = is_array($parsed['briefing'] ?? null) ? $parsed['briefing'] : [];
        $poll = is_array($parsed['poll'] ?? null) ? $parsed['poll'] : [];
        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];

        if ($title === '' || $summary === '') {
            return false;
        }
        if (trim((string) ($briefing['what_changed'] ?? '')) === '') {
            return false;
        }
        if (count($options) < 4) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $article @param array<string, mixed> $parsed @return array<string, mixed> */
    private function buildChange(array $article, array $parsed): array
    {
        $category = (string) ($parsed['category'] ?? 'other');
        $allowed = ['geopolitics', 'business', 'tech', 'climate', 'other'];
        if (!in_array($category, $allowed, true)) {
            $category = 'other';
        }

        $briefing = is_array($parsed['briefing'] ?? null) ? $parsed['briefing'] : [];
        $highlights = is_array($briefing['highlights'] ?? null) ? $briefing['highlights'] : [];
        $highlights = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $highlights)));

        $poll = is_array($parsed['poll'] ?? null) ? $parsed['poll'] : [];
        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];

        return [
            'category' => $category,
            'title' => mb_substr(trim((string) ($parsed['title'] ?? '')), 0, 200),
            'summary' => trim((string) ($parsed['summary'] ?? '')),
            'briefing' => [
                'what_changed' => (string) ($briefing['what_changed'] ?? ''),
                'why_changed' => (string) ($briefing['why_changed'] ?? ''),
                'why_important' => (string) ($briefing['why_important'] ?? ''),
                'future_impact' => (string) ($briefing['future_impact'] ?? ''),
                'highlights' => array_slice($highlights, 0, 6),
            ],
            'sources' => [[
                'name' => (string) ($article['source_name'] ?? 'Source'),
                'url' => (string) ($article['url'] ?? ''),
                'article_title' => (string) ($article['title'] ?? ''),
            ]],
            'poll' => [
                'question' => mb_substr(trim((string) ($poll['question'] ?? '')), 0, 300),
                'options' => array_map(
                    static fn($o) => mb_substr(trim((string) $o), 0, 120),
                    array_slice($options, 0, 4),
                ),
            ],
            'extraction_status' => (string) ($article['extraction_status'] ?? 'full'),
            'body_text' => (string) ($article['body_text'] ?? ''),
        ];
    }
}
