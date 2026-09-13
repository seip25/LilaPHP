<?php

declare(strict_types=1);

namespace Core;

/**
 * Ultra-fast Dual Route Dispatcher (REST API & Web Views).
 * 
 * Maps `/api/*` requests to `app/routes/api/*.php` and web requests to `app/routes/*.php`
 * with parameter extraction, automatic 405 Method Not Allowed detection, and preflight CORS.
 * 
 * @package Core
 */
class Dispatcher
{
    /**
     * Dispatches the incoming HTTP request to an API or View route.
     * 
     * @return void
     */
    public static function dispatch(): void
    {
        Response::handlePreflight();
        Security::applyGeneralSecurityHeaders();

        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = trim($rawUri, '/');
        $isApi = false;

        if (str_starts_with($uri, 'api/')) {
            $uri = substr($uri, 4);
            $isApi = true;
        } elseif ($uri === 'api') {
            $uri = '';
            $isApi = true;
        }

        if ($uri === '' || $uri === 'index') {
            $uri = 'index';
        }

        $baseDir = $isApi ? (Config::$DIR_APP . '/routes/api') : (Config::$DIR_APP . '/routes');

        Request::resetRouteState();

        $targetFile = "{$baseDir}/{$uri}.php";
        $targetIndex = "{$baseDir}/{$uri}/index.php";
        $matchedFile = null;

        if (file_exists($targetFile)) {
            $_SERVER['ROUTE_PARTS'] = explode('/', $uri);
            $_SERVER['ROUTE_PARAMS'] = [];
            $matchedFile = $targetFile;
        } elseif (file_exists($targetIndex)) {
            $_SERVER['ROUTE_PARTS'] = explode('/', $uri);
            $_SERVER['ROUTE_PARAMS'] = [];
            $matchedFile = $targetIndex;
        } else {
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

                    $matchedFile = file_exists($controllerFile) ? $controllerFile : $controllerIndex;
                    break;
                }
            }
        }

        if ($matchedFile !== null) {
            require $matchedFile;

            if (!Request::wasHandled()) {
                $allowed = Request::getAllowedMethods();
                $currentMethod = Request::getMethod();

                if (!empty($allowed)) {
                    $uniqueAllowed = array_values(array_unique($allowed));
                    if (!headers_sent()) {
                        header('Allow: ' . implode(', ', $uniqueAllowed));
                    }

                    if ($isApi) {
                        Response::error("Method `{$currentMethod}` not allowed. Allowed: " . implode(', ', $uniqueAllowed), 405);
                    } else {
                        if (!headers_sent()) {
                            http_response_code(405);
                        }
                        $view405 = Config::$DIR_APP . '/views/405.php';
                        if (file_exists($view405)) {
                            View::render('405', ['method' => $currentMethod, 'allowed' => $uniqueAllowed]);
                        } else {
                            if (!headers_sent()) {
                                header('Content-Type: text/html; charset=utf-8');
                            }
                            $methodEscaped = htmlspecialchars($currentMethod, ENT_QUOTES, 'UTF-8');
                            $allowedEscaped = htmlspecialchars(implode(', ', $uniqueAllowed), ENT_QUOTES, 'UTF-8');
                            echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>405 Method Not Allowed</title><style>body{font-family:sans-serif;padding:50px;background:#0f172a;color:#f8fafc;text-align:center;}h1{color:#ef4444;}</style></head><body><h1>405 Method Not Allowed</h1><p>Method <code>{$methodEscaped}</code> is not permitted on this route.</p><p>Allowed: <code>{$allowedEscaped}</code></p></body></html>";
                        }
                        exit;
                    }
                }
            }
            exit;
        }

        if ($isApi) {
            Response::error(Config::$DEBUG ? "API endpoint `/{$uri}` not found" : 'Endpoint not found', 404);
        } else {
            if (!headers_sent()) {
                http_response_code(404);
            }
            $view404 = Config::$DIR_APP . '/views/404.php';
            if (file_exists($view404)) {
                View::render('404', ['uri' => $uri]);
            } else {
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=utf-8');
                }
                $uriEscaped = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
                echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>404 Not Found</title><style>body{font-family:sans-serif;padding:50px;background:#0f172a;color:#f8fafc;text-align:center;}h1{color:#38bdf8;}</style></head><body><h1>404 Not Found</h1><p>The requested page <code>/{$uriEscaped}</code> could not be found.</p></body></html>";
            }
            exit;
        }
    }
}
