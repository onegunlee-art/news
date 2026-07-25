<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryLLMClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $openaiConfig = require dirname(__DIR__, 2) . '/config/openai.php';
        $this->apiKey = (string) ($config['api_key'] ?? $openaiConfig['api_key'] ?? '');
        $this->model = (string) ($config['model'] ?? 'gpt-4o');
        $this->endpoint = (string) ($openaiConfig['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null}
     */
    public function generateDailyChanges(string $date, array $discoveryConfig): array
    {
        if (!$this->isConfigured()) {
            return $this->mockChanges($date);
        }

        $system = <<<'SYS'
You are a world-news change detector for a Korean B2C product "오늘의 발견".
Rules:
- Use web search ONLY. Never invent events or URLs from memory.
- Each change must have happened within the last 48 hours relative to the edition date (prefer 24h).
- Return STRICT JSON only.
- If you cannot find enough verified recent changes, return fewer — never fabricate.
- sources[].url must be real article URLs from search results.
- poll must be neutral, no right answer, 4 distinct options.
- briefing must have exactly 4 keys: what_changed, why_changed, why_important, future_impact (Korean, 합니다체).
SYS;

        $targets = $discoveryConfig['category_targets'] ?? [];
        $user = sprintf(
            "Edition date (KST): %s\nTarget: up to 9 changes.\nCategory targets: geopolitics=%d, business=%d, tech=%d, climate=%d, other=%d\n\nReturn JSON:\n{\n  \"changes\": [\n    {\n      \"category\": \"geopolitics|business|tech|climate|other\",\n      \"title\": \"한 줄 제목\",\n      \"summary\": \"카드용 2~3줄 요약\",\n      \"briefing\": {\"what_changed\":\"\",\"why_changed\":\"\",\"why_important\":\"\",\"future_impact\":\"\"},\n      \"sources\": [{\"name\":\"\",\"url\":\"\",\"article_title\":\"\"}],\n      \"poll\": {\"question\":\"\",\"options\":[\"\",\"\",\"\",\"\"]}\n    }\n  ]\n}",
            $date,
            $targets['geopolitics'] ?? 4,
            $targets['business'] ?? 3,
            $targets['tech'] ?? 2,
            $targets['climate'] ?? 1,
            $targets['other'] ?? 2
        );

        $payload = [
            'model' => $this->model,
            'instructions' => $system,
            'input' => $user,
            'max_output_tokens' => 12000,
            'tools' => [['type' => 'web_search_preview']],
            'text' => ['format' => ['type' => 'json_object']],
        ];

        $raw = $this->callResponsesApi($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Discovery LLM returned invalid JSON');
        }
        $changes = $decoded['changes'] ?? [];
        if (!is_array($changes)) {
            $changes = [];
        }

        return [
            'changes' => $this->normalizeChanges($changes),
            'raw' => $raw,
            'cost_usd' => null,
        ];
    }

    /** @param list<array<string,mixed>> $changes @return list<array<string,mixed>> */
    private function normalizeChanges(array $changes): array
    {
        $allowed = ['geopolitics', 'business', 'tech', 'climate', 'other'];
        $out = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $category = (string) ($change['category'] ?? 'other');
            if (!in_array($category, $allowed, true)) {
                $category = 'other';
            }
            $briefing = $change['briefing'] ?? [];
            if (!is_array($briefing)) {
                $briefing = [];
            }
            $sources = $change['sources'] ?? [];
            if (!is_array($sources)) {
                $sources = [];
            }
            $poll = $change['poll'] ?? [];
            if (!is_array($poll)) {
                $poll = [];
            }
            $options = $poll['options'] ?? [];
            if (!is_array($options) || count($options) < 4) {
                continue;
            }
            $title = trim((string) ($change['title'] ?? ''));
            $summary = trim((string) ($change['summary'] ?? ''));
            if ($title === '' || $summary === '') {
                continue;
            }
            $out[] = [
                'category' => $category,
                'title' => mb_substr($title, 0, 200),
                'summary' => $summary,
                'briefing' => [
                    'what_changed' => (string) ($briefing['what_changed'] ?? ''),
                    'why_changed' => (string) ($briefing['why_changed'] ?? ''),
                    'why_important' => (string) ($briefing['why_important'] ?? ''),
                    'future_impact' => (string) ($briefing['future_impact'] ?? ''),
                ],
                'sources' => array_values(array_filter(array_map(static function ($s) {
                    if (!is_array($s)) {
                        return null;
                    }
                    $url = trim((string) ($s['url'] ?? ''));
                    if ($url === '') {
                        return null;
                    }
                    return [
                        'name' => trim((string) ($s['name'] ?? '')),
                        'url' => $url,
                        'article_title' => trim((string) ($s['article_title'] ?? '')),
                    ];
                }, $sources))),
                'poll' => [
                    'question' => mb_substr(trim((string) ($poll['question'] ?? '')), 0, 300),
                    'options' => array_map(static fn($o) => mb_substr(trim((string) $o), 0, 120), array_slice($options, 0, 4)),
                ],
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $payload */
    private function callResponsesApi(array $payload): string
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
        $text = $this->extractText($data);
        if ($text === null || trim($text) === '') {
            throw new \RuntimeException('Discovery LLM empty response');
        }
        return $text;
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
        $changes = [];
        for ($i = 1; $i <= 3; $i++) {
            $changes[] = [
                'category' => $i === 1 ? 'geopolitics' : ($i === 2 ? 'business' : 'tech'),
                'title' => sprintf('[MOCK] %s 테스트 변화 #%d', $date, $i),
                'summary' => 'ENABLE_DISCOVERY mock 모드에서 생성된 샘플 카드 요약입니다. 실제 OpenAI 키 설정 후 재생성하세요.',
                'briefing' => [
                    'what_changed' => '테스트용 변화 설명입니다.',
                    'why_changed' => 'Mock 데이터이므로 실제 사건이 아닙니다.',
                    'why_important' => 'UI 검수용입니다.',
                    'future_impact' => '프로덕션에서는 LLM+웹검색 결과가 들어갑니다.',
                ],
                'sources' => [
                    [
                        'name' => 'Reuters',
                        'url' => 'https://www.reuters.com/world/',
                        'article_title' => 'World News',
                    ],
                ],
                'poll' => [
                    'question' => sprintf('이 변화 #%d에 대해 어떻게 보시나요?', $i),
                    'options' => ['긍정적', '부정적', '중립적', '아직 모르겠다'],
                ],
            ];
        }
        return [
            'changes' => $changes,
            'raw' => json_encode(['changes' => $changes], JSON_UNESCAPED_UNICODE),
            'cost_usd' => 0.0,
        ];
    }
}
