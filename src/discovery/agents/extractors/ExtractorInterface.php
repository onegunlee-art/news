<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

interface ExtractorInterface
{
    public function supports(string $domain): bool;

    public function extract(string $url, int $timeoutSec = 20): string;
}

final class ExtractionException extends \RuntimeException
{
}
