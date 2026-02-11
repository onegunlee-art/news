<?php
/**
 * Supabase REST API Service
 *
 * Supabase PostgREST/RPC 래퍼. pgvector 유사도 검색, CRUD 지원.
 *
 * @package Agents\Services
 */

declare(strict_types=1);

namespace Agents\Services;

class SupabaseService
{
    private string $url;
    private string $serviceRoleKey;
    private int $timeout;
    private string $lastError = '';

    public function __construct(array $config = [])
    {
        $projectRoot = dirname(__DIR__, 3);
        $defaultConfig = file_exists($projectRoot . '/config/supabase.php')
            ? require $projectRoot . '/config/supabase.php'
            : [];
        $merged = array_merge($defaultConfig, $config);

        $this->url = rtrim($merged['url'] ?? '', '/');
        $this->serviceRoleKey = $merged['service_role_key'] ?? '';
        $this->timeout = (int) ($merged['timeout'] ?? 30);
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->serviceRoleKey !== '';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    // ── CRUD ────────────────────────────────────────────

    /**
     * INSERT row(s) into a table. Returns inserted rows.
     */
    public function insert(string $table, array $data): ?array
    {
        return $this->request('POST', "/rest/v1/{$table}", $data, [
            'Prefer: return=representation',
        ]);
    }

    /**
     * SELECT rows. $query is a PostgREST query string, e.g. "conversation_id=eq.{$id}&order=created_at.asc".
     */
    public function select(string $table, string $query = '', int $limit = 100): ?array
    {
        $qs = $query !== '' ? "?{$query}&limit={$limit}" : "?limit={$limit}";
        return $this->request('GET', "/rest/v1/{$table}{$qs}");
    }

    /**
     * UPDATE rows matching $query. Returns updated rows.
     */
    public function update(string $table, string $query, array $data): ?array
    {
        return $this->request('PATCH', "/rest/v1/{$table}?{$query}", $data, [
            'Prefer: return=representation',
        ]);
    }

    /**
     * DELETE rows matching $query.
     */
    public function delete(string $table, string $query): bool
    {
        $res = $this->request('DELETE', "/rest/v1/{$table}?{$query}");
        return $res !== null;
    }

    // ── RPC (pgvector search etc.) ──────────────────────

    /**
     * Call a Supabase RPC function.
     */
    public function rpc(string $functionName, array $params = []): ?array
    {
        return $this->request('POST', "/rest/v1/rpc/{$functionName}", $params);
    }

    /**
     * pgvector cosine similarity search via RPC.
     *
     * Requires a Supabase SQL function like:
     *   match_critique_embeddings(query_embedding vector(1536), match_count int)
     */
    public function vectorSearch(string $rpcName, array $queryEmbedding, int $topK = 5): ?array
    {
        return $this->rpc($rpcName, [
            'query_embedding' => $queryEmbedding,
            'match_count' => $topK,
        ]);
    }

    // ── HTTP ────────────────────────────────────────────

    /**
     * Low-level HTTP request to Supabase (429/5xx 자동 재시도 포함).
     */
    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): ?array
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase not configured (url or service_role_key missing)';
            return null;
        }

        $url = $this->url . $path;
        $headers = array_merge([
            'apikey: ' . $this->serviceRoleKey,
            'Authorization: Bearer ' . $this->serviceRoleKey,
            'Content-Type: application/json',
        ], $extraHeaders);

        $maxRetries = 3;
        $attempt = 0;
        $response = '';
        $httpCode = 0;

        while (true) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            if ($body !== null && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $this->lastError = "Supabase curl error: {$curlError}";
                error_log($this->lastError);
                return null;
            }

            // 429 Rate Limit → 재시도
            if ($httpCode === 429 && $attempt <= $maxRetries) {
                $waitSec = min(pow(2, $attempt), 30);
                error_log("Supabase 429 rate limit (attempt {$attempt}/{$maxRetries}). Waiting {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // 5xx 서버 에러 → 재시도
            if ($httpCode >= 500 && $httpCode < 600 && $attempt <= $maxRetries) {
                $waitSec = min(pow(2, $attempt), 30);
                error_log("Supabase {$httpCode} server error (attempt {$attempt}/{$maxRetries}). Retrying in {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // 다른 에러이거나 재시도 소진
            break;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->lastError = "Supabase HTTP {$httpCode}: " . mb_substr((string) $response, 0, 500);
            error_log($this->lastError);
            return null;
        }

        // DELETE with no body returns empty
        if ($response === '' || $response === false) {
            return [];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Supabase JSON decode error: ' . json_last_error_msg();
            error_log($this->lastError);
            return null;
        }

        return $data;
    }
}
