<?php

namespace Core;

/**
 * High-performance API Request handler (`Performance First`).
 * 
 * Provides static, zero-allocation (`O(1)`) access to HTTP request methods,
 * JSON payloads, headers, query parameters, and Bearer authentication tokens
 * without instantiating heavy request objects per HTTP request.
 * 
 * @package Core
 */
class Request
{
    private static ?array $jsonPayload = null;
    private static ?string $activeRouteCacheKey = null;
    private static int $activeRouteCacheTtl = 0;
    private static string $activeRouteCacheDriver = 'apcu';


    /**
     * Returns the current HTTP request method in uppercase (`GET`, `POST`, `PUT`, `DELETE`, `PATCH`, `OPTIONS`).
     * 
     * @return string
     * @example if (\Core\Request::getMethod() === 'POST') { ... }
     */
    public static function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Asserts that the current request method matches one of the allowed methods.
     * If it does not match, sends a 405 Method Not Allowed response and terminates execution.
     * 
     * @param string ...$allowedMethods Allowed HTTP methods (e.g., 'POST', 'PUT')
     * @return void
     * @example \Core\Request::assertMethod('POST', 'PUT');
     */
    public static function assertMethod(string ...$allowedMethods): void
    {
        $currentMethod = self::getMethod();
        $allowed = array_map('strtoupper', $allowedMethods);

        if (!in_array($currentMethod, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            Response::error("Method `{$currentMethod}` not allowed on this endpoint. Allowed: " . implode(', ', $allowed), 405);
        }
    }

    /**
     * Checks if the request is GET.
     */
    public static function isGet(): bool
    {
        return self::getMethod() === 'GET';
    }

    /**
     * Checks if the request is POST.
     */
    public static function isPost(): bool
    {
        return self::getMethod() === 'POST';
    }

    /**
     * Checks if the request is PUT.
     */
    public static function isPut(): bool
    {
        return self::getMethod() === 'PUT';
    }

    /**
     * Checks if the request is DELETE.
     */
    public static function isDelete(): bool
    {
        return self::getMethod() === 'DELETE';
    }

    /**
     * Checks if the request is PATCH.
     */
    public static function isPatch(): bool
    {
        return self::getMethod() === 'PATCH';
    }

    /**
     * Returns the parsed JSON payload from the request body (`php://input`).
     * Memoizes the result in RAM so repeated calls are instantaneous (`O(1)`).
     * 
     * @return array<string, mixed>
     * @example $body = \Core\Request::json();
     */
    public static function json(): array
    {
        if (self::$jsonPayload !== null) {
            return self::$jsonPayload;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            self::$jsonPayload = [];
            return self::$jsonPayload;
        }

        $decoded = json_decode($raw, true);
        self::$jsonPayload = is_array($decoded) ? $decoded : [];
        return self::$jsonPayload;
    }

    /**
     * Retrieves an input parameter from `$_REQUEST` or JSON payload.
     * 
     * @param string $key Parameter name
     * @param mixed $default Default value if parameter is missing
     * @return mixed
     * @example $page = \Core\Request::input('page', 1);
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        if (isset($_REQUEST[$key])) {
            return $_REQUEST[$key];
        }

        $json = self::json();
        return $json[$key] ?? $default;
    }

    /**
     * Returns all input parameters merged from `$_REQUEST` and JSON payload.
     * 
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_merge($_REQUEST, self::json());
    }

    /**
     * Returns all HTTP request headers as an associative array.
     * 
     * @return array<string, string>
     */
    public static function headers(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return $headers ?: [];
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retrieves a specific HTTP request header (case-insensitive).
     * 
     * @param string $key Header name (e.g., 'Authorization' or 'X-API-Key')
     * @param string|null $default Default value if missing
     * @return string|null
     */
    public static function header(string $key, ?string $default = null): ?string
    {
        $headers = self::headers();
        foreach ($headers as $headerName => $value) {
            if (strcasecmp($headerName, $key) === 0) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Extracts the Bearer token from the `Authorization` header.
     * 
     * @return string|null
     * @example $token = \Core\Request::bearerToken();
     */
    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization');
        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }
        return null;
    }

    /**
     * Retrieves the client IP address (`X-Forwarded-For` support behind Nginx proxy).
     * 
     * @return string
     */
    public static function ip(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    /**
     * Executes route middlewares and handler callback if HTTP method matches.
     * 
     * Accepts flexible arguments:
     * - `Request::route('GET', $callback)`
     * - `Request::route('GET', [$mw1, $mw2], $callback)`
     * - `Request::route('GET', $mw1, $mw2, $callback)`
     * - `Request::route('GET', [$mw1, $mw2, $callback])`
     * Checks if route caching is active for the current request execution.
     */
    public static function hasActiveRouteCache(): bool
    {
        return self::$activeRouteCacheKey !== null;
    }

    /**
     * Retrieves the active route cache configuration.
     *
     * @return array{0: string, 1: int, 2: string} [Key, TTL, Driver]
     */
    public static function getActiveRouteCache(): array
    {
        return [
            self::$activeRouteCacheKey ?? '',
            self::$activeRouteCacheTtl,
            self::$activeRouteCacheDriver
        ];
    }

    /**
     * Clears the active route cache context.
     */
    public static function clearActiveRouteCache(): void
    {
        self::$activeRouteCacheKey = null;
        self::$activeRouteCacheTtl = 0;
        self::$activeRouteCacheDriver = 'apcu';
    }

    /**
     * Executes route middlewares and handler callback if HTTP method matches.
     * 
     * Accepts flexible arguments:
     * - `Request::route('GET', $callback)`
     * - `Request::route('GET', ['cache' => true, 'cache_ttl' => 0], $callback)`
     * - `Request::route('GET', ['cache' => true, 'cache_ttl' => 60], [$mw1, $mw2], $callback)`
     * - `Request::route('GET', $mw1, $mw2, $callback)`
     * 
     * @param string $method Target HTTP method ('GET', 'POST', 'PUT', 'DELETE', etc.)
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function route(string $method, mixed ...$args): void
    {
        if (strtoupper($method) !== self::getMethod()) {
            return;
        }

        [$options, $middlewares, $callback] = self::parseRouteArgs($args);

        foreach ($middlewares as $mw) {
            $ok = self::executeMiddleware($mw);
            if ($ok === false) {
                return;
            }
        }

        $isCacheEnabled = !empty($options['cache']);
        $cacheKey = null;
        $cacheTtl = (int)($options['cache_ttl'] ?? $options['ttl'] ?? 0);
        $driver = $options['driver'] ?? 'apcu';

        if ($isCacheEnabled) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $keyBase = 'route_cache_' . strtoupper($method) . '_' . md5($uri);
            if (!empty($options['by_session']) || !empty($options['vary_session'])) {
                $token = self::bearerToken() ?: ($_COOKIE[session_name()] ?? 'anon');
                $keyBase .= '_' . md5($token);
            }
            $cacheKey = $keyBase;

            $cached = Cache::get($cacheKey, null, $driver);
            if ($cached !== null) {
                header('X-Lila-Cache: HIT');
                if (is_array($cached) && isset($cached['payload'])) {
                    if (isset($cached['headers']) && is_array($cached['headers'])) {
                        foreach ($cached['headers'] as $hName => $hVal) {
                            header("{$hName}: {$hVal}");
                        }
                    }
                    Response::json($cached['payload'], $cached['status'] ?? 200);
                } elseif (is_array($cached) || is_object($cached)) {
                    Response::json($cached);
                } elseif (is_string($cached)) {
                    echo $cached;
                    exit;
                }
            }

            self::$activeRouteCacheKey = $cacheKey;
            self::$activeRouteCacheTtl = $cacheTtl;
            self::$activeRouteCacheDriver = $driver;
        }

        $result = call_user_func($callback);

        if ($isCacheEnabled && $cacheKey !== null && $result !== null) {
            Cache::set($cacheKey, ['payload' => $result, 'status' => 200], $cacheTtl, $driver);
            self::clearActiveRouteCache();
        }

        if (is_array($result) || is_object($result)) {
            Response::json($result);
        } elseif (is_string($result)) {
            echo $result;
            exit;
        }

        exit;
    }

    /**
     * Executes route if current HTTP method is GET.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     * @example Request::GET(function() { Response::json(['ok' => true]); });
     * @example Request::GET(['cache' => true, 'cache_ttl' => 0], function() { Response::json(['ok' => true]); });
     * @example Request::GET([AuthMiddleware::class], function() { Response::json(['user' => 'John']); });
     */
    public static function GET(mixed ...$args): void
    {
        self::route('GET', ...$args);
    }

    /**
     * Executes route if current HTTP method is POST.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function POST(mixed ...$args): void
    {
        self::route('POST', ...$args);
    }

    /**
     * Executes route if current HTTP method is PUT.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function PUT(mixed ...$args): void
    {
        self::route('PUT', ...$args);
    }

    /**
     * Executes route if current HTTP method is DELETE.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function DELETE(mixed ...$args): void
    {
        self::route('DELETE', ...$args);
    }

    /**
     * Executes route if current HTTP method is PATCH.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function PATCH(mixed ...$args): void
    {
        self::route('PATCH', ...$args);
    }

    /**
     * Executes route if current HTTP method is OPTIONS.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function OPTIONS(mixed ...$args): void
    {
        self::route('OPTIONS', ...$args);
    }

    /**
     * Executes route if current HTTP method is HEAD.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function HEAD(mixed ...$args): void
    {
        self::route('HEAD', ...$args);
    }

    /**
     * Executes route if current HTTP method matches any of the specified methods.
     * 
     * @param array $methods List of allowed methods (e.g. ['GET', 'POST'])
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function match(array $methods, mixed ...$args): void
    {
        $current = self::getMethod();
        $upper = array_map('strtoupper', $methods);
        if (in_array($current, $upper, true)) {
            self::route($current, ...$args);
        }
    }

    /**
     * Executes route regardless of current HTTP method.
     * 
     * @param mixed ...$args Options, Middlewares, and callback handler
     * @return void
     */
    public static function any(mixed ...$args): void
    {
        self::route(self::getMethod(), ...$args);
    }

    /**
     * Dynamic static call handler to support custom HTTP methods or case-insensitive calls.
     * 
     * @param string $name Method name (e.g. 'GET', 'post', 'PURGE')
     * @param array $arguments Method arguments
     * @return void
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        self::route($name, ...$arguments);
    }

    /**
     * Helper to parse options array, middleware list, and callback from method arguments.
     *
     * @param array $args
     * @return array{0: array, 1: array, 2: callable}
     */
    private static function parseRouteArgs(array $args): array
    {
        if (empty($args)) {
            throw new \InvalidArgumentException("Route definition requires at least a callable handler.");
        }

        $options = [
            'cache' => false,
            'cache_ttl' => 0,
            'by_session' => false,
            'driver' => 'apcu'
        ];
        $middlewares = [];

        // Check single array wrapper format e.g. Request::GET([$options, $mw, $callback])
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $last = end($args);
        if (!is_callable($last)) {
            throw new \InvalidArgumentException("The last argument in a route definition must be a valid callable callback.");
        }

        $callback = array_pop($args);

        foreach ($args as $arg) {
            if (is_array($arg)) {
                // Determine if this array is a route options array or an array of middlewares
                $isOptionsArray = isset($arg['cache']) || isset($arg['cache_ttl']) || isset($arg['ttl']) || isset($arg['by_session']) || isset($arg['driver']);
                if (!$isOptionsArray) {
                    // Check if non-sequential or string keys exist
                    $keys = array_keys($arg);
                    $isOptionsArray = array_keys($keys) !== $keys;
                }

                if ($isOptionsArray) {
                    if (isset($arg['cache_ttl']) || isset($arg['ttl'])) {
                        $arg['cache'] = $arg['cache'] ?? true;
                    }
                    $options = array_merge($options, $arg);
                } else {
                    foreach ($arg as $subMw) {
                        $middlewares[] = $subMw;
                    }
                }
            } else {
                $middlewares[] = $arg;
            }
        }

        return [$options, $middlewares, $callback];
    }

    /**
     * Executes a single middleware component.
     *
     * Supported formats:
     * - Callable (Closure, anonymous function, array `[Class, 'method']`)
     * - Class name string with static/instance `handle()`, `process()`, or `__invoke()`
     * - Object instance with `handle()`, `process()`, or `__invoke()`
     *
     * @param mixed $middleware
     * @return bool Returns false if middleware returned false, true otherwise
     */
    public static function executeMiddleware(mixed $middleware): bool
    {
        if (is_callable($middleware)) {
            $res = call_user_func($middleware);
            return $res !== false;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            $ref = new \ReflectionClass($middleware);
            if ($ref->hasMethod('handle') && $ref->getMethod('handle')->isStatic()) {
                $res = $middleware::handle();
                return $res !== false;
            }

            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                $res = $instance->handle();
                return $res !== false;
            }
            if (method_exists($instance, 'process')) {
                $res = $instance->process();
                return $res !== false;
            }
            if (is_callable($instance)) {
                $res = $instance();
                return $res !== false;
            }
        }

        if (is_object($middleware)) {
            if (method_exists($middleware, 'handle')) {
                $res = $middleware->handle();
                return $res !== false;
            }
            if (method_exists($middleware, 'process')) {
                $res = $middleware->process();
                return $res !== false;
            }
            if (is_callable($middleware)) {
                $res = $middleware();
                return $res !== false;
            }
        }

        return true;
    }
}
