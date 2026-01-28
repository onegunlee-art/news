<?php
/**
 * HTTP 클라이언트 유틸리티 클래스
 * 
 * cURL 기반의 HTTP 요청 처리를 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Utils;

use RuntimeException;

/**
 * HttpClient 클래스
 * 
 * 외부 API 호출을 위한 HTTP 클라이언트입니다.
 */
final class HttpClient
{
    private array $defaultHeaders = [];
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private bool $verifySSL = true;

    /**
     * 기본 헤더 설정
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * 타임아웃 설정
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 연결 타임아웃 설정
     */
    public function setConnectTimeout(int $timeout): self
    {
        $this->connectTimeout = $timeout;
        return $this;
    }

    /**
     * SSL 검증 설정
     */
    public function setVerifySSL(bool $verify): self
    {
        $this->verifySSL = $verify;
        return $this;
    }

    /**
     * GET 요청
     */
    public function get(string $url, array $params = [], array $headers = []): HttpResponse
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * POST 요청
     */
    public function post(string $url, mixed $data = null, array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * PUT 요청
     */
    public function put(string $url, mixed $data = null, array $headers = []): HttpResponse
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * DELETE 요청
     */
    public function delete(string $url, array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * PATCH 요청
     */
    public function patch(string $url, mixed $data = null, array $headers = []): HttpResponse
    {
        return $this->request('PATCH', $url, $data, $headers);
    }

    /**
     * Form 데이터 POST 요청
     */
    public function postForm(string $url, array $data, array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        
        return $this->request('POST', $url, http_build_query($data), $headers);
    }

    /**
     * JSON POST 요청
     */
    public function postJson(string $url, array $data, array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/json';
        
        return $this->request('POST', $url, json_encode($data), $headers);
    }

    /**
     * HTTP 요청 실행
     */
    private function request(string $method, string $url, mixed $data = null, array $headers = []): HttpResponse
    {
        $ch = curl_init();
        
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }
        
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $headerLines = [];
        
        foreach ($allHeaders as $key => $value) {
            $headerLines[] = "{$key}: {$value}";
        }
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];
        
        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = $data;
                }
                break;
                
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = $data;
                }
                break;
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error ({$errno}): {$error}");
        }
        
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        curl_close($ch);
        
        $responseHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return new HttpResponse($statusCode, $this->parseHeaders($responseHeaders), $body);
    }

    /**
     * 응답 헤더 파싱
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
}

/**
 * HTTP 응답 클래스
 */
final class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * 상태 코드 반환
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 헤더 반환
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 특정 헤더 반환
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * 바디 반환
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * JSON으로 디코딩
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);
        
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * 성공 응답인지 확인
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * 클라이언트 에러인지 확인
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * 서버 에러인지 확인
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
