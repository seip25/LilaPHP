<?php

declare(strict_types=1);

namespace Core;

/**
 * High-performance configuration and environment variable loader.
 * 
 * Automatically loads .env configurations and caches parsed arrays for
 * zero-overhead OPcache memory loading in production environments.
 * 
 * @package Core
 */
class Config
{
    public static string $DIR_PROJECT = '';
    public static string $DIR_CORE = '';
    public static string $DIR_BACKEND = '';
    public static string $DIR_FRONTEND = '';
    public static string $APP_NAME = 'LilaPHP';
    public static string $APP_ENV = 'development';
    public static bool $DEBUG = true;
    public static string $APP_URL = 'http://localhost:8080';
    public static int $HTTP_PORT = 8080;
    public static int $PROD_HTTP_PORT = 80;

    // Database Configuration (Default: MySQL | Optional: SQLite / PostgreSQL)
    public static string $DB_TYPE = 'mysql';
    public static string $DB_HOST = 'mysql';
    public static int $DB_PORT = 3306;
    public static string $DB_NAME = 'lilaphp';
    public static string $DB_USER = 'root';
    public static string $DB_PASSWORD = 'secret';
    public static string $DB_FILE = '';

    // Cache & Redis
    public static string $REDIS_HOST = 'redis';
    public static int $REDIS_PORT = 6379;
    public static string $REDIS_PASSWORD = '';
    public static string $CACHE_DRIVER = 'file';
    public static string $DB_CACHE_DRIVER = 'file';

    // Security & Limits
    public static string $APP_KEY = '';
    public static string $CORS_ALLOWED_ORIGINS = '*';
    public static bool $LOG_ENABLED = false;
    public static bool $DEBUG_LOGGING_ENABLED = false;
    public static int $RATE_LIMIT = 200;
    public static int $RATE_LIMIT_WINDOW = 60;

    // AI & LLM Engine Configuration
    public static string $AI_PROVIDER = 'openai';
    public static string $AI_KEY = '';
    public static string $OPENAI_API_KEY = '';
    public static string $GEMINI_API_KEY = '';
    public static string $DEEPSEEK_API_KEY = '';
    public static string $ANTHROPIC_API_KEY = '';
    public static string $OLLAMA_BASE_URL = 'http://localhost:11434';

    private static bool $loaded = false;

