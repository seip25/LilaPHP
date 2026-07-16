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
}
