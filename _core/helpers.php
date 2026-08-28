<?php

declare(strict_types=1);

/**
 * LilaPHP Global Helper Functions (`KISS & Performance First`).
 * 
 * Clean, zero-overhead helper utilities for micro-API development.
 */

if (!function_exists('json_response')) {
    /**
     * Send a standardized JSON response with proper HTTP status and headers.
     * 
     * @param mixed $data Payload to serialize to JSON
     * @param int $status HTTP status code (default: 200)
     * @param array<string, string> $headers Additional HTTP headers
     * @return never
     */
    function json_response(mixed $data, int $status = 200, array $headers = []): never
    {
        \Core\Response::json($data, $status, $headers);
        exit;
    }
}

if (!function_exists('sanitize')) {
    /**
     * Recursively sanitize input data by stripping null bytes, trimming strings,
     * and ensuring valid UTF-8 encoding.
     * 
     * @param mixed $input Input string, array, or object
     * @return mixed Sanitized output
     */
    function sanitize(mixed $input): mixed
    {
        if (is_string($input)) {
            $cleaned = str_replace(chr(0), '', $input);
            return trim($cleaned);
        }

        if (is_array($input)) {
            $sanitized = [];
            foreach ($input as $key => $value) {
                $sanitizedKey = is_string($key) ? trim(str_replace(chr(0), '', $key)) : $key;
                $sanitized[$sanitizedKey] = sanitize($value);
            }
            return $sanitized;
        }

        return $input;
    }
}

if (!function_exists('input')) {
    /**
     * Retrieve a sanitized input value from JSON payload, $_POST, or $_GET.
     * 
     * @param string|null $key Parameter key. If null, returns all combined inputs.
     * @param mixed $default Fallback value if key is not found
     * @return mixed
     */
    function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return sanitize(\Core\Request::all());
        }
        $val = \Core\Request::input($key, $default);
        return sanitize($val);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get or generate the active CSRF token for the current session.
     */
    function csrf_token(): string
    {
        return \Core\Security::getCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate an HTML hidden input tag containing the active CSRF token.
     */
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * Verify the CSRF token submitted via POST body or `X-CSRF-TOKEN` header.
     */
    function verify_csrf(?string $token = null): bool
    {
        return \Core\Security::validateCsrfToken($token);
    }
}

if (!function_exists('env')) {
    /**
     * Get the value of an environment variable with a fallback default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

if (!function_exists('abort')) {
    /**
     * Abort request execution with a JSON error response.
     * 
     * @param int $code HTTP error status code (400, 401, 403, 404, 500, etc.)
     * @param string $message Error message
     * @return never
     */
    function abort(int $code = 404, string $message = ''): never
    {
        $msg = $message !== '' ? $message : match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            default => 'Internal Server Error'
        };
        json_response(['error' => true, 'code' => $code, 'message' => $msg], $code);
    }
}
