<?php
declare(strict_types=1);

namespace Discovery;

use PDO;

final class DiscoveryRateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function hit(string $bucketKey, int $maxHits, int $windowSeconds): void
    {
        $windowStart = gmdate('Y-m-d H:i:00', (int) floor(time() / $windowSeconds) * $windowSeconds);
        $stmt = $this->pdo->prepare(
            'INSERT INTO discovery_rate_limits (bucket_key, window_start, hit_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hit_count = hit_count + 1'
        );
        $stmt->execute([$bucketKey, $windowStart]);

        $check = $this->pdo->prepare(
            'SELECT hit_count FROM discovery_rate_limits WHERE bucket_key = ? AND window_start = ? LIMIT 1'
        );
        $check->execute([$bucketKey, $windowStart]);
        $count = (int) ($check->fetchColumn() ?: 0);
        if ($count > $maxHits) {
            throw new \RuntimeException('요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.', 429);
        }
    }

    public static function ipHash(): string
    {
        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return hash('sha256', $ip . '|discovery');
    }
}
