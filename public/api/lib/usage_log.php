<?php
/**
 * API 사용량 로깅 (api_usage_logs 테이블)
 * OpenAI, Google TTS 등 모든 API 호출 시 사용. 실패해도 메인 플로우에 영향 없음.
 */

declare(strict_types=1);

function log_api_usage(array $data): void
{
    static $pdo = null;
    static $tableChecked = false;

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
        if ($pdo === null) {
            $projectRoot = dirname(__DIR__, 3) . '/';
            if (file_exists($projectRoot . 'config/database.php')) {
                $cfg = require $projectRoot . 'config/database.php';
                $dsn = "mysql:host=" . ($cfg['host'] ?? 'localhost') . ";dbname=" . ($cfg['database'] ?? $cfg['dbname'] ?? 'ailand') . ";charset=" . ($cfg['charset'] ?? 'utf8mb4');
                $pdo = new PDO($dsn, $cfg['username'] ?? 'ailand', $cfg['password'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } else {
                return;
            }
        }

        if (!$tableChecked) {
            $pdo->query("SELECT 1 FROM api_usage_logs LIMIT 1");
            $tableChecked = true;
        }

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
        error_log('log_api_usage: ' . $e->getMessage());
    }
}
