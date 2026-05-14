<?php

namespace Core;

use Throwable;

class Dispatcher
{
    public static function run(): void
    {
        $dirLilaPHP = dirname(__DIR__, 2);
        $dirRoutes = $dirLilaPHP . "/routes";

        try {
            self::dispatch($dirLilaPHP, $dirRoutes);
        } catch (Throwable $error) {
            http_response_code(500);
            if (Config::$DEBUG) {
                echo "<h1>Erro Interno</h1>";
                echo "<pre>" . $error->getMessage() . "\n" . $error->getTraceAsString() . "</pre>";
            } else {
                echo "<h1>500 Internal Server Error</h1>";
                echo "<p>Something went wrong. Please try again later.</p>";
            }
        }
    }

    /**
     * Handles the request dispatching process
     * 
     * @param string $dirLilaPHP Framework directory
     * @param string $dirRoutes Routes directory
     * @return void
     */
    private static function dispatch(string $dirLilaPHP, string $dirRoutes): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (Config::$DEBUG) {
            Debug::init();
            Debug::start();
        }
        Security::applyGeneralSecurityHeaders();

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($scriptName), '/\\');

        $path = substr($uri, strlen($basePath));
        $path = explode('?', $path)[0];
        $path = trim($path, '/');

        $languages = self::getSupportedLanguages($dirLilaPHP);
        $lang = null;

        $parts = explode('/', $path);
        if (!empty($parts[0]) && in_array($parts[0], $languages)) {
            $lang = array_shift($parts);
            $path = implode('/', $parts);
            Session::set('lang', $lang);
        }

        $targetFile = null;
        if (empty($path)) {
            $targetFile = $dirRoutes . "/index.php";
        } else {
            if (file_exists($dirRoutes . "/" . $path . ".php")) {
                $targetFile = $dirRoutes . "/" . $path . ".php";
            } elseif (is_dir($dirRoutes . "/" . $path) && file_exists($dirRoutes . "/" . $path . "/index.php")) {
                $targetFile = $dirRoutes . "/" . $path . "/index.php";
            }
        }

        if ($targetFile && file_exists($targetFile)) {
            if (Config::$DEBUG) {
                if (!isset($_GET['debug'])) {
                    register_shutdown_function(function () {
                        Debug::end(http_response_code() ?: 200);
                    });
                }
            }
            require_once $targetFile;
        } else {
            http_response_code(404);

            if (Config::$DEBUG) {
                if (!isset($_GET['debug'])) {
                    register_shutdown_function(function () {
                        Debug::end(404);
                    });
                }
            }
            if (file_exists($dirRoutes . "/404.php")) {
                require_once $dirRoutes . "/404.php";
            } else {
                echo "<h1>404 Not Found</h1>";
                echo "<p>The requested route <strong>/{$path}</strong> was not found on this server.</p>";
            }
        }
    }

    private static function getSupportedLanguages(string $dirLilaPHP): array
    {
        $localesDir = $dirLilaPHP . "/locales";
        if (!is_dir($localesDir)) {
            return ['en', 'es', 'pt', 'pt-br'];
        }

        $languages = [];
        $files = scandir($localesDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || str_starts_with($file, 'validation_') || $file === 'seo.php') {
                continue;
            }
            if (str_ends_with($file, '.php')) {
                $languages[] = str_replace('.php', '', $file);
            }
        }
        return $languages ?: ['en', 'es', 'pt', 'pt-br'];
    }
}
