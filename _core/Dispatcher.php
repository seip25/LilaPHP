<?php

namespace Core;

/**
 * File-based API Route Dispatcher.
 * 
 * Maps `/api/*` requests directly to `backend/routes/*.php` files in dev/fallback mode
 * with zero-overhead routing inspection.
 * 
 * @package Core
 */
class Dispatcher
{
    /**
     * Resolves the request URI to a PHP script inside `backend/routes/`.
     * 
     * @return void
     * @example \Core\Dispatcher::dispatch();
     */
    public static function dispatch(): void
    {
        Response::handlePreflight();
        Security::applyGeneralSecurityHeaders();

        if (Config::$RATE_LIMIT > 0 && !Security::rateLimit(Config::$RATE_LIMIT, Config::$RATE_LIMIT_WINDOW)) {
            Response::error('Rate limit exceeded', 429);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = trim($uri, '/');

        if ($uri === '' || $uri === 'index') {
            $uri = 'index';
        }

        if ($uri === 'debug') {
            $debugFile = Config::$DIR_CORE . '/routes/frontend/debug.php';
            if (file_exists($debugFile)) {
                require $debugFile;
                exit;
            }
        }

        if (str_starts_with($uri, '404')) {
            $coreFile = Config::$DIR_CORE . "/routes/{$uri}.php";
            if (file_exists($coreFile)) {
                require $coreFile;
                exit;
            }
        }

        $isApi = str_starts_with($uri, 'api/') || $uri === 'api';

        if ($isApi) {
            $uri = $uri === 'api' ? 'index' : substr($uri, 4);
            $baseDir = Config::$DIR_BACKEND . '/routes';
        } else {
            $baseDir = Config::$DIR_BACKEND . '/views';
        }

        $targetFile = "{$baseDir}/{$uri}.php";
        $targetIndex = "{$baseDir}/{$uri}/index.php";

        if (file_exists($targetFile)) {
            require $targetFile;
            exit;
        }

        if (file_exists($targetIndex)) {
            require $targetIndex;
            exit;
        }

        $parts = explode('/', $uri);
        if (count($parts) >= 2) {
            $baseController = $parts[0];
            $baseFile = "{$baseDir}/{$baseController}.php";
            $baseIndex = "{$baseDir}/{$baseController}/index.php";

            if (file_exists($baseFile) || file_exists($baseIndex)) {
                if (isset($parts[1]) && $parts[1] !== '') {
                    $_GET['id'] = $_GET['id'] ?? $parts[1];
                    $_SERVER['ROUTE_ID'] = $parts[1];
                }
                if (file_exists($baseFile)) {
                    require $baseFile;
                } else {
                    require $baseIndex;
                }
                exit;
            }
        }

        $fallback404 = Config::$DIR_CORE . '/routes/404.php';
        if (file_exists($fallback404)) {
            require $fallback404;
        } else {
            Response::error('Endpoint not found', 404);
        }
        exit;
    }
}
