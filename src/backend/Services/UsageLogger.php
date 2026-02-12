<?php
/**
 * API 사용량 로깅 서비스
 * OpenAI, Google TTS, Kakao 등 모든 API 호출 시 api_usage_logs 테이블에 저장
 *
 * @package App\Services
 */

declare(strict_types=1);

namespace App\Services;

use PDO;

class UsageLogger
{
    private ?PDO $pdo = null;
    private bool $enabled = true;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function setConnection(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * 사용량 로그 저장 (비동기/실패해도 무시 - 메인 플로우에 영향 없음)
     */
    public function log(array $data): void
    {
        if (!$this->enabled) {
            return;
        }

        $pdo = $this->getConnection();
        if (!$pdo) {
            return;
        }

        $provider = $data['provider'] ?? 'unknown';
        $endpoint = $data['endpoint'] ?? 'unknown';
        $model = $data['model'] ?? null;
        $inputTokens = (int) ($data['input_tokens'] ?? 0);
        $outputTokens = (int) ($data['output_tokens'] ?? 0);
        $images = (int) ($data['images'] ?? 0);
        $characters = (int) ($data['characters'] ?? 0);
        $requests = (int) ($data['requests'] ?? 1);
        $estimatedCostUsd = isset($data['estimated_cost_usd']) ? (float) $data['estimated_cost_usd'] : null;
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null;

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO api_usage_logs (provider, endpoint, model, input_tokens, output_tokens, images, characters, requests, estimated_cost_usd, metadata) 
                 VALUES (:provider, :endpoint, :model, :input_tokens, :output_tokens, :images, :characters, :requests, :estimated_cost_usd, :metadata)"
            );
            $stmt->execute([
                ':provider' => $provider,
                ':endpoint' => $endpoint,
                ':model' => $model,
                ':input_tokens' => $inputTokens,
                ':output_tokens' => $outputTokens,
                ':images' => $images,
                ':characters' => $characters,
                ':requests' => $requests,
                ':estimated_cost_usd' => $estimatedCostUsd,
                ':metadata' => $metadata
            ]);
        } catch (\Throwable $e) {
            error_log('UsageLogger: ' . $e->getMessage());
        }
    }

    /**
     * OpenAI Chat 사용량 로깅 헬퍼
     */
    public static function logOpenAIChat(int $inputTokens, int $outputTokens, ?string $model = null, ?float $costUsd = null): void
    {
        self::getInstance()->log([
            'provider' => 'openai',
            'endpoint' => 'chat',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $costUsd
        ]);
    }

    /**
     * OpenAI Embeddings 사용량 로깅 헬퍼
     */
    public static function logOpenAIEmbeddings(int $inputTokens, ?string $model = null, ?float $costUsd = null): void
    {
        self::getInstance()->log([
            'provider' => 'openai',
            'endpoint' => 'embeddings',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => 0,
            'estimated_cost_usd' => $costUsd
        ]);
    }

    /**
     * OpenAI Images (DALL-E) 사용량 로깅 헬퍼
     */
    public static function logOpenAIImages(int $images = 1, ?string $model = null, ?float $costUsd = null): void
    {
        self::getInstance()->log([
            'provider' => 'openai',
            'endpoint' => 'images',
            'model' => $model ?? 'dall-e-3',
            'images' => $images,
            'estimated_cost_usd' => $costUsd
        ]);
    }

    /**
     * OpenAI TTS 사용량 로깅 헬퍼
     */
    public static function logOpenAITTS(int $characters, ?string $model = null, ?float $costUsd = null): void
    {
        self::getInstance()->log([
            'provider' => 'openai',
            'endpoint' => 'tts',
            'model' => $model,
            'characters' => $characters,
            'estimated_cost_usd' => $costUsd
        ]);
    }

    /**
     * Google TTS 사용량 로깅 헬퍼
     */
    public static function logGoogleTTS(int $characters, ?float $costUsd = null): void
    {
        self::getInstance()->log([
            'provider' => 'google_tts',
            'endpoint' => 'tts',
            'characters' => $characters,
            'estimated_cost_usd' => $costUsd
        ]);
    }

    private function getConnection(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            if (file_exists(dirname(__DIR__, 3) . '/config/database.php')) {
                require_once dirname(__DIR__, 2) . '/Core/Database.php';
                $this->pdo = \App\Core\Database::getInstance()->getConnection();
                return $this->pdo;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
