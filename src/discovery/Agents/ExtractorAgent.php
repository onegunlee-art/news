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

    /** @var array<string, array{full: int, summary: int, failed: int}> */
    private array $domainStats = [];

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
        $maxBodyChars = (int) ($config['pipeline']['max_body_chars'] ?? 15000);

        $output = [];
        $discarded = [];
        $this->domainStats = [];

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
            $this->ensureDomainStats($domain);

            $extractor = $this->resolveExtractor($domain);
            $extractorName = $this->getExtractorName($extractor);

            try {
                $body = $extractor->extract($url, $timeoutSec);
                $bodyLen = mb_strlen($body);
                $body = mb_substr($body, 0, $maxBodyChars);

                $article['body_text'] = $body;
                $article['extraction_status'] = 'full';
                $article['extraction_chars'] = $bodyLen;
                $article['extractor_used'] = $extractorName;
                $article['domain'] = $domain;
                $output[] = $article;

                $this->domainStats[$domain]['full']++;
            } catch (ExtractionException $e) {
                $summary = trim((string) ($article['description'] ?? ''));
                if ($summary === '' || mb_strlen($summary) < 50) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'extraction_failed',
                        'detail' => $e->getMessage(),
                        'url' => $url,
                        'domain' => $domain,
                        'extractor' => $extractorName,
                    ];
                    $this->domainStats[$domain]['failed']++;
                    continue;
                }

                $article['body_text'] = mb_substr($summary, 0, $maxBodyChars);
                $article['extraction_status'] = 'summary_only';
                $article['extraction_chars'] = mb_strlen($summary);
                $article['extractor_used'] = 'summary_fallback';
                $article['domain'] = $domain;
                $output[] = $article;

                $this->domainStats[$domain]['summary']++;
            }
        }

        $fullCount = 0;
        $summaryCount = 0;
        foreach ($output as $art) {
            if (($art['extraction_status'] ?? '') === 'full') {
                $fullCount++;
            } else {
                $summaryCount++;
            }
        }

        return new DiscoveryAgentResult(
            [
                'articles' => $output,
                'extraction_stats' => [
                    'full' => $fullCount,
                    'summary_only' => $summaryCount,
                    'failed' => count($discarded),
                    'by_domain' => $this->domainStats,
                ],
            ],
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

    private function getExtractorName(ExtractorInterface $extractor): string
    {
        $class = get_class($extractor);
        return basename(str_replace('\\', '/', $class));
    }

    private function hostLabel(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    private function ensureDomainStats(string $domain): void
    {
        if (!isset($this->domainStats[$domain])) {
            $this->domainStats[$domain] = ['full' => 0, 'summary' => 0, 'failed' => 0];
        }
    }
}
