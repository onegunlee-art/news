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

    /** @return array{changes: list<array<string,mixed>>, raw: string, cost_usd: float|null} */
    public function generateDailyChanges(string $date): array
    {
        return $this->llm->generateDailyChanges($date, $this->config);
    }
}