    /**
     * Loads environment configurations from cache or .env file.
     * 
     * @return void
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$DIR_PROJECT = dirname(__DIR__);
        self::$DIR_CORE = self::$DIR_PROJECT . '/_core';
        self::$DIR_BACKEND = self::$DIR_PROJECT . '/backend';
        self::$DIR_FRONTEND = self::$DIR_PROJECT . '/frontend';

        $cacheFile = self::$DIR_CORE . '/cache/env.php';
        $envFile = self::$DIR_BACKEND . '/.env';

        if (!file_exists($envFile) && file_exists(self::$DIR_PROJECT . '/.env')) {
            $envFile = self::$DIR_PROJECT . '/.env';
        }

        $envVars = [];
        $cacheLoaded = false;

        if (file_exists($cacheFile)) {
            if (file_exists($envFile) && filemtime($envFile) > filemtime($cacheFile)) {
                @unlink($cacheFile);
            } else {
                $envVars = require $cacheFile;
                $cacheLoaded = true;
            }
        }

        if (!$cacheLoaded && file_exists($envFile)) {
            $envVars = self::parseEnvFile($envFile);
            if (!is_dir(self::$DIR_CORE . '/cache')) {
                @mkdir(self::$DIR_CORE . '/cache', 0777, true);
            }
            
            // Clean robust caching condition: only in production or when debug is explicitly false
            $isDebug = filter_var($envVars['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $isProduction = ($envVars['APP_ENV'] ?? 'development') === 'production';
            if (!$isDebug || $isProduction) {
                self::saveCache($cacheFile, $envVars);
            }
        }

        foreach ($envVars as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        self::$APP_NAME = (string) ($_ENV['APP_NAME'] ?? 'LilaPHP');
        self::$APP_ENV = (string) ($_ENV['APP_ENV'] ?? 'development');
        self::$DEBUG = filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN);
        self::$APP_URL = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost:8080'), '/');
        self::$HTTP_PORT = (int) ($_ENV['HTTP_PORT'] ?? 8080);
        self::$PROD_HTTP_PORT = (int) ($_ENV['PROD_HTTP_PORT'] ?? 80);

        self::$DB_TYPE = strtolower((string) ($_ENV['DB_TYPE'] ?? 'mysql'));
        self::$DB_HOST = (string) ($_ENV['DB_HOST'] ?? 'mysql');
        self::$DB_PORT = (file_exists('/.dockerenv') && self::$DB_HOST === 'mysql') ? 3306 : (int) ($_ENV['DB_PORT'] ?? 3306);
        self::$DB_NAME = (string) ($_ENV['DB_NAME'] ?? 'lilaphp');
        self::$DB_USER = (string) ($_ENV['DB_USER'] ?? 'root');
        self::$DB_PASSWORD = (string) ($_ENV['DB_PASSWORD'] ?? 'secret');
        self::$DB_FILE = (string) ($_ENV['DB_FILE'] ?? (self::$DIR_BACKEND . '/database/app.sqlite'));

        self::$REDIS_HOST = (string) ($_ENV['REDIS_HOST'] ?? 'redis');
        self::$REDIS_PORT = (int) ($_ENV['REDIS_PORT'] ?? 6379);
        self::$REDIS_PASSWORD = (string) ($_ENV['REDIS_PASSWORD'] ?? '');

        self::$CACHE_DRIVER = (string) ($_ENV['CACHE_DRIVER'] ?? (function_exists('apcu_fetch') ? 'apcu' : 'file'));
        self::$DB_CACHE_DRIVER = (string) ($_ENV['DB_CACHE_DRIVER'] ?? 'file');
        self::$APP_KEY = (string) ($_ENV['APP_KEY'] ?? '');
        self::$CORS_ALLOWED_ORIGINS = (string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
        self::$LOG_ENABLED = filter_var($_ENV['LOG_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        self::$DEBUG_LOGGING_ENABLED = filter_var($_ENV['DEBUG_LOGGING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $rateLimitVal = strtolower(trim((string) ($_ENV['RATE_LIMIT'] ?? '200')));
        self::$RATE_LIMIT = in_array($rateLimitVal, ['false', '0', 'none', 'off', 'null'], true) ? 0 : (int) $rateLimitVal;
        self::$RATE_LIMIT_WINDOW = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);

        self::$AI_PROVIDER = (string) ($_ENV['AI_PROVIDER'] ?? 'openai');
        self::$AI_KEY = (string) ($_ENV['API_IA_KEY'] ?? $_ENV['AI_KEY'] ?? '');
        self::$OPENAI_API_KEY = (string) ($_ENV['OPENAI_API_KEY'] ?? self::$AI_KEY);
        self::$GEMINI_API_KEY = (string) ($_ENV['GEMINI_API_KEY'] ?? self::$AI_KEY);
        self::$DEEPSEEK_API_KEY = (string) ($_ENV['DEEPSEEK_API_KEY'] ?? self::$AI_KEY);
        self::$ANTHROPIC_API_KEY = (string) ($_ENV['ANTHROPIC_API_KEY'] ?? self::$AI_KEY);
        self::$OLLAMA_BASE_URL = (string) ($_ENV['OLLAMA_BASE_URL'] ?? 'http://localhost:11434');

        self::$loaded = true;
    }

    /**
     * Parses a .env file into an associative array without external library overhead.
     * 
     * @param string $path Absolute path to the .env file
     * @return array<string, string|bool|int|float>
     */
    private static function parseEnvFile(string $path): array
    {
        $parsed = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (strtolower($value) === 'true') {
                $value = true;
            } elseif (strtolower($value) === 'false') {
                $value = false;
            } elseif (strtolower($value) === 'null') {
                $value = '';
            } elseif (is_numeric($value) && ($value === '0' || !str_starts_with($value, '0') || str_starts_with($value, '0.'))) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * Saves parsed environment array into a PHP file for OPcache direct loading.
     */
    public static function saveCache(string $path, array $data): bool
    {
        try {
            $export = var_export($data, true);
            $content = "<?php\n\nreturn {$export};\n";
            return file_put_contents($path, $content, LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Compiles and caches environment configurations into _core/cache/env.php.
     *
     * @param string|null $envFile Custom .env path (optional)
     * @return bool True on success, false on failure
     */
    public static function cache(?string $envFile = null): bool
    {
        if (self::$DIR_PROJECT === '') {
            self::$DIR_PROJECT = dirname(__DIR__);
            self::$DIR_CORE = self::$DIR_PROJECT . '/_core';
            self::$DIR_BACKEND = self::$DIR_PROJECT . '/backend';
            self::$DIR_FRONTEND = self::$DIR_PROJECT . '/frontend';
        }

        if ($envFile === null) {
            $envFile = self::$DIR_BACKEND . '/.env';
            if (!file_exists($envFile) && file_exists(self::$DIR_PROJECT . '/.env')) {
                $envFile = self::$DIR_PROJECT . '/.env';
            }
        }

        if (!file_exists($envFile)) {
            return false;
        }

        $envVars = self::parseEnvFile($envFile);

        $cacheDir = self::$DIR_CORE . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheFile = $cacheDir . '/env.php';
        return self::saveCache($cacheFile, $envVars);
    }

    /**
     * Clears the compiled configuration cache file.
     *
     * @return bool
     */
    public static function clearCache(): bool
    {
        if (self::$DIR_CORE === '') {
            self::$DIR_CORE = dirname(__DIR__) . '/_core';
        }

        $cacheFile = self::$DIR_CORE . '/cache/env.php';
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }

        return true;
    }
}

