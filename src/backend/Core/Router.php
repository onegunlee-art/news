<?php
/**
 * 라우터 클래스
 * 
 * RESTful API 라우팅을 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Router 클래스
 * 
 * HTTP 요청을 적절한 컨트롤러/핸들러로 라우팅합니다.
 */
final class Router
{
    /** @var array<string, array> 등록된 라우트 */
    private array $routes = [];
    
    /** @var array<Closure> 미들웨어 스택 */
    private array $middleware = [];
    
    /** @var string 현재 그룹 prefix */
    private string $groupPrefix = '';
    
    /** @var array 현재 그룹 미들웨어 */
    private array $groupMiddleware = [];
    
    /** @var array<string, string> 라우트 별칭 */
    private array $namedRoutes = [];

    /**
     * GET 라우트 등록
     */
    public function get(string $path, mixed $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * POST 라우트 등록
     */
    public function post(string $path, mixed $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * PUT 라우트 등록
     */
    public function put(string $path, mixed $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * DELETE 라우트 등록
     */
    public function delete(string $path, mixed $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * PATCH 라우트 등록
     */
    public function patch(string $path, mixed $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * OPTIONS 라우트 등록
     */
    public function options(string $path, mixed $handler): self
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    /**
     * 모든 HTTP 메서드에 대한 라우트 등록
     */
    public function any(string $path, mixed $handler): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->addRoute($method, $path, $handler);
        }
        
        return $this;
    }

    /**
     * 라우트 등록
     * 
     * @param string $method HTTP 메서드
     * @param string $path 경로 패턴
     * @param mixed $handler 핸들러 (클로저 또는 [Controller::class, 'method'])
     */
    public function addRoute(string $method, string $path, mixed $handler): self
    {
        $fullPath = $this->groupPrefix . $path;
        $fullPath = '/' . trim($fullPath, '/');
        
        $this->routes[$method][$fullPath] = [
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
        ];
        
        return $this;
    }

    /**
     * 라우트 이름 지정
     */
    public function name(string $name): self
    {
        // 마지막으로 등록된 라우트에 이름 지정
        foreach ($this->routes as $method => $routes) {
            $paths = array_keys($routes);
            if (!empty($paths)) {
                $lastPath = end($paths);
                $this->namedRoutes[$name] = $lastPath;
                break;
            }
        }
        
        return $this;
    }

    /**
     * 미들웨어 추가
     */
    public function middleware(Closure|array $middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        
        return $this;
    }

    /**
     * 라우트 그룹 생성
     * 
     * @param array $attributes 그룹 속성 ['prefix' => '', 'middleware' => []]
     * @param Closure $callback 그룹 내 라우트 정의 콜백
     */
    public function group(array $attributes, Closure $callback): self
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // 그룹 속성 적용
        if (isset($attributes['prefix'])) {
            $this->groupPrefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }
        
        if (isset($attributes['middleware'])) {
            $middlewares = is_array($attributes['middleware']) 
                ? $attributes['middleware'] 
                : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($previousMiddleware, $middlewares);
        }
        
        // 콜백 실행
        $callback($this);
        
        // 이전 상태 복원
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        
        return $this;
    }

    /**
     * 요청 디스패치
     * 
     * @param string $method HTTP 메서드
     * @param string $uri 요청 URI
     */
    public function dispatch(string $method, string $uri): void
    {
        $request = Request::capture();
        $path = parse_url($uri, PHP_URL_PATH);
        $path = '/' . trim($path ?? '/', '/');
        
        // API prefix 제거
        if (str_starts_with($path, '/api')) {
            $path = substr($path, 4) ?: '/';
        }
        
        // 라우트 매칭
        $route = $this->matchRoute($method, $path);
        
        if ($route === null) {
            Response::notFound('Route not found')->send();
            return;
        }
        
        // 라우트 파라미터 설정
        $request->setRouteParams($route['params']);
        
        try {
            // 미들웨어 실행
            $response = $this->runMiddleware(
                array_merge($this->middleware, $route['middleware']),
                $request,
                fn() => $this->callHandler($route['handler'], $request)
            );
            
            // Response 객체 처리
            if ($response instanceof Response) {
                $response->send();
            }
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * 라우트 매칭
     */
    private function matchRoute(string $method, string $path): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        foreach ($this->routes[$method] as $routePath => $routeData) {
            $params = $this->matchPath($routePath, $path);
            
            if ($params !== null) {
                return [
                    'handler' => $routeData['handler'],
                    'middleware' => $routeData['middleware'],
                    'params' => $params,
                ];
            }
        }
        
        return null;
    }

    /**
     * 경로 패턴 매칭
     * 
     * @param string $pattern 라우트 패턴 (예: /users/{id})
     * @param string $path 실제 경로 (예: /users/123)
     * @return array|null 파라미터 배열 또는 null (매칭 실패)
     */
    private function matchPath(string $pattern, string $path): ?array
    {
        // 정확한 경로 매칭 먼저 시도 (성능 최적화)
        if ($pattern === $path) {
            return [];
        }
        
        // 파라미터가 있는 경우 정규표현식으로 변환
        if (str_contains($pattern, '{')) {
            $regex = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                fn($matches) => '(?P<' . $matches[1] . '>[^/]+)',
                $pattern
            );
            
            $regex = '#^' . $regex . '$#';
            
            if (preg_match($regex, $path, $matches)) {
                // 숫자 키 제거, 문자열 키만 반환
                return array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
            }
        }
        
        return null;
    }

    /**
     * 미들웨어 실행
     */
    private function runMiddleware(array $middleware, Request $request, Closure $next): mixed
    {
        if (empty($middleware)) {
            return $next();
        }
        
        $mw = array_shift($middleware);
        
        return $mw($request, fn() => $this->runMiddleware($middleware, $request, $next));
    }

    /**
     * 핸들러 호출
     * 
     * @param mixed $handler 핸들러 (클로저 또는 [Controller::class, 'method'])
     * @param Request $request 요청 객체
     * @return mixed
     */
    private function callHandler(mixed $handler, Request $request): mixed
    {
        // 클로저인 경우
        if ($handler instanceof Closure) {
            return $handler($request);
        }
        
        // [Controller::class, 'method'] 형식인 경우
        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;
            
            if (!class_exists($controllerClass)) {
                throw new RuntimeException("Controller not found: {$controllerClass}");
            }
            
            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new RuntimeException("Method not found: {$controllerClass}::{$method}");
            }
            
            return $controller->{$method}($request);
        }
        
        // 'Controller@method' 문자열 형식인 경우
        if (is_string($handler) && str_contains($handler, '@')) {
            [$controllerClass, $method] = explode('@', $handler);
            
            // 네임스페이스 추가
            if (!str_starts_with($controllerClass, 'App\\')) {
                $controllerClass = 'App\\Controllers\\' . $controllerClass;
            }
            
            if (!class_exists($controllerClass)) {
                throw new RuntimeException("Controller not found: {$controllerClass}");
            }
            
            $controller = new $controllerClass();
            
            return $controller->{$method}($request);
        }
        
        throw new RuntimeException('Invalid route handler');
    }

    /**
     * 예외 처리
     */
    private function handleException(\Throwable $e): void
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'timestamp' => date('c'),
        ];
        
        // 디버그 모드에서만 스택 트레이스 포함
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        if ($config['debug'] ?? false) {
            $response['trace'] = $e->getTraceAsString();
        }
        
        Response::json($response, $statusCode)->send();
    }

    /**
     * 라우트 목록 반환
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * 이름으로 라우트 URL 생성
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route not found: {$name}");
        }
        
        $path = $this->namedRoutes[$name];
        
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }
        
        return $path;
    }
}
