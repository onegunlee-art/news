<?php
/**
 * HTTP 응답 처리 클래스
 * 
 * HTTP 응답의 헤더, 상태 코드, 바디를 관리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Response 클래스
 * 
 * HTTP 응답을 빌드하고 전송합니다.
 */
final class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $body = null;
    private bool $sent = false;

    /**
     * HTTP 상태 코드 메시지
     */
    private const STATUS_MESSAGES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * 상태 코드 설정
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        
        return $this;
    }

    /**
     * 상태 코드 반환
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 헤더 설정
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        
        return $this;
    }

    /**
     * 여러 헤더 설정
     */
    public function setHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        
        return $this;
    }

    /**
     * 헤더 반환
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * 모든 헤더 반환
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Content-Type 설정
     */
    public function setContentType(string $type, string $charset = 'UTF-8'): self
    {
        return $this->setHeader('Content-Type', "{$type}; charset={$charset}");
    }

    /**
     * 바디 설정
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        
        return $this;
    }

    /**
     * 바디 반환
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * JSON 응답 생성
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $response = new self();
        
        return $response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setHeaders($headers)
            ->setBody($data);
    }

    /**
     * 성공 응답 생성
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
        ], $statusCode);
    }

    /**
     * 에러 응답 생성
     */
    public static function error(string $message, int $statusCode = 400, ?array $errors = null): self
    {
        $body = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c'),
        ];
        
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        
        return self::json($body, $statusCode);
    }

    /**
     * 404 Not Found 응답
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }

    /**
     * 401 Unauthorized 응답
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, 401);
    }

    /**
     * 403 Forbidden 응답
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, 403);
    }

    /**
     * 422 Validation Error 응답
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return self::error($message, 422, $errors);
    }

    /**
     * 500 Internal Server Error 응답
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, 500);
    }

    /**
     * 201 Created 응답
     */
    public static function created(mixed $data = null, string $message = 'Created successfully'): self
    {
        return self::success($data, $message, 201);
    }

    /**
     * 204 No Content 응답
     */
    public static function noContent(): self
    {
        $response = new self();
        
        return $response->setStatusCode(204);
    }

    /**
     * 리다이렉트 응답
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new self();
        
        return $response
            ->setStatusCode($statusCode)
            ->setHeader('Location', $url);
    }

    /**
     * 페이지네이션 응답
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): self
    {
        $totalPages = (int) ceil($total / $perPage);
        
        return self::json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
            'timestamp' => date('c'),
        ]);
    }

    /**
     * 응답 전송
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        
        $this->sendHeaders();
        $this->sendBody();
        
        $this->sent = true;
    }

    /**
     * 헤더 전송
     */
    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        
        // HTTP 상태 라인
        $statusMessage = self::STATUS_MESSAGES[$this->statusCode] ?? 'Unknown';
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        header("{$protocol} {$this->statusCode} {$statusMessage}");
        
        // 응답 헤더
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * 바디 전송
     */
    private function sendBody(): void
    {
        if ($this->body === null) {
            return;
        }
        
        // Content-Type이 JSON이면 인코딩
        $contentType = $this->getHeader('Content-Type') ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            echo json_encode(
                $this->body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
        } elseif (is_string($this->body)) {
            echo $this->body;
        } else {
            echo (string) $this->body;
        }
    }

    /**
     * 캐시 헤더 설정
     */
    public function cache(int $seconds): self
    {
        return $this
            ->setHeader('Cache-Control', "public, max-age={$seconds}")
            ->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }

    /**
     * 캐시 비활성화
     */
    public function noCache(): self
    {
        return $this
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    /**
     * CORS 헤더 설정
     */
    public function withCors(
        string $origin = '*',
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): self {
        return $this
            ->setHeader('Access-Control-Allow-Origin', $origin)
            ->setHeader('Access-Control-Allow-Methods', implode(', ', $methods))
            ->setHeader('Access-Control-Allow-Headers', implode(', ', $headers))
            ->setHeader('Access-Control-Max-Age', '86400');
    }
}
