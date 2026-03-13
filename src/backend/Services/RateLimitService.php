<?php
/**
 * Rate Limit 서비스
 * 
 * Redis 기반 Rate Limiting (파일 기반 fallback 지원)
 * 
 * @author News Context Analysis Team
 * @version 2.0.0
 */

declare(strict_types=1);

namespace App\Services;

/**
 * RateLimitService 클래스
 */
final class RateLimitService
{
    private static ?RateLimitService $instance = null;
    private ?\Redis $redis = null;
    private bool $useRedis = false;
    private string $cacheDir;

    private function __construct()
    {
        $this->cacheDir = dirname(__DIR__, 2) . '/storage/cache/rate_limit';
        $this->initRedis();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Redis 연결 초기화
     */
    private function initRedis(): void
    {
        $redisHost = getenv('REDIS_HOST') ?: null;
        $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
        $redisPassword = getenv('REDIS_PASSWORD') ?: null;

        if (!$redisHost || !extension_loaded('redis')) {
            $this->useRedis = false;
            return;
        }

        try {
            $this->redis = new \Redis();
            $connected = @$this->redis->connect($redisHost, $redisPort, 2.0);
            
            if (!$connected) {
                $this->useRedis = false;
                $this->redis = null;
                return;
            }

            if ($redisPassword) {
                $this->redis->auth($redisPassword);
            }

            $this->redis->setOption(\Redis::OPT_PREFIX, 'thegist:ratelimit:');
            $this->useRedis = true;
        } catch (\Exception $e) {
            $this->useRedis = false;
            $this->redis = null;
            error_log("Redis connection failed: " . $e->getMessage());
        }
    }

    /**
     * Rate Limit 확인 및 증가
     * 
     * @param string $key 식별자 (IP 또는 사용자 ID)
     * @param int $maxRequests 최대 요청 수
     * @param int $windowSeconds 윈도우 크기 (초)
     * @return array{allowed: bool, remaining: int, reset: int, retry_after: int|null}
     */
    public function check(string $key, int $maxRequests = 100, int $windowSeconds = 60): array
    {
        if ($this->useRedis && $this->redis) {
            return $this->checkRedis($key, $maxRequests, $windowSeconds);
        }
        return $this->checkFile($key, $maxRequests, $windowSeconds);
    }

    /**
     * Redis 기반 Rate Limit (Sliding Window Counter)
     */
    private function checkRedis(string $key, int $maxRequests, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;

        try {
            // Sorted Set에서 윈도우 외 항목 제거
            $this->redis->zRemRangeByScore($key, '-inf', (string)$windowStart);

            // 현재 요청 수 확인
            $currentCount = (int)$this->redis->zCard($key);

            if ($currentCount >= $maxRequests) {
                // 가장 오래된 요청 시간
                $oldest = $this->redis->zRange($key, 0, 0, true);
                $oldestTime = !empty($oldest) ? (int)array_keys($oldest)[0] : $now;
                $retryAfter = max(1, ($oldestTime + $windowSeconds) - $now);

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => $now + $windowSeconds,
                    'retry_after' => $retryAfter,
                ];
            }

            // 새 요청 기록 (score = timestamp, member = unique ID)
            $uniqueId = $now . '_' . uniqid('', true);
            $this->redis->zAdd($key, $now, $uniqueId);
            $this->redis->expire($key, $windowSeconds + 10);

            return [
                'allowed' => true,
                'remaining' => $maxRequests - $currentCount - 1,
                'reset' => $now + $windowSeconds,
                'retry_after' => null,
            ];
        } catch (\Exception $e) {
            error_log("Redis rate limit error: " . $e->getMessage());
            return $this->checkFile($key, $maxRequests, $windowSeconds);
        }
    }

    /**
     * 파일 기반 Rate Limit (fallback)
     */
    private function checkFile(string $key, int $maxRequests, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        $file = $this->cacheDir . '/' . md5($key) . '.json';
        $data = [];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true) ?? [];
            }
        }

        // 윈도우 내 요청만 필터링
        $requests = array_filter($data['requests'] ?? [], fn($t) => $t > $windowStart);

        if (count($requests) >= $maxRequests) {
            $oldestTime = !empty($requests) ? min($requests) : $now;
            $retryAfter = max(1, ($oldestTime + $windowSeconds) - $now);

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $now + $windowSeconds,
                'retry_after' => $retryAfter,
            ];
        }

        // 새 요청 기록
        $requests[] = $now;
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        @file_put_contents($file, json_encode(['requests' => array_values($requests)]), LOCK_EX);

        return [
            'allowed' => true,
            'remaining' => $maxRequests - count($requests),
            'reset' => $now + $windowSeconds,
            'retry_after' => null,
        ];
    }

    /**
     * 특정 키의 Rate Limit 초기화
     */
    public function reset(string $key): void
    {
        if ($this->useRedis && $this->redis) {
            $this->redis->del($key);
        }
        
        $file = $this->cacheDir . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Redis 사용 여부 확인
     */
    public function isUsingRedis(): bool
    {
        return $this->useRedis;
    }
}
