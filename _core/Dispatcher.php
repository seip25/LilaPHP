<?php

declare(strict_types=1);

namespace Core;

/**
 * Ultra-fast REST API Route Dispatcher (`Performance First`).
 * 
 * Maps `/api/*` requests directly to `backend/routes/*.php` or controllers with
 * zero-overhead file inspection, parameter extraction, and automatic preflight CORS.
 * 
 * @package Core
 */
class Dispatcher
{
    /**
     * Dispatches the incoming HTTP request.
     * 
     * @return void
     */
    public static function dispatch(): void
    {
        // 1. Handle CORS Preflight immediately
        Response::handlePreflight();
        Security::applyGeneralSecurityHeaders();

        // 2. Apply Rate Limiting
        if (Config::$RATE_LIMIT > 0 && !Security::rateLimit(Config::$RATE_LIMIT, Config::$RATE_LIMIT_WINDOW)) {
            Response::error('Rate limit exceeded', 429);
        }

        // 3. Normalize Request URI
        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = trim($rawUri, '/');

        // Normalize /api prefix
        if (str_starts_with($uri, 'api/')) {
            $uri = substr($uri, 4);
        } elseif ($uri === 'api') {
            $uri = '';
        }

        if ($uri === '' || $uri === 'index') {
            $uri = 'index';
        }

        $baseDir = Config::$DIR_BACKEND . '/routes';

        // 4. Exact File or Directory Match
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

        // 5. Dynamic Route Parameter Matching (e.g. /api/users/42 -> backend/routes/users.php with ID)
        $parts = explode('/', $uri);
        if (count($parts) >= 2) {
            $controller = $parts[0];
            $paramValue = $parts[1];

            $controllerFile = "{$baseDir}/{$controller}.php";
            $controllerIndex = "{$baseDir}/{$controller}/index.php";

            if (file_exists($controllerFile) || file_exists($controllerIndex)) {
                if ($paramValue !== '') {
                    $_GET['id'] = $_GET['id'] ?? $paramValue;
                    $_SERVER['ROUTE_ID'] = $paramValue;
                    $_SERVER['ROUTE_PARAM'] = $paramValue;
                }

                if (count($parts) >= 3) {
                    $_SERVER['ROUTE_SUB'] = $parts[2];
                }

                if (file_exists($controllerFile)) {
                    require $controllerFile;
                } else {
                    require $controllerIndex;
                }
                exit;
            }
        }

        // 6. Fallback: Endpoint Not Found
        Response::error("API endpoint `/{$uri}` not found", 404);
    }
}
