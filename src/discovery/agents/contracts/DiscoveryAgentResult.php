<?php
declare(strict_types=1);

namespace Discovery\Agents\Contracts;

final class DiscoveryAgentResult
{
    /**
     * @param list<array<string, mixed>> $output
     * @param list<array<string, mixed>> $discarded
     */
    public function __construct(
        public readonly array $output,
        public readonly int $inputCount,
        public readonly int $outputCount,
        public readonly array $discarded = [],
        public readonly ?string $error = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toLogEntry(): array
    {
        return [
            'input_count' => $this->inputCount,
            'output_count' => $this->outputCount,
            'discarded_count' => count($this->discarded),
            'error' => $this->error,
        ];
    }
}
