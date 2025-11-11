<?php

namespace Core;

class Session
{
    protected static bool $started = false;

    public static function start(): void
    {
        if (self::$started)
            return;
        $secure = Config::$DEBUG == false || isset($_SERVER['HTTPS']) ? true : false;
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();
        self::$started = true;

        if (!isset($_SESSION['regenerated_at']) || (time() - $_SESSION['regenerated_at'] > 300)) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
        self::validate();
    }

    protected static function validate(): void
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::destroy();
            return;
        }
        $_SESSION['last_activity'] = time();

        if (!isset($_SESSION['user_ip']) && !isset($_SESSION['user_agent'])) {
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } else {
            if (
                $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '') ||
                $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
            ) {
                self::destroy();
            }
        }
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        self::$started = false;
    }
}
