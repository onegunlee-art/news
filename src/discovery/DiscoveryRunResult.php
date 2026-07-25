<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryRunResult
{
    /** @param array<string, mixed> $edition */
    public function __construct(
        public readonly array $edition,
        public readonly array $verifiedChanges,
        public readonly array $discardedChanges,
        public readonly int $durationSec,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'edition' => $this->edition,
            'verified_count' => count($this->verifiedChanges),
            'discarded_count' => count($this->discardedChanges),
            'discarded' => $this->discardedChanges,
            'duration_sec' => $this->durationSec,
        ];
    }
}
