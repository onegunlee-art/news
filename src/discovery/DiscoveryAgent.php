<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryAgent
{
    public function __construct(
        private readonly DiscoveryLLMClient $llm,
        private readonly array $config,
    ) {
    }

    /** @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null, catalog_count?:int, generation_mode?:string} */
    public function generateDailyChanges(string $date): array
    {
        $whitelist = new SourceWhitelist(
            $this->config['source_whitelist'] ?? [],
            $this->config['source_blocklist'] ?? [],
        );
        $catalog = new DiscoveryArticleCatalog($this->config, $whitelist);
        $articles = $catalog->fetch($date);

        $result = $this->llm->generateDailyChanges($date, $this->config, $articles);
        $result['catalog_count'] = count($articles);
        $result['generation_mode'] = $result['generation_mode'] ?? (count($articles) >= (int) ($this->config['min_catalog_articles'] ?? 8) ? 'rss_catalog' : 'web_search');

        return $result;
    }
}
