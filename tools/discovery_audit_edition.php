<?php
declare(strict_types=1);

/**
 * Deep audit for a discovery edition (draft or published).
 * Usage: php tools/discovery_audit_edition.php [YYYY-MM-DD]
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$config = discoveryConfig($root);
$pdo = discoveryGetDb($root);
$repo = new Discovery\DiscoveryRepository($pdo);
$whitelist = new Discovery\SourceWhitelist(
    $config['source_whitelist'] ?? [],
    $config['source_blocklist'] ?? [],
);
$gate = new Discovery\DiscoveryQualityGate();
$urlGuard = new Discovery\DiscoveryUrlGuard();

$date = $argv[1] ?? discoveryTodayKst();
$edition = $repo->findEditionByDate($date);
if (!$edition) {
    fwrite(STDERR, "No edition for {$date}\n");
    exit(1);
}

$changes = $repo->getChangesForEdition((int) $edition['id']);
echo "=== Discovery Edition Audit date={$date} ===\n";
echo json_encode($edition, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo 'change_count=' . count($changes) . "\n\n";

$stmt = $pdo->prepare('SELECT * FROM discovery_runs WHERE edition_date = ? ORDER BY run_at DESC LIMIT 3');
$stmt->execute([$date]);
$runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "--- Latest discovery_runs ---\n";
foreach ($runs as $run) {
    echo sprintf(
        "run_at=%s generated=%s discarded=%s duration_sec=%s cost_usd=%s\n",
        $run['run_at'] ?? '?',
        $run['generated_count'] ?? '?',
        $run['discarded_count'] ?? '?',
        $run['duration_sec'] ?? '?',
        $run['cost_usd'] ?? 'null'
    );
    $reasons = json_decode((string) ($run['reasons_json'] ?? '[]'), true);
    if (is_array($reasons)) {
        $meta = $reasons[0]['generation_mode'] ?? null;
        if ($meta) {
            echo "  (reasons_json may contain meta in discarded list)\n";
        }
    }
}
echo "\n";

$domains = [];
foreach ($changes as $i => $change) {
    $rank = (int) ($change['rank'] ?? ($i + 1));
    $src = $change['sources'][0] ?? [];
    $url = (string) ($src['url'] ?? '');
    $domain = $whitelist->hostLabel($url);
    $domains[] = $domain;

    echo "========== #{$rank} [{$change['category']}] {$change['title']} ==========\n";
    echo "source_name: " . ($src['name'] ?? '?') . "\n";
    echo "domain: {$domain}\n";
    echo "url: {$url}\n";
    echo 'whitelist=' . ($url !== '' && $whitelist->isWhitelistedUrl($url) ? 'PASS' : 'FAIL') . "\n";
    echo 'blocked=' . ($url !== '' && $whitelist->isBlockedUrl($url) ? 'YES' : 'no') . "\n";
    echo 'hallucination_pattern=' . ($url !== '' && $urlGuard->looksHallucinated($url) ? 'SUSPECT' : 'ok') . "\n";
    echo 'importance=' . ($gate->passesImportanceCategory($change) ? 'PASS' : 'FAIL') . "\n";
    echo 'completeness=' . ($gate->passesCompleteness($change) ? 'PASS' : 'FAIL') . "\n";

    $briefing = is_array($change['briefing'] ?? null) ? $change['briefing'] : [];
    echo "\n--- briefing 4단 ---\n";
    echo "what_changed: " . ($briefing['what_changed'] ?? '') . "\n\n";
    echo "why_changed: " . ($briefing['why_changed'] ?? '') . "\n\n";
    echo "why_important: " . ($briefing['why_important'] ?? '') . "\n\n";
    echo "future_impact: " . ($briefing['future_impact'] ?? '') . "\n\n";
    $highlights = is_array($briefing['highlights'] ?? null) ? $briefing['highlights'] : [];
    echo "highlights:\n";
    foreach ($highlights as $h) {
        echo "  - {$h}\n";
    }

    echo "\n--- URL live check ---\n";
    if ($url === '') {
        echo "SKIP: no url\n\n";
        continue;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GistDiscoveryAudit/1.0)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    echo "http={$httpCode} final_url={$finalUrl}\n";
    if ($body !== false && $httpCode >= 200 && $httpCode < 400) {
        $plain = mb_strtolower(strip_tags(html_entity_decode((string) $body, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $title = mb_strtolower((string) ($change['title'] ?? ''));
        $titleTokens = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?: '') ?: [];
        $hits = 0;
        foreach ($titleTokens as $tok) {
            $tok = trim($tok);
            if (mb_strlen($tok) >= 3 && str_contains($plain, $tok)) {
                $hits++;
            }
        }
        echo "title_keyword_hits={$hits}\n";
        echo 'page_snippet: ' . mb_substr(preg_replace('/\s+/u', ' ', $plain) ?? $plain, 0, 240) . "...\n";
    } else {
        echo "FETCH_FAILED\n";
    }
    echo "\n";
}

echo "=== Domain summary ===\n";
echo implode(', ', array_unique($domains)) . "\n";
echo 'nature.com_in_whitelist=' . (in_array('nature.com', $config['source_whitelist'] ?? [], true) ? 'yes' : 'NO') . "\n";
