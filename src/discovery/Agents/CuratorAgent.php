<?php
declare(strict_types=1);

namespace Discovery\Agents;

use Discovery\Agents\Contracts\DiscoveryAgentInterface;
use Discovery\Agents\Contracts\DiscoveryAgentResult;

/**
 * LLM-based Curator Agent (Phase 2).
 * Selects globally important articles using semantic understanding, not keywords.
 * Also ensures source diversity.
 */
final class CuratorAgent implements DiscoveryAgentInterface
{
    public function __construct(
        private readonly LLMClient $llm,
    ) {}

    public function getName(): string
    {
        return 'curator';
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $config */
    public function process(array $input, array $config): DiscoveryAgentResult
    {
        /** @var list<array<string, mixed>> $articles */
        $articles = $input['articles'] ?? [];
        $targetCount = (int) ($config['pipeline']['curator_limit'] ?? 15);
        $maxPerSource = (int) ($config['pipeline']['max_per_source'] ?? 5);

        if (count($articles) === 0) {
            return new DiscoveryAgentResult(['articles' => []], 0, 0, []);
        }

        $diversified = $this->ensureSourceDiversity($articles, $maxPerSource);
        $batchSize = min(40, count($diversified));
        $batch = array_slice($diversified, 0, $batchSize);

        $evaluated = $this->evaluateImportance($batch);

        usort($evaluated, fn($a, $b) => ($b['importance_score'] ?? 0) <=> ($a['importance_score'] ?? 0));

        $selected = [];
        $discarded = [];
        foreach ($evaluated as $article) {
            if (count($selected) >= $targetCount) {
                break;
            }
            $dominated = ($article['importance_score'] ?? 0) >= 6;
            $dominated = $dominated && !($article['is_entertainment'] ?? false);
            $dominated = $dominated && !($article['is_sports'] ?? false);

            if ($dominated) {
                $selected[] = $article;
            } else {
                $discarded[] = [
                    'title' => $article['title'] ?? '(no title)',
                    'reason' => $this->getDiscardReason($article),
                    'score' => $article['importance_score'] ?? 0,
                ];
            }
        }

        return new DiscoveryAgentResult(
            ['articles' => $selected],
            count($articles),
            count($selected),
            $discarded,
        );
    }

    /**
     * Ensure source diversity by limiting articles per domain.
     * @param list<array<string, mixed>> $articles
     * @return list<array<string, mixed>>
     */
    private function ensureSourceDiversity(array $articles, int $maxPerSource): array
    {
        $byDomain = [];
        $result = [];

        foreach ($articles as $article) {
            $domain = $this->extractDomain($article['url'] ?? '');
            if (!isset($byDomain[$domain])) {
                $byDomain[$domain] = 0;
            }
            if ($byDomain[$domain] < $maxPerSource) {
                $result[] = $article;
                $byDomain[$domain]++;
            }
        }

        return $result;
    }

    /**
     * Use LLM to evaluate importance of each article.
     * @param list<array<string, mixed>> $articles
     * @return list<array<string, mixed>>
     */
    private function evaluateImportance(array $articles): array
    {
        $articleList = [];
        foreach ($articles as $i => $article) {
            $articleList[] = [
                'id' => $i,
                'title' => $article['title'] ?? '',
                'description' => mb_substr($article['description'] ?? '', 0, 300),
                'source' => $article['source_name'] ?? $this->extractDomain($article['url'] ?? ''),
            ];
        }

        $system = <<<'PROMPT'
You are a news curator for a global news briefing service. Your job is to evaluate articles for GLOBAL IMPORTANCE.

For each article, provide:
1. importance_score (1-10): How globally significant is this news?
   - 10: Major world-changing event (war outbreak, major treaty, global crisis)
   - 8-9: Significant international impact (major policy change, important elections, significant tech breakthrough)
   - 6-7: Notable regional/sector impact (trade deals, regulatory changes, security incidents)
   - 4-5: Limited impact, mostly local or niche interest
   - 1-3: Entertainment, sports, gossip, local events
2. is_entertainment (boolean): Celebrity news, music, movies, TV shows, concerts
3. is_sports (boolean): Sports games, matches, tournaments, athletes (BUT NOT sports governance/politics like FIFA corruption)
4. reason (string): One sentence explaining your rating

IMPORTANT distinctions:
- "Pool" in political context (Reflecting Pool in Washington DC) is NOT sports
- AI security incidents, hacking, cyber attacks are HIGH importance (tech/security)
- International organization politics (FIFA leadership crisis) - evaluate on global impact, not just "sports"
- Natural disasters, climate events affecting multiple countries are important
- Government policy changes, legal decisions affecting rights are important

Respond in JSON array format:
[{"id": 0, "importance_score": 8, "is_entertainment": false, "is_sports": false, "reason": "..."}, ...]
PROMPT;

        $user = "Evaluate these articles:\n" . json_encode($articleList, JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->llm->complete($system, $user, [
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            $evaluations = $this->parseEvaluations($response->content);

            foreach ($articles as $i => &$article) {
                if (isset($evaluations[$i])) {
                    $article['importance_score'] = $evaluations[$i]['importance_score'] ?? 5;
                    $article['is_entertainment'] = $evaluations[$i]['is_entertainment'] ?? false;
                    $article['is_sports'] = $evaluations[$i]['is_sports'] ?? false;
                    $article['curator_reason'] = $evaluations[$i]['reason'] ?? '';
                } else {
                    $article['importance_score'] = 5;
                    $article['is_entertainment'] = false;
                    $article['is_sports'] = false;
                    $article['curator_reason'] = 'evaluation_missing';
                }
            }
            unset($article);

            return $articles;
        } catch (\Throwable $e) {
            foreach ($articles as &$article) {
                $article['importance_score'] = 5;
                $article['is_entertainment'] = false;
                $article['is_sports'] = false;
                $article['curator_reason'] = 'llm_error: ' . $e->getMessage();
            }
            unset($article);
            return $articles;
        }
    }

    /**
     * @return array<int, array{importance_score: int, is_entertainment: bool, is_sports: bool, reason: string}>
     */
    private function parseEvaluations(string $content): array
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $id = (int) $item['id'];
            $result[$id] = [
                'importance_score' => (int) ($item['importance_score'] ?? 5),
                'is_entertainment' => (bool) ($item['is_entertainment'] ?? false),
                'is_sports' => (bool) ($item['is_sports'] ?? false),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }

        return $result;
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /** @param array<string, mixed> $article */
    private function getDiscardReason(array $article): string
    {
        if ($article['is_entertainment'] ?? false) {
            return 'entertainment_excluded';
        }
        if ($article['is_sports'] ?? false) {
            return 'sports_excluded';
        }
        $score = $article['importance_score'] ?? 0;
        if ($score < 6) {
            return "low_importance_score_{$score}";
        }
        return 'unknown';
    }
}
