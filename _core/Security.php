<?php

declare(strict_types=1);

namespace Core;

/**
 * High-performance API Security & Sanitization suite.
 * 
 * Provides rate limiting backed by RAM/Cache, payload inspection,
 * recursive input sanitization, CSRF verification, secure sessions,
 * and standard defensive HTTP security headers.
 * 
 * @package Core
 */
class Security
{
    /**
     * Applies general defensive HTTP security headers.
     * 
     * @return void
     */
    public static function applyGeneralSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 0');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');

        if (!Config::$DEBUG && (($_SERVER['HTTPS'] ?? 'off') !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Initializes a secure PHP session if not already active.
     */
    public static function ensureSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure'   => $isHttps,
                'use_strict_mode' => true,
            ]);
        }
    }

    /**
     * Returns or generates the active CSRF token for the session.
     */
    public static function getCsrfToken(): string
    {
        self::ensureSecureSession();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Validates a CSRF token from header, payload, or argument against the active session.
     */
    public static function validateCsrfToken(?string $token = null): bool
    {
        self::ensureSecureSession();
        $expected = $_SESSION['_csrf_token'] ?? '';
        if ($expected === '') {
            return false;
        }

        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_XSRF_TOKEN'] ?? ($_POST['_csrf'] ?? ($_POST['_csrf_token'] ?? ($_POST['csrf'] ?? ''))));
        }

        return hash_equals($expected, (string)$token);
    }

    /**
     * Recursively trims strings and strips null bytes from input arrays.
     * 
     * @param array &$data Target array to sanitize
     * @return void
     */
    public static function sanitize(array &$data): void
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = trim(str_replace(chr(0), '', $value));
            }
        });
    }

    /**
     * Inspects request data recursively for dangerous scripts or inline event listeners.
     * 
     * @param array $data Input dictionary
     * @return bool True if clean, false if malicious pattern found
     */
    public static function checkPayload(array $data): bool
    {
        $dangerous = false;

        array_walk_recursive($data, function ($value) use (&$dangerous) {
            if ($dangerous || !is_string($value)) {
                return;
            }
            if (preg_match('/<script\b[^>]*>|\bonerror\s*=\s*|\bonload\s*=\s*|javascript:/i', $value)) {
                $dangerous = true;
            }
        });

        if ($dangerous) {
            Logger::warning('Malicious payload pattern blocked', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        }

        return !$dangerous;
    }

    /**
     * Enforces API Rate Limiting using APCu RAM (or Redis / fallback).
     * 
     * @param int $maxRequests Maximum allowed requests per window
     * @param int $windowSeconds Duration of the limit window in seconds
     * @return bool True if request allowed, false if rate limit exceeded
     */
    public static function rateLimit(int $maxRequests = 200, int $windowSeconds = 60): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            return true;
        }

        $ip = Request::ip();
        $key = "rate_limit:{$ip}";

        $rateData = Cache::api($key, fn() => [
            'count' => 0,
            'start' => time()
        ], $windowSeconds);

        if (!is_array($rateData) || !isset($rateData['start'])) {
            $rateData = ['count' => 0, 'start' => time()];
        }

        if (time() - $rateData['start'] >= $windowSeconds) {
            $rateData = ['count' => 0, 'start' => time()];
        }

        $rateData['count']++;
        Cache::set($key, $rateData, $windowSeconds, 'apcu');

        $remaining = max(0, $maxRequests - $rateData['count']);
        $reset = max(0, $windowSeconds - (time() - $rateData['start']));

        header("X-RateLimit-Limit: {$maxRequests}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$reset}");

        if ($rateData['count'] > $maxRequests) {
            header("Retry-After: {$reset}");
            return false;
        }

        return true;
    }

    /**
     * Validates API Key header (`X-API-Key`) against APP_KEY or custom token.
     */
    public static function verifyApiKey(?string $expectedKey = null): bool
    {
        $expected = $expectedKey ?? Config::$APP_KEY;
        if ($expected === '') {
            return true;
        }

        $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
        return hash_equals($expected, (string) $provided);
    }

    /**
     * Encrypts plaintext data using OpenSSL AES-256-GCM and APP_KEY.
     */
    public static function encrypt(string $plaintext): string
    {
        $key = hash('sha256', Config::$APP_KEY, true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts ciphertext produced by `encrypt()`.
     */
    public static function decrypt(string $encoded): string|false
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            return false;
        }

        $key = hash('sha256', Config::$APP_KEY, true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
}
