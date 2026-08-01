<?php
declare(strict_types=1);

namespace Discovery\Agents\Contracts;

interface DiscoveryAgentInterface
{
    public function getName(): string;

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $config
     */
    public function process(array $input, array $config): DiscoveryAgentResult;
}
