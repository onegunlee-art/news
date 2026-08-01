<?php
declare(strict_types=1);

namespace Discovery\Agents;

use Discovery\Agents\Contracts\DiscoveryAgentInterface;
use Discovery\Agents\Contracts\DiscoveryAgentResult;
use Discovery\Agents\Extractors\BBCExtractor;
use Discovery\Agents\Extractors\ExtractionException;
use Discovery\Agents\Extractors\ExtractorInterface;
use Discovery\Agents\Extractors\GenericExtractor;
use Discovery\Agents\Extractors\GuardianExtractor;

final class ExtractorAgent implements DiscoveryAgentInterface
{
    /** @var list<ExtractorInterface> */
    private array $extractors;

    private GenericExtractor $fallback;

    public function __construct()
    {
        $this->extractors = [
            new BBCExtractor(),
            new GuardianExtractor(),
        ];
        $this->fallback = new GenericExtractor();
    }

    public function getName(): string
    {
        return 'extractor';
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $config */
    public function process(array $input, array $config): DiscoveryAgentResult
    {
        /** @var list<array<string, mixed>> $articles */
        $articles = $input['articles'] ?? [];
        $timeoutSec = (int) ($config['pipeline']['extractor_timeout_sec'] ?? 20);
        $maxBodyChars = (int) ($config['pipeline']['max_body_chars'] ?? 12000);

        $output = [];
        $discarded = [];

        foreach ($articles as $article) {
            $url = trim((string) ($article['url'] ?? ''));
            $title = trim((string) ($article['title'] ?? ''));
            if ($url === '' || $title === '') {
                $discarded[] = [
                    'title' => $title ?: '(no title)',
                    'reason' => 'missing_url_or_title',
                ];
                continue;
            }

            $domain = $this->hostLabel($url);
            $extractor = $this->resolveExtractor($domain);

            try {
                $body = $extractor->extract($url, $timeoutSec);
                $body = mb_substr($body, 0, $maxBodyChars);
                $article['body_text'] = $body;
                $article['extraction_status'] = 'full';
                $article['domain'] = $domain;
                $output[] = $article;
            } catch (ExtractionException $e) {
                $summary = trim((string) ($article['description'] ?? ''));
                if ($summary === '' || mb_strlen($summary) < 80) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'extraction_failed',
                        'detail' => $e->getMessage(),
                        'url' => $url,
                    ];
                    continue;
                }
                $article['body_text'] = mb_substr($summary, 0, $maxBodyChars);
                $article['extraction_status'] = 'summary_only';
                $article['domain'] = $domain;
                $output[] = $article;
            }
        }

        return new DiscoveryAgentResult(
            ['articles' => $output],
            count($articles),
            count($output),
            $discarded,
        );
    }

    private function resolveExtractor(string $domain): ExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($domain)) {
                return $extractor;
            }
        }

        return $this->fallback;
    }

    private function hostLabel(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        return preg_replace('/^www\./', '', $host) ?? $host;
    }
}
