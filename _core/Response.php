<?php

declare(strict_types=1);

namespace Core;

/**
 * High-performance API Response handler.
 * 
 * Specialized for JSON serialization, CORS headers, HTTP status codes,
 * and binary/stream responses without template or UI engine overhead.
 * 
 * @package Core
 */
class Response
{
    /**
     * Sends a JSON response with proper CORS and content headers and terminates execution.
     * 
     * @param array|object $data Data payload to encode as JSON
     * @param int $status HTTP status code (default: 200)
     * @param array<string, string> $headers Additional custom HTTP headers
     * @return void
     * @example \Core\Response::json(['status' => 'success', 'data' => $user], 200);
     */
    public static function json(array|object $data, int $status = 200, array $headers = []): void
    {
        if (Request::hasActiveRouteCache()) {
            [$key, $ttl, $driver] = Request::getActiveRouteCache();
            Cache::set($key, ['payload' => $data, 'status' => $status, 'headers' => $headers], $ttl, $driver);
            Request::clearActiveRouteCache();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            self::applyCorsHeaders();

            foreach ($headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (Config::$DEBUG) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);
        if ($json === false) {
            http_response_code(500);
            echo json_encode(['error' => 'JSON encoding failure: ' . json_last_error_msg(), 'code' => 500]);
            exit;
        }

        echo $json;
        exit;
    }

    /**
     * Sends a standardized JSON error response.
     * 
     * @param string $message Error description
     * @param int $status HTTP error code (default: 400)
     * @param array|null $errors Detailed validation errors or debugging trace
     * @return void
     * @example \Core\Response::error('User not found', 404);
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): void
    {
        $payload = [
            'status' => 'error',
            'code' => $status,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $status);
    }

    /**
     * Applies CORS (Cross-Origin Resource Sharing) headers based on environment configuration.
     * 
     * @return void
     * @example \Core\Response::applyCorsHeaders();
     */
    public static function applyCorsHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed = Config::$CORS_ALLOWED_ORIGINS;

        if ($allowed === '*' || $allowed === '') {
            header('Access-Control-Allow-Origin: *');
        } elseif (str_contains($allowed, ',')) {
            $origins = array_map('trim', explode(',', $allowed));
            if (in_array($origin, $origins, true)) {
                header("Access-Control-Allow-Origin: {$origin}");
            }
        } elseif ($allowed === $origin) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Applies HTTP headers to explicitly disable caching for private/authenticated responses.
     * 
     * @return void
     * @example \Core\Response::setPrivateCache();
     */
    public static function setPrivateCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Handles HTTP OPTIONS preflight request.
     * 
     * @return void
     * @example \Core\Response::handlePreflight();
     */
    public static function handlePreflight(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            self::applyCorsHeaders();
            http_response_code(204);
            exit;
        }
    }

    /**
     * Streams binary content or large data directly without buffering.
     * 
     * @param callable $callback Generator or stream writer
     * @param int $status HTTP status code (default: 200)
     * @param string $contentType MIME content type
     * @return void
     * @example \Core\Response::stream(fn() => echo "chunk", 200, 'text/event-stream');
     */
    public static function stream(callable $callback, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): void
    {
        http_response_code($status);
        header("Content-Type: {$contentType}");
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        self::applyCorsHeaders();

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        $callback();
        flush();
        exit;
    }

    /**
     * Sends a file directly to the client.
     * 
     * @param string $filePath Absolute path to the file on disk
     * @param string|null $downloadName Optional filename for download prompt
     * @param bool $inline Whether to display inline or as attachment
     * @param int $status HTTP status code
     * @return void
     * @example \Core\Response::file('/path/to/report.pdf', 'report.pdf', false);
     */
    public static function file(string $filePath, ?string $downloadName = null, bool $inline = false, int $status = 200): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            self::error('File not found or unreadable', 404);
        }

        http_response_code($status);
        self::applyCorsHeaders();

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        header("Content-Type: {$mimeType}");

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $downloadName ?? basename($filePath);
        header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($filePath));

        $handle = fopen($filePath, 'rb');
        if ($handle !== false) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
        exit;
    }

    /**
     * Redirects to a specified URL.
     * 
     * @param string $url Destination URL or path
     * @param int $status HTTP redirect status code (301 or 302)
     * @return void
     * @example \Core\Response::redirect('/api/v1/health', 302);
     */
    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * Proxy for `\Core\Request::assertMethod()`.
     * 
     * @param string ...$allowedMethods
     */
    public static function assertMethod(string ...$allowedMethods): void
    {
        Request::assertMethod(...$allowedMethods);
    }

    /**
     * Proxy for `\Core\Request::getMethod()`.
     * 
     * @return string
     */
    public static function getMethod(): string
    {
        return Request::getMethod();
    }
}
