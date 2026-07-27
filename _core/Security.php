<?php

namespace Core;

/**
 * High-performance API Security & Sanitization suite.
 * 
 * Provides rate limiting backed by APCu/Redis RAM (`Core\Cache`), payload inspection,
 * recursive input sanitization, CSRF/Token verification, and security headers.
 * 
 * @package Core
 */
class Security
{
    /**
     * Applies general defensive HTTP security headers.
     * 
     * @return void
     * @example \Core\Security::applyGeneralSecurityHeaders();
     */
    public static function applyGeneralSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 0');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Powered-By: LilaPHP');

        if (!Config::$DEBUG && (($_SERVER['HTTPS'] ?? 'off') !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Recursively trims strings and strips null bytes from input arrays.
     * 
     * @param array &$data Target array to sanitize
     * @return void
     * @example \Core\Security::sanitize($_POST);
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
     * @example if (!\Core\Security::checkPayload($_POST)) { ... }
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
     * Enforces API Rate Limiting using APCu RAM (or Redis).
     * 
     * @param int $maxRequests Maximum allowed requests per window
     * @param int $windowSeconds Duration of the limit window in seconds
     * @return bool True if request allowed, false if rate limit exceeded
     * @example if (!\Core\Security::rateLimit(100, 60)) { \Core\Response::error('Too Many Requests', 429); }
     */
    public static function rateLimit(int $maxRequests = 200, int $windowSeconds = 60): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
     * 
     * @param string|null $expectedKey Expected token string (defaults to APP_KEY)
     * @return bool True if valid
     * @example if (!\Core\Security::verifyApiKey()) { \Core\Response::error('Unauthorized', 401); }
     */
    public static function verifyApiKey(?string $expectedKey = null): bool
    {
        $expected = $expectedKey ?? Config::$APP_KEY;
        if ($expected === '') {
            return true;
        }

        $provided = $_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['api_key'] ?? '');
        return hash_equals($expected, (string) $provided);
    }

    /**
     * Encrypts plaintext data using OpenSSL AES-256-GCM and APP_KEY.
     * 
     * @param string $plaintext String to encrypt
     * @return string Base64 encoded IV + Tag + Ciphertext
     * @example $cipher = \Core\Security::encrypt('Secret data');
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
     * 
     * @param string $encoded Base64 encoded IV + Tag + Ciphertext
     * @return string|false Decrypted plaintext or false on verification failure
     * @example $plaintext = \Core\Security::decrypt($cipher);
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
