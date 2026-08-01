<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryRunReporter
{
    public function __construct(
        private readonly SourceWhitelist $whitelist,
    ) {
    }

    /** @param list<array<string,mixed>> $verified @param list<array<string,mixed>> $discarded */
    public function printReport(array $verified, array $discarded): void
    {
        echo "\n=== §8 Quality Gate Report (v2) ===\n";
        echo 'final_count=' . count($verified) . ' discarded_count=' . count($discarded) . "\n";

        echo "\n--- (a) Final changes (title + source domain) ---\n";
        $domains = [];
        foreach ($verified as $i => $change) {
            $src = $change['sources'][0] ?? [];
            $url = (string) ($src['url'] ?? '');
            $domain = $this->whitelist->hostLabel($url);
            $domains[] = $domain;
            $blocked = $url !== '' && $this->whitelist->isBlockedUrl($url) ? 'BLOCKED' : 'ok';
            $wl = $url !== '' && $this->whitelist->isWhitelistedUrl($url) ? 'whitelist_ok' : 'whitelist_FAIL';
            echo sprintf(
                "#%d [%s] %s\n  source: %s | domain: %s | %s | %s\n",
                $i + 1,
                $change['category'] ?? '?',
                $change['title'] ?? '',
                $src['name'] ?? $domain,
                $domain,
                $wl,
                $blocked
            );
        }
        echo "\n  final_domains: " . implode(', ', array_unique($domains)) . "\n";

        echo "\n--- (b) Discarded by reason ---\n";
        $byReason = [];
        foreach ($discarded as $item) {
            $reason = (string) ($item['reason'] ?? 'unknown');
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }
        foreach ($byReason as $reason => $count) {
            echo sprintf("  %s: %d\n", $reason, $count);
        }
        foreach ($discarded as $item) {
            $detail = isset($item['detail']) ? ' | ' . $item['detail'] : '';
            $merged = isset($item['merged_into']) ? ' -> merged_into: ' . $item['merged_into'] : '';
            echo sprintf(
                "  - [%s] %s%s%s\n",
                $item['reason'] ?? '?',
                mb_substr((string) ($item['title'] ?? ''), 0, 80),
                $detail,
                $merged
            );
        }

        $allWhitelisted = true;
        $anyKoreanSource = false;
        foreach ($verified as $change) {
            $sources = is_array($change['sources'] ?? null) ? $change['sources'] : [];
            foreach ($sources as $source) {
                if (!is_array($source)) {
                    continue;
                }
                $url = (string) ($source['url'] ?? '');
                if ($url === '') {
                    continue;
                }
                if ($this->whitelist->isBlockedUrl($url)) {
                    $anyKoreanSource = true;
                }
                if (!$this->whitelist->isWhitelistedUrl($url)) {
                    $allWhitelisted = false;
                }
            }
        }
        echo "\n--- (c) All sources non-Korean whitelist? " . ($allWhitelisted && !$anyKoreanSource ? 'YES' : 'NO') . " ---\n";

        echo "\n--- (d) Korea-related items (overseas source check) ---\n";
        $gate = new DiscoveryQualityGate();
        $koreaHits = 0;
        foreach ($verified as $change) {
            $text = ($change['title'] ?? '') . ' ' . ($change['summary'] ?? '');
            if (preg_match('/(한국|korea|south korea|seoul|반도체)/iu', $text) !== 1) {
                continue;
            }
            $koreaHits++;
            $domain = $this->whitelist->hostLabel((string) (($change['sources'][0]['url'] ?? '')));
            echo sprintf(
                "  - %s | source_domain=%s (must be overseas whitelist)\n",
                mb_substr((string) ($change['title'] ?? ''), 0, 70),
                $domain
            );
        }
        if ($koreaHits === 0) {
            echo "  (none)\n";
        }

        echo "\n--- (e) Entertainment/sports/completeness check ---\n";
        foreach ($verified as $change) {
            $importance = $gate->passesImportanceCategory($change) ? 'ok' : 'WARN_entertainment_or_low_impact';
            $complete = $gate->passesCompleteness($change) ? 'ok' : 'WARN_incomplete';
            echo sprintf(
                "  %s | importance=%s completeness=%s\n",
                mb_substr((string) ($change['title'] ?? ''), 0, 60),
                $importance,
                $complete
            );
        }
    }
}
