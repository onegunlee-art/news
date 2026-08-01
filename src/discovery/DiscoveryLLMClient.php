<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryLLMClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private DiscoveryUrlGuard $urlGuard;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $openaiConfig = require dirname(__DIR__, 2) . '/config/openai.php';
        $this->apiKey = (string) ($config['api_key'] ?? $openaiConfig['api_key'] ?? '');
        $this->model = (string) ($config['model'] ?? 'gpt-4o');
        $this->endpoint = (string) ($openaiConfig['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses');
        $this->urlGuard = new DiscoveryUrlGuard();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param list<array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogArticles
     * @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null, generation_mode: string}
     */
    public function generateDailyChanges(string $date, array $discoveryConfig, array $catalogArticles = []): array
    {
        if (!$this->isConfigured()) {
            return array_merge($this->mockChanges($date), ['generation_mode' => 'mock']);
        }

        $minCatalog = (int) ($discoveryConfig['min_catalog_articles'] ?? 8);
        if (count($catalogArticles) >= $minCatalog) {
            return $this->generateFromCatalog($date, $discoveryConfig, $catalogArticles);
        }

        return $this->generateWithWebSearch($date, $discoveryConfig, $catalogArticles);
    }

    /**
     * @param list<array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogArticles
     * @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null, generation_mode: string}
     */
    private function generateFromCatalog(string $date, array $discoveryConfig, array $catalogArticles): array
    {
        $system = <<<'SYS'
You are a world-news change detector for a Korean B2C product "오늘의 발견".
You receive ARTICLE_CATALOG with REAL URLs from RSS feeds. You MUST NOT invent URLs or events.
Rules:
- Pick changes ONLY from ARTICLE_CATALOG entries (use article_index).
- NEVER modify or guess URLs. sources[].url must be copied EXACTLY from the catalog entry.
- Each change maps to one catalog article (article_index). One article = one change max.
- Return STRICT JSON only — no markdown fences.
- Generate up to 12 candidates (we filter to 5~7). Return fewer if not enough qualify — never fabricate.
- EXCLUDE: entertainment/concerts/K-pop/sports, personal visit schedules, regional festivals.
- INCLUDE: policy, diplomacy, economic indicators (with numbers), tech/industry, security, climate/energy.
- briefing keys: what_changed, why_changed, why_important, future_impact, highlights (4-6 bullets).
- OUTPUT LANGUAGE: Korean 합니다체.
- COMPLETENESS: include concrete numbers/facts from the article in title and what_changed.
- poll: neutral question, 4 distinct options.
SYS;

        $candidateCount = (int) ($discoveryConfig['candidate_count'] ?? 12);
        $catalogJson = json_encode(array_slice($catalogArticles, 0, 40), JSON_UNESCAPED_UNICODE);
        $user = sprintf(
            "Edition date (KST): %s\nGenerate up to %d changes from ARTICLE_CATALOG below.\n\nARTICLE_CATALOG:\n%s\n\nReturn JSON:\n{\n  \"changes\": [\n    {\n      \"article_index\": 0,\n      \"category\": \"geopolitics|business|tech|climate|other\",\n      \"title\": \"한 줄 제목 (수치·결과 포함)\",\n      \"summary\": \"카드용 2~3줄 요약\",\n      \"briefing\": {\"what_changed\":\"\",\"why_changed\":\"\",\"why_important\":\"\",\"future_impact\":\"\",\"highlights\":[\"\",\"\",\"\",\"\"]},\n      \"sources\": [{\"name\":\"\",\"url\":\"\",\"article_title\":\"\"}],\n      \"poll\": {\"question\":\"\",\"options\":[\"\",\"\",\"\",\"\"]}\n    }\n  ]\n}",
            $date,
            $candidateCount,
            $catalogJson
        );

        $payload = [
            'model' => $this->model,
            'instructions' => $system,
            'input' => $user,
            'max_output_tokens' => 12000,
        ];

        $response = $this->callResponsesApi($payload);
        $decoded = $this->parseJsonFromText($response['text']);
        $changes = is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [];

        return [
            'changes' => $this->normalizeChanges($changes, $catalogArticles, []),
            'raw' => $response['text'],
            'cost_usd' => null,
            'generation_mode' => 'rss_catalog',
        ];
    }

    /**
     * @param list<array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogArticles
     * @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null, generation_mode: string}
     */
    private function generateWithWebSearch(string $date, array $discoveryConfig, array $catalogArticles): array
    {
        $system = <<<'SYS'
You are a world-news change detector. Use web search to find REAL recent articles.
CRITICAL: sources[].url MUST be exact URLs from your web search results — NEVER invent or guess URLs.
If you cannot find a real URL for an event, skip that event entirely.
Return STRICT JSON only. Korean 합니다체. Include concrete numbers in titles.
SYS;

        $candidateCount = (int) ($discoveryConfig['candidate_count'] ?? 12);
        $catalogHint = $catalogArticles !== []
            ? "\n\nPartial RSS catalog (prefer these exact URLs when matching):\n" . json_encode(array_slice($catalogArticles, 0, 20), JSON_UNESCAPED_UNICODE)
            : '';

        $user = sprintf(
            "Edition date (KST): %s\nSearch global geopolitics/economy/tech from overseas whitelist sources (Reuters, AP, BBC, Bloomberg, FT, etc.). Generate up to %d changes.%s\n\nReturn JSON with changes array (category, title, summary, briefing, sources with REAL urls, poll).",
            $date,
            $candidateCount,
            $catalogHint
        );

        $payload = [
            'model' => $this->model,
            'instructions' => $system,
            'input' => $user,
            'max_output_tokens' => 12000,
            'tools' => [['type' => 'web_search']],
            'include' => ['web_search_call.action.sources'],
        ];

        $response = $this->callResponsesApi($payload);
        $searchUrls = $this->extractWebSearchUrls($response['data']);
        $catalogUrls = array_map(static fn(array $a) => (string) $a['url'], $catalogArticles);
        $allowedUrls = array_values(array_unique(array_merge($searchUrls, $catalogUrls)));

        $decoded = $this->parseJsonFromText($response['text']);
        $changes = is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [];

        return [
            'changes' => $this->normalizeChanges($changes, $catalogArticles, $allowedUrls),
            'raw' => $response['text'],
            'cost_usd' => null,
            'generation_mode' => 'web_search',
        ];
    }

    /**
     * @param list<array<string,mixed>> $changes
     * @param list<array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogArticles
     * @param list<string> $allowedUrls
     * @return list<array<string,mixed>>
     */
    private function normalizeChanges(array $changes, array $catalogArticles, array $allowedUrls): array
    {
        $catalogByIndex = [];
        $catalogByUrl = [];
        foreach ($catalogArticles as $article) {
            $catalogByIndex[(int) $article['index']] = $article;
            $catalogByUrl[$this->urlGuard->normalizeUrl((string) $article['url'])] = $article;
        }

        $allowed = [];
        foreach ($allowedUrls as $url) {
            $allowed[$this->urlGuard->normalizeUrl($url)] = $url;
        }

        $out = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $bound = $this->bindSources($change, $catalogByIndex, $catalogByUrl, $allowed);
            if ($bound === null) {
                continue;
            }
            $change = $bound;

            $category = (string) ($change['category'] ?? 'other');
            $allowedCategories = ['geopolitics', 'business', 'tech', 'climate', 'other'];
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'other';
            }

            $briefing = is_array($change['briefing'] ?? null) ? $change['briefing'] : [];
            $poll = is_array($change['poll'] ?? null) ? $change['poll'] : [];
            $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
            if (count($options) < 4) {
                continue;
            }

            $title = trim((string) ($change['title'] ?? ''));
            $summary = trim((string) ($change['summary'] ?? ''));
            if ($title === '' || $summary === '') {
                continue;
            }

            $sources = is_array($change['sources'] ?? null) ? $change['sources'] : [];
            if ($sources === []) {
                continue;
            }

            $highlights = is_array($briefing['highlights'] ?? null) ? $briefing['highlights'] : [];
            $highlights = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $highlights)));

            $out[] = [
                'category' => $category,
                'title' => mb_substr($title, 0, 200),
                'summary' => $summary,
                'briefing' => [
                    'what_changed' => (string) ($briefing['what_changed'] ?? ''),
                    'why_changed' => (string) ($briefing['why_changed'] ?? ''),
                    'why_important' => (string) ($briefing['why_important'] ?? ''),
                    'future_impact' => (string) ($briefing['future_impact'] ?? ''),
                    'highlights' => array_slice($highlights, 0, 6),
                ],
                'sources' => $sources,
                'poll' => [
                    'question' => mb_substr(trim((string) ($poll['question'] ?? '')), 0, 300),
                    'options' => array_map(static fn($o) => mb_substr(trim((string) $o), 0, 120), array_slice($options, 0, 4)),
                ],
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $change
     * @param array<int, array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogByIndex
     * @param array<string, array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}> $catalogByUrl
     * @param array<string, string> $allowed
     * @return array<string,mixed>|null
     */
    private function bindSources(array $change, array $catalogByIndex, array $catalogByUrl, array $allowed): ?array
    {
        if (isset($change['article_index']) && is_numeric($change['article_index'])) {
            $idx = (int) $change['article_index'];
            if (!isset($catalogByIndex[$idx])) {
                return null;
            }
            $article = $catalogByIndex[$idx];
            $change['sources'] = [[
                'name' => (string) $article['source_name'],
                'url' => (string) $article['url'],
                'article_title' => (string) $article['title'],
            ]];

            return $change;
        }

        $sources = is_array($change['sources'] ?? null) ? $change['sources'] : [];
        $boundSources = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $url = trim((string) ($source['url'] ?? ''));
            if ($url === '' || $this->urlGuard->looksHallucinated($url)) {
                continue;
            }

            $norm = $this->urlGuard->normalizeUrl($url);
            if (isset($catalogByUrl[$norm])) {
                $article = $catalogByUrl[$norm];
                $boundSources[] = [
                    'name' => (string) ($source['name'] ?: $article['source_name']),
                    'url' => (string) $article['url'],
                    'article_title' => (string) ($source['article_title'] ?: $article['title']),
                ];
                continue;
            }

            if ($allowed !== [] && !isset($allowed[$norm])) {
                continue;
            }

            $boundSources[] = [
                'name' => trim((string) ($source['name'] ?? '')),
                'url' => $url,
                'article_title' => trim((string) ($source['article_title'] ?? '')),
            ];
        }

        if ($boundSources === []) {
            return null;
        }

        $change['sources'] = $boundSources;

        return $change;
    }

    /** @return array<string, mixed> */
    private function parseJsonFromText(string $text): array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        throw new \RuntimeException('Discovery LLM returned invalid JSON: ' . mb_substr($text, 0, 300));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{text:string,data:array<string,mixed>|null}
     */
    private function callResponsesApi(array $payload): array
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Discovery LLM curl error: ' . $err);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('Discovery LLM HTTP ' . $httpCode . ': ' . mb_substr((string) $response, 0, 500));
        }

        $data = json_decode((string) $response, true);
        $text = $this->extractText(is_array($data) ? $data : null);
        if ($text === null || trim($text) === '') {
            throw new \RuntimeException('Discovery LLM empty response');
        }

        return ['text' => $text, 'data' => is_array($data) ? $data : null];
    }

    /** @param array<string,mixed>|null $data @return list<string> */
    private function extractWebSearchUrls(?array $data): array
    {
        if (!$data || empty($data['output']) || !is_array($data['output'])) {
            return [];
        }

        $urls = [];
        foreach ($data['output'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'web_search_call') {
                $sources = $item['action']['sources'] ?? [];
                if (is_array($sources)) {
                    foreach ($sources as $source) {
                        if (is_array($source) && !empty($source['url'])) {
                            $urls[] = (string) $source['url'];
                        }
                    }
                }
            }

            if (($item['type'] ?? '') === 'message' && !empty($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $part) {
                    if (!is_array($part)) {
                        continue;
                    }
                    foreach ($part['annotations'] ?? [] as $annotation) {
                        if (is_array($annotation)
                            && ($annotation['type'] ?? '') === 'url_citation'
                            && !empty($annotation['url'])) {
                            $urls[] = (string) $annotation['url'];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /** @param array<string,mixed>|null $data */
    private function extractText(?array $data): ?string
    {
        if (!$data) {
            return null;
        }
        if (!empty($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                if (($item['type'] ?? '') === 'message' && !empty($item['content'])) {
                    foreach ($item['content'] as $part) {
                        if (($part['type'] ?? '') === 'output_text' && !empty($part['text'])) {
                            return (string) $part['text'];
                        }
                    }
                }
            }
        }
        if (!empty($data['output_text'])) {
            return (string) $data['output_text'];
        }

        return null;
    }

    /** @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null} */
    private function mockChanges(string $date): array
    {
        $samples = [
            [
                'category' => 'geopolitics',
                'title' => '예멘 후티, 홍해 해상 봉쇄 재개',
                'summary' => '후티 반군이 주요 해상로 봉쇄를 재개하면서 글로벌 운임과 에너지 수송에 파급이 확대되고 있습니다.',
                'briefing' => [
                    'what_changed' => '후티가 홍해 주요 항로에 대한 공격·봉쇄 조치를 재개했습니다.',
                    'why_changed' => '지역 분쟁 장기화와 해상 통제력 확보 시도가 겹쳤습니다.',
                    'why_important' => '유럽·아시아 간 에너지·컨테이너 수송 비용에 직접 영향을 줍니다.',
                    'future_impact' => '우회 항로 확대와 보험료 상승이 이어질 수 있습니다.',
                    'highlights' => ['해상 봉쇄 재개', '운임 상승 압력', '에너지 수송 리스크'],
                ],
            ],
        ];
        $changes = [];
        foreach ($samples as $sample) {
            $changes[] = array_merge($sample, [
                'sources' => [
                    ['name' => 'Reuters', 'url' => 'https://www.reuters.com/world/', 'article_title' => 'Global News'],
                ],
                'poll' => [
                    'question' => sprintf('[%s] 이 변화의 글로벌 파급을 어떻게 보시나요?', $date),
                    'options' => ['단기 충격 크다', '점진적 조정', '영향 제한적', '아직 판단 어렵다'],
                ],
            ]);
        }

        return [
            'changes' => $changes,
            'raw' => json_encode(['changes' => $changes], JSON_UNESCAPED_UNICODE),
            'cost_usd' => 0.0,
        ];
    }
}
