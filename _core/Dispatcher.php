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

        if (!Security::rateLimit(200, 60)) {
            Response::error('Rate limit exceeded', 429);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = trim($uri, '/');

        if (str_starts_with($uri, 'api/')) {
            $uri = substr($uri, 4);
        } elseif ($uri === 'api') {
            $uri = '';
        }

        if ($uri === '' || $uri === 'index') {
            $uri = 'index';
        }

        $routesDir = Config::$DIR_BACKEND . '/routes';
        $targetFile = "{$routesDir}/{$uri}.php";
        $targetIndex = "{$routesDir}/{$uri}/index.php";

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
            $baseFile = "{$routesDir}/{$baseController}.php";
            $baseIndex = "{$routesDir}/{$baseController}/index.php";
            if (file_exists($baseFile) || file_exists($baseIndex)) {
                $_GET['id'] = $_GET['id'] ?? $parts[1];
                $_SERVER['ROUTE_ID'] = $parts[1];
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
