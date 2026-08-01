<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

final class GuardianExtractor extends GenericExtractor
{
    public function supports(string $domain): bool
    {
        return str_contains($domain, 'theguardian.com');
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        return parent::extract($url, $timeoutSec);
    }
}
