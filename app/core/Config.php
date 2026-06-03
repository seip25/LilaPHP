<?php

namespace Core;

use Dotenv\Dotenv;

class Config
{
    public static string $DIR_PROJECT = '';
    public static string $TITLE_PROJECT = 'LilaPHP';
    public static string $VERSION_PROJECT = '1.0.0';
    public static string $VERSION_API = '1';
    public static bool $DEBUG = true;
    public static bool $TRANSLATE = true;
    public static string $PATH_LOGS = '/app/logs';
    public static string $PATH_LOCALES = '/locales';
    private static string $SECRET_KEY = '';
    public static string $URL_PROJECT = 'http://localhost';
    public static string $LANGHTML = 'en';

    public static string $LANG = 'en';

    public static string $DESCRIPTIONMETA = '';
    public static string $KEYWORDSMETA = '';
    public static string $AUTHORMETA = 'LilaPHP';

    public static string $PATH_CACHE = '/app/cache';

    public static function load(): void
    {
        self::$DIR_PROJECT = dirname(__DIR__, 2);
        $cacheFile = self::$DIR_PROJECT . '/app/cache/env_cache.php';
        $envFile = self::$DIR_PROJECT . '/app/.env';

        $cacheExists = false;

        if (file_exists($cacheFile)) {
            if (file_exists($envFile) && filemtime($envFile) > filemtime($cacheFile)) {
                @unlink($cacheFile);
            } else {
                $cacheExists = true;
                $cachedEnv = require $cacheFile;
                foreach ($cachedEnv as $key => $value) {
                    $_ENV[$key] = $value;
                }
            }
        }

        if (!$cacheExists && file_exists($envFile)) {
            $dotenv = Dotenv::createMutable(self::$DIR_PROJECT . '/app');
            $envVars = $dotenv->load();

            if (isset($envVars['DEBUG']) && ($envVars['DEBUG'] === 'false' || $envVars['DEBUG'] === false)) {
                $cacheDir = self::$DIR_PROJECT . '/app/cache';
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0777, true);
                }
                self::saveCache($cacheFile, $envVars);
            } else {
                self::deleteCache(self::$DIR_PROJECT);
            }
        }

        if ($cacheExists && (($_ENV['DEBUG'] ?? 'true') === 'true')) {
            self::deleteCache(self::$DIR_PROJECT);
        }

        self::$TITLE_PROJECT = $_ENV['TITLE_PROJECT'] ?? 'Seip PHP Framework';
        self::$VERSION_PROJECT = $_ENV['VERSION_PROJECT'] ?? '0.1';
        self::$VERSION_API = (int) ($_ENV['VERSION_API'] ?? 1);
        self::$DEBUG = ($_ENV['DEBUG'] ?? 'true') === 'true';
        self::$TRANSLATE = ($_ENV['TRANSLATE'] ?? 'true') === 'true';
        self::$PATH_LOGS = self::normalizePath(env: 'PATH_LOGS', default: '/app/logs');
        self::$PATH_LOCALES = self::normalizePath(env: 'PATH_LOCALES', default: '/app/locales/');
        self::$SECRET_KEY = $_ENV['SECRET_KEY'] ?? bin2hex(random_bytes(32));
        self::$URL_PROJECT = self::getURLProject();
        self::$LANG = $_ENV['LANG'] ?? 'en';
        self::$LANGHTML = Config::convertLangForHtml($_ENV['LANG'] ?? "en");
        self::$DESCRIPTIONMETA = $_ENV['DESCRIPTIONMETA'] ?? "";
        self::$KEYWORDSMETA = $_ENV['KEYWORDSMETA'] ?? "";
        self::$AUTHORMETA = $_ENV['AUTHORMETA'] ?? "";
        self::$PATH_CACHE = self::normalizePath(env: 'PATH_CACHE', default: '/app/cache');
    }

    public static function deleteCache(string $DIR_PROJECT): void
    {
        $cacheDir = rtrim($DIR_PROJECT, '/') . '/app/cache';

        if (is_dir($cacheDir)) {
            self::recursiveRmdir($cacheDir);
        }

        // Recreate the directory so the app can write new cache files immediately
        @mkdir($cacheDir, 0777, true);
    }

    private static function recursiveRmdir(string $dir): void
    {
        if (!is_dir($dir))
            return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recursiveRmdir("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }

    public static function saveCache(string $path, array $data): bool
    {
        try {
            $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
            return (bool) file_put_contents($path, $content);
        } catch (\Throwable $e) {
            return false;
        }
    }
    public static function convertLangForHtml(string $lang): string
    {
        $l = str_contains($lang, "es") ? "es" : $lang;
        $l = str_contains($lang, "en") ? "en" : $lang;
        return $l;
    }
    public static function getAll(): array
    {
        if (self::$DEBUG == false)
            return [];
        return [
            "DIR_PROJECT" => self::$DIR_PROJECT,
            "URL_PROJECT" => self::$URL_PROJECT,
            "PATH_LOCALES" => self::$PATH_LOCALES,
            "PATH_LOGS" => self::$PATH_LOGS,
            "DEBUG" => self::$DEBUG,
            "VERSION_PROJECT" => self::$VERSION_PROJECT,
            "TITLE_PROJECT" => self::$TITLE_PROJECT,
            "VERSION_API" => (int) self::$VERSION_API,
            "DESCRIPTIONMETA" => self::$DESCRIPTIONMETA,
            "KEYWORDSMETA" => self::$KEYWORDSMETA,
            "AUTHORMETA" => self::$AUTHORMETA,

        ];
    }

    public static function getURLProject()
    {
        if (!empty($_ENV['URL_PROJECT'])) {
            return rtrim($_ENV['URL_PROJECT'], '/') . '/';
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $base = rtrim($scriptName, '/');
        $url = str_replace("\/", "/", "$protocol://$host$base/");
        return $url;
    }
    public static function Env(string $key): string|null
    {
        $val = $_ENV[$key] ?? null;
        return is_null(value: $val) ? null : trim(string: $val);
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
