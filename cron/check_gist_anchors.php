<?php
declare(strict_types=1);

/**
 * 지정 주차 diplomacy gist 앵커(기사) 건수 확인
 * Usage: php cron/check_gist_anchors.php [2026-W21]
 */
require_once __DIR__ . '/../src/backend/bootstrap_intelligence.php';
require_once __DIR__ . '/../src/backend/autoload.php';

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);

    $reportWeek = $argv[1] ?? (new DateTime('today'))->format('o-\WW');
    $year = (int) substr($reportWeek, 0, 4);
    $weekNum = (int) ltrim(substr($reportWeek, 5), 'W');
    $dto = new DateTime();
    $dto->setISODate($year, $weekNum);
    $start = (clone $dto)->modify('monday this week')->format('Y-m-d');
    $end = (clone $dto)->modify('sunday this week')->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT id, title, published_at
         FROM news
         WHERE status = 'published'
           AND category_parent = 'diplomacy'
           AND published_at BETWEEN :start AND :end
           AND narration IS NOT NULL AND CHAR_LENGTH(narration) > 100
         ORDER BY published_at DESC
         LIMIT 10"
    );
    $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
    $rows = $stmt->fetchAll() ?: [];

    $result = [
        'report_week' => $reportWeek,
        'period' => ['start' => $start, 'end' => $end],
        'gist_anchor_count' => count($rows),
        'anchors' => array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'title' => (string) ($r['title'] ?? ''),
            'published_at' => (string) ($r['published_at'] ?? ''),
        ], $rows),
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
