<?php

declare(strict_types=1);

namespace Core;

/**
 * Ultra-fast REST API Route Dispatcher.
 * 
 * Maps `/api/*` requests directly to `backend/routes/*.php` or controllers with
 * zero-overhead file inspection, multi-level parameter extraction, and automatic preflight CORS.
 * 
 * @package Core
 */
class Dispatcher
{
    /**
     * Dispatches the incoming HTTP request.
     */
    public static function dispatch(): void
    {
        Response::handlePreflight();
        Security::applyGeneralSecurityHeaders();

        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = trim($rawUri, '/');

        if (str_starts_with($uri, 'api/')) {
            $uri = substr($uri, 4);
        } elseif ($uri === 'api') {
            $uri = '';
        }

        if ($uri === '' || $uri === 'index') {
            $uri = 'index';
        }

        $baseDir = Config::$DIR_BACKEND . '/routes';

        $targetFile = "{$baseDir}/{$uri}.php";
        $targetIndex = "{$baseDir}/{$uri}/index.php";

        if (file_exists($targetFile)) {
            $_SERVER['ROUTE_PARTS'] = explode('/', $uri);
            $_SERVER['ROUTE_PARAMS'] = [];
            require $targetFile;
            exit;
        }

        if (file_exists($targetIndex)) {
            $_SERVER['ROUTE_PARTS'] = explode('/', $uri);
            $_SERVER['ROUTE_PARAMS'] = [];
            require $targetIndex;
            exit;
        }

        $parts = explode('/', $uri);
        $totalParts = count($parts);

        for ($i = $totalParts - 1; $i >= 1; $i--) {
            $prefix = implode('/', array_slice($parts, 0, $i));
            $controllerFile = "{$baseDir}/{$prefix}.php";
            $controllerIndex = "{$baseDir}/{$prefix}/index.php";

            if (file_exists($controllerFile) || file_exists($controllerIndex)) {
                $params = array_slice($parts, $i);
                $_SERVER['ROUTE_PARTS'] = $parts;
                $_SERVER['ROUTE_PARAMS'] = $params;

                if (!empty($params)) {
                    $paramValue = $params[0];
                    if ($paramValue !== '') {
                        $_GET['id'] = $paramValue;
                        $_SERVER['ROUTE_ID'] = $paramValue;
                        $_SERVER['ROUTE_PARAM'] = $paramValue;
                    }
                    if (isset($params[1])) {
                        $_SERVER['ROUTE_SUB'] = $params[1];
                    }
                }

                if (file_exists($controllerFile)) {
                    require $controllerFile;
                } else {
                    require $controllerIndex;
                }
                exit;
            }
        }

        Response::error(Config::$DEBUG ? "API endpoint `/{$uri}` not found" : 'Endpoint not found', 404);
    }
}
