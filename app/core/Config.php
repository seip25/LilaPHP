<?php

namespace Core;

use Dotenv\Dotenv;

class Config
{
    public static string $DIR_PROJECT;
    public static string $TITLE_PROJECT;
    public static string $VERSION_PROJECT;
    public static string $VERSION_API;
    public static bool $DEBUG;
    public static string $PATH_LOGS;
    public static string $PATH_LOCALES;
    private static string $SECRET_KEY;
    public static string $URL_PROJECT;

    public static function load(): void
    {
        self::$DIR_PROJECT = dirname(__DIR__, 1);
        if (file_exists(self::$DIR_PROJECT . '/.env')) {
            $dotenv = Dotenv::createImmutable(self::$DIR_PROJECT);
            $dotenv->load();
        }
        self::$TITLE_PROJECT = $_ENV['TITLE_PROJECT'] ?? 'Seip PHP Framework';
        self::$VERSION_PROJECT = $_ENV['VERSION_PROJECT'] ?? '0.1';
        self::$VERSION_API = (int)$_ENV['VERSION_API'] ?? 1;
        self::$DEBUG = ($_ENV['DEBUG'] ?? 'true') === 'true';
        self::$PATH_LOGS = self::normalizePath(env: 'PATH_LOGS', default: '/logs');
        self::$PATH_LOCALES = self::normalizePath(env: 'PATH_LOCALES', default: '/locales/');
        self::$SECRET_KEY = $_ENV['SECRET_KEY'] ?? bin2hex(random_bytes(32));
        self::$URL_PROJECT = self::getURLProject();
    }
    public static function getAll(): array
    {
        if (self::$DEBUG == false) return [];
        return [
            "DIR_PROJECT"=>self::$DIR_PROJECT,
            "URL_PROJECT" => self::$URL_PROJECT,
            "PATH_LOCALES" => self::$PATH_LOCALES,
            "PATH_LOGS" => self::$PATH_LOGS,
            "DEBUG" => self::$DEBUG,
            "VERSION_PROJECT" => self::$VERSION_PROJECT,
            "TITLE_PROJECT" => self::$TITLE_PROJECT,
            "VERSION_API" =>(int) self::$VERSION_API,

        ];
    }

    public static function getURLProject()
    {
        if (!empty($_ENV['URL_PROJECT'])) {
            return  rtrim($_ENV['URL_PROJECT'], '/') . '/';
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $base = rtrim($scriptName, '/');
        $url = str_replace("\/", "/", "$protocol://$host$base/");
        return $url;
    }
    public static function normalizePath(string $env, string $default)
    {
        return isset($_ENV[$env]) ? self::$DIR_PROJECT . '/' . $_ENV[$env] : self::$DIR_PROJECT . '' . $default;
    }
    public static function getSecretKey(): string
    {
        return self::$SECRET_KEY;
    }
}
