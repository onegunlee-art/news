<?php
/**
 * P2-A1 — MySQL news.content → 경첩 JSON + confidence + needs_review
 *
 * Usage:
 *   php tools/edu_gist_hinge_extract.php 630
 *   php tools/edu_gist_hinge_extract.php 630 546 631
 *   php tools/edu_gist_hinge_extract.php 630 --stdout-only
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduMysql.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$stdoutOnly = in_array('--stdout-only', $argv ?? [], true);
$numericArgs = array_values(array_filter($argv ?? [], static fn ($a) => is_numeric($a)));
$ids = $numericArgs !== [] ? array_map('intval', $numericArgs) : [];

if ($ids === []) {
    fwrite(STDERR, "Usage: php tools/edu_gist_hinge_extract.php <news_id> [news_id...] [--stdout-only]\n");
    exit(1);
}

try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL unavailable: {$e->getMessage()}\n");
    exit(1);
}

$llm = eduLlm();
$manifest = [];

foreach ($ids as $newsId) {
    echo "Extracting news_id={$newsId} (mysql.news.content)...\n";

    $article = eduHingeLoadMysqlContent($pdo, $newsId);
    if ($article === null) {
        fwrite(STDERR, "  SKIP: news_id={$newsId} — content missing or empty\n");
        continue;
    }

    $result = eduHingeExtractFromContent(
        $llm,
        $newsId,
        $article['title'],
        $article['content']
    );

    if (!$result['ok']) {
        fwrite(STDERR, '  ERROR: ' . ($result['error'] ?? 'unknown') . "\n");
        if (!empty($result['raw'])) {
            fwrite(STDERR, "  raw: " . mb_substr($result['raw'], 0, 200) . "...\n");
        }
        continue;
    }

    $extraction = $result['extraction'];
    $flag = $extraction['needs_review'] ? ' [검증 필요]' : '';
    $conf = $extraction['confidence'] ?? 'null';
    echo "  confidence={$conf}{$flag}\n";
    echo '  hinge: ' . mb_substr((string) ($extraction['hinge'] ?? 'null'), 0, 80) . "\n";

    if (!$stdoutOnly) {
        $path = eduHingeSaveExtraction($extraction);
        echo "  Wrote {$path}\n";
    }

    echo json_encode($extraction, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

    $manifest[] = [
        'news_id' => $newsId,
        'title' => $extraction['title'],
        'confidence' => $extraction['confidence'],
        'needs_review' => $extraction['needs_review'],
        'extracted_at' => $extraction['extracted_at'],
    ];
}

if (!$stdoutOnly && $manifest !== []) {
    eduHingeEnsureDirs();
    $manifestPath = eduHingeExtractionsDir() . '/manifest.json';
    $existing = [];
    if (is_file($manifestPath)) {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (is_array($row) && isset($row['news_id'])) {
                    $existing[(int) $row['news_id']] = $row;
                }
            }
        }
    }
    foreach ($manifest as $row) {
        $existing[(int) $row['news_id']] = $row;
    }
    ksort($existing);
    file_put_contents(
        $manifestPath,
        json_encode(array_values($existing), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
    );
    echo "Updated {$manifestPath}\n";
}
