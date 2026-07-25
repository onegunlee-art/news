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
- Return STRICT JSON only — no markdown fences, no commentary outside the JSON object.
- If you cannot find enough verified recent changes, return fewer — never fabricate.
- sources[].url must be real article URLs from search results (prefer Reuters, BBC, FT, AP, official EU/US/UN sources).
- poll must be neutral, no right answer, 4 distinct options.
- briefing must have keys: what_changed, why_changed, why_important, future_impact, highlights (array of 4-6 bullet strings).
- OUTPUT LANGUAGE: Korean 합니다체. SUBJECT MATTER: global — NOT Korea-domestic-only news.
- EXCLUDE: Korean local/municipal news, domestic-only corporate PR, regional church/school events, K-pop/entertainment without global spillover.
- TARGET ~90% global: US, EU, Middle East, China/Taiwan, international geopolitics, macro, energy, global tech.
- Each change must have international significance or cross-border impact.
- GOOD examples: Yemen Houthi Red Sea blockade, Fed rate decision, DeepSeek AI chip, EU AI Act enforcement, UK fiscal policy shift.
- BAD examples: Daegu city AI hub selection, local church photo album, Korean regional festival.
SYS;

        $targets = $discoveryConfig['category_targets'] ?? [];
        $user = sprintf(
            "Edition date (KST): %s\nTarget: up to 9 changes.\nCategory targets: geopolitics=%d, business=%d, tech=%d, climate=%d, other=%d\nGlobal-only (~90%%). Korean language output, global subject matter.\n\nReturn JSON:\n{\n  \"changes\": [\n    {\n      \"category\": \"geopolitics|business|tech|climate|other\",\n      \"title\": \"한 줄 제목\",\n      \"summary\": \"카드용 2~3줄 요약\",\n      \"briefing\": {\"what_changed\":\"\",\"why_changed\":\"\",\"why_important\":\"\",\"future_impact\":\"\",\"highlights\":[\"\",\"\",\"\",\"\"]},\n      \"sources\": [{\"name\":\"\",\"url\":\"\",\"article_title\":\"\"}],\n      \"poll\": {\"question\":\"\",\"options\":[\"\",\"\",\"\",\"\"]}\n    }\n  ]\n}",
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
        ];

        $raw = $this->callResponsesApi($payload);
        $decoded = $this->parseJsonFromText($raw);
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
            $highlights = $briefing['highlights'] ?? [];
            if (!is_array($highlights)) {
                $highlights = [];
            }
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
            [
                'category' => 'business',
                'title' => '미 연준, 기준금리 동결 결정',
                'summary' => '연준이 기준금리를 동결하면서 글로벌 자금 흐름과 달러 강세 기대가 재조정되고 있습니다.',
                'briefing' => [
                    'what_changed' => 'FOMC가 기준금리 동결을 결정했습니다.',
                    'why_changed' => '인플레이션 둔화와 고용 지표의 혼조가 겹쳤습니다.',
                    'why_important' => '신흥국 자본 흐름과 글로벌 채권 시장에 직접적 영향을 줍니다.',
                    'future_impact' => '시장은 연내 인하 시점을 재가격할 가능성이 있습니다.',
                    'highlights' => ['금리 동결', '달러·채권 재조정', '신흥국 자금'],
                ],
            ],
            [
                'category' => 'tech',
                'title' => '중국 딥시크, 자체 AI 칩 개발 가속',
                'summary' => '딥시크가 자체 AI 가속 칩 개발을 공개하며 글로벌 반도체·AI 경쟁 구도에 변수가 더해졌습니다.',
                'briefing' => [
                    'what_changed' => '딥시크가 자체 AI 칩 로드맵을 발표했습니다.',
                    'why_changed' => '수출 통제와 공급망 불확실성이 자체 칩 필요성을 키웠습니다.',
                    'why_important' => '글로벌 AI 인프라 경쟁과 GPU 수요 구조에 영향을 줍니다.',
                    'future_impact' => '중국 AI 생태계의 독립성 강화가 가속될 수 있습니다.',
                    'highlights' => ['자체 AI 칩', '공급망 독립', '글로벌 GPU 경쟁'],
                ],
            ],
        ];
        $changes = [];
        foreach ($samples as $i => $sample) {
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
