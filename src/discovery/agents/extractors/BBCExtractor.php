<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

final class BBCExtractor extends GenericExtractor
{
    public function supports(string $domain): bool
    {
        return str_contains($domain, 'bbc.co.uk') || str_contains($domain, 'bbc.com');
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        $text = parent::extract($url, $timeoutSec);
        // BBC pages often include navigation noise; trim common footer patterns
        $text = preg_replace('/\b(BBC News|Related Topics|More on this story)\b.*$/iu', '', $text) ?? $text;

        return trim($text);
    }
}
