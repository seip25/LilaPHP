<?php

namespace Core;

class Session
{
    protected static bool $started = false;
    private const CIPHER = 'aes-256-gcm';

    public static function start(): void
    {
        if (self::$started)
            return;
        $secure = Config::$DEBUG == false || isset($_SERVER['HTTPS']) ? true : false;
        session_set_cookie_params([
            'lifetime' => 604800,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
        self::$started = true;

        if (!isset($_SESSION['regenerated_at']) || (time() - $_SESSION['regenerated_at'] > 1800)) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
        self::validate();
    }

    /**
     * @return void
     */
    protected static function validate(): void
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
            self::destroy();
            return;
        }
        $_SESSION['last_activity'] = time();

        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::destroy();
        }
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        if (!self::$started) self::start();
        $_SESSION[$key] = $encrypt
            ? ['__enc' => true, 'value' => self::encrypt($value)]
            : $value;
    }
    public static function get(string $key, mixed $default = null, bool $decrypt = false): mixed
    {
        if (!self::$started) self::start();
        if (!isset($_SESSION[$key])) {
            return $default;
        }

        $stored = $_SESSION[$key];

        if ($decrypt && is_array($stored) && ($stored['__enc'] ?? false)) {
            return self::decrypt($stored['value']);
        }

        return $stored;
    }

    public static function has(string $key): bool
    {
        if (!self::$started) self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        if (!self::$started) self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (!self::$started) self::start();
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        self::$started = false;
    }

    private static function encrypt(mixed $value): string
    {
        $key = self::getKey();
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            json_encode($value),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt(string $payload): mixed
    {
        $key = self::getKey();
        $data = base64_decode($payload);

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? json_decode($decrypted, true) : null;
    }

    private static function getKey(): string
    {
        $key = Config::Env('SECRET_KEY');

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return hash('sha256', $key, true);
    }
}
