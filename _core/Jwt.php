<?php

namespace Core;

/**
 * High-Performance Zero-Dependency JSON Web Token (JWT) Helper.
 * 
 * Provides HMAC-SHA256 (HS256) token generation, verification, and payload extraction
 * without external Composer dependencies.
 * 
 * @package Core
 */
class Jwt
{
    /**
     * Encodes a payload dictionary into a signed JWT token string.
     * 
     * @param array $payload Claims and user data dictionary
     * @param int $ttlSeconds Token lifetime in seconds (default: 86400 = 24 hours)
     * @param string|null $secret Custom secret key (defaults to Config::$APP_KEY)
     * @return string Signed JWT token string (Header.Payload.Signature)
     * @example $token = \Core\Jwt::encode(['user_id' => 42, 'role' => 'admin']);
     */
    public static function encode(array $payload, int $ttlSeconds = 86400, ?string $secret = null): string
    {
        $key = $secret ?? Config::$APP_KEY;
        $now = time();

        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds
        ]);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($claims));

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $key, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Decodes and verifies a JWT token.
     * 
     * @param string $token JWT token string
     * @param string|null $secret Custom secret key (defaults to Config::$APP_KEY)
     * @return array|false Returns payload array on success, false if expired or signature invalid
     * @example $claims = \Core\Jwt::decode($token);
     */
    public static function decode(string $token, ?string $secret = null): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $key = $secret ?? Config::$APP_KEY;
        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $key, true));

        if (!hash_equals($expectedSignature, $base64Signature)) {
            return false;
        }

        $payloadJson = self::base64UrlDecode($base64Payload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return false;
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64URL encoding helper.
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64URL decoding helper.
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
