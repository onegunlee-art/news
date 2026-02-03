<?php
/**
 * HTTP 요청 처리 클래스
 * 
 * HTTP 요청의 파라미터, 헤더, 바디 등을 관리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Request 클래스
 * 
 * HTTP 요청 정보를 캡슐화하고 접근을 제공합니다.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $post;
    private array $server;
    private array $headers;
    private array $cookies;
    private array $files;
    private ?array $json = null;
    private ?string $rawBody = null;
    private array $routeParams = [];

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();
    }

    /**
     * 현재 요청에서 새 인스턴스 생성 (팩토리 메서드)
     */
    public static function capture(): self
    {
        return new self();
    }

    /**
     * HTTP 메서드 반환
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 요청 URI 반환
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 요청 경로 반환 (쿼리 스트링 제외)
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 특정 HTTP 메서드인지 확인
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * GET 요청인지 확인
     */
    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    /**
     * POST 요청인지 확인
     */
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * PUT 요청인지 확인
     */
    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    /**
     * DELETE 요청인지 확인
     */
    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    /**
     * AJAX 요청인지 확인
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * JSON 요청인지 확인
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        
        return str_contains($contentType, 'application/json');
    }

    /**
     * 쿼리 파라미터 반환
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }

    /**
     * POST 파라미터 반환
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        
        return $this->post[$key] ?? $default;
    }

    /**
     * 입력값 반환 (GET + POST + JSON)
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->query, $this->post, $this->json() ?? []);
        
        if ($key === null) {
            return $all;
        }
        
        return $all[$key] ?? $default;
    }

    /**
     * JSON 바디 파싱 및 반환
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $body = $this->getRawBody();
            $this->json = json_decode($body, true) ?? [];
        }
        
        if ($key === null) {
            return $this->json;
        }
        
        return $this->json[$key] ?? $default;
    }

    /**
     * Raw 바디 반환
     */
    public function getRawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        
        return $this->rawBody;
    }

    /**
     * 헤더 반환
     * 
     * @param string|null $key 헤더명 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function getHeader(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->headers;
        }
        
        // 헤더명 정규화 (Case-Insensitive)
        $normalizedKey = strtolower($key);
        
        foreach ($this->headers as $headerKey => $value) {
            if (strtolower($headerKey) === $normalizedKey) {
                return $value;
            }
        }
        
        return $default;
    }

    /**
     * 쿠키 반환
     * 
     * @param string|null $key 쿠키명 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }
        
        return $this->cookies[$key] ?? $default;
    }

    /**
     * 업로드된 파일 반환
     * 
     * @param string|null $key 파일 필드명 (null이면 전체 반환)
     * @return array|null
     */
    public function file(?string $key = null): ?array
    {
        if ($key === null) {
            return $this->files;
        }
        
        return $this->files[$key] ?? null;
    }

    /**
     * 서버 변수 반환
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        
        return $this->server[$key] ?? $default;
    }

    /**
     * Bearer 토큰 추출
     * Apache/CGI에서 Authorization이 제거되는 경우 REDIRECT_HTTP_AUTHORIZATION, X-Authorization 폴백
     */
    public function bearerToken(): ?string
    {
        $header = $this->getHeader('Authorization');
        if (!$header && isset($this->server['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $this->server['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (!$header) {
            $header = $this->getHeader('X-Authorization');
        }
        
        if ($header && preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    /**
     * 클라이언트 IP 주소 반환
     */
    public function getClientIp(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        
        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $ip = $this->server[$key];
                
                // 쉼표로 구분된 경우 첫 번째 IP 사용
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * User Agent 반환
     */
    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Referer 반환
     */
    public function getReferer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    /**
     * HTTPS 요청인지 확인
     */
    public function isSecure(): bool
    {
        return (
            (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ||
            ($this->server['SERVER_PORT'] ?? 80) == 443 ||
            ($this->getHeader('X-Forwarded-Proto') === 'https')
        );
    }

    /**
     * 라우트 파라미터 설정
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * 라우트 파라미터 반환
     * 
     * @param string|null $key 키 (null이면 전체 반환)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function param(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->routeParams;
        }
        
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * 입력값 검증
     * 
     * @param array $rules 검증 규칙 [필드 => 규칙]
     * @return array 검증 에러 메시지
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $data = $this->input();
        
        foreach ($rules as $field => $rule) {
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;
            $value = $data[$field] ?? null;
            
            foreach ($ruleList as $singleRule) {
                $error = $this->validateRule($field, $value, $singleRule);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        return $errors;
    }

    /**
     * 단일 규칙 검증
     */
    private function validateRule(string $field, mixed $value, string $rule): ?string
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;
        
        return match ($ruleName) {
            'required' => empty($value) && $value !== '0' ? "{$field}은(는) 필수입니다." : null,
            'email' => $value && !filter_var($value, FILTER_VALIDATE_EMAIL) ? "{$field}은(는) 유효한 이메일이 아닙니다." : null,
            'min' => $value && strlen((string) $value) < (int) $ruleParam ? "{$field}은(는) 최소 {$ruleParam}자 이상이어야 합니다." : null,
            'max' => $value && strlen((string) $value) > (int) $ruleParam ? "{$field}은(는) 최대 {$ruleParam}자까지 가능합니다." : null,
            'numeric' => $value && !is_numeric($value) ? "{$field}은(는) 숫자여야 합니다." : null,
            'url' => $value && !filter_var($value, FILTER_VALIDATE_URL) ? "{$field}은(는) 유효한 URL이 아닙니다." : null,
            default => null,
        };
    }

    /**
     * 헤더 파싱
     */
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                // HTTP_CONTENT_TYPE -> Content-Type
                $headerName = str_replace('_', '-', substr($key, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = str_replace('_', '-', $key);
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
}
