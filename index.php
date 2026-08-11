<?php

/**
 * LilaPHP Unified Front Controller (`Performance First`).
 * 
 * In production, Nginx serves `frontend/` static assets directly on `/`
 * and delegates `/api/*` directly to `backend/routes/*.php` via FastCGI.
 * 
 * When running under PHP built-in server (`php -S`) or fallback front controller:
 * - Requests starting with `/api` are dispatched to `backend/routes/*.php`.
 * - All other requests are rendered by `frontend/index.php` via ViewEngine.
 */

require_once __DIR__ . '/_core/bootstrap.php';

use Core\Dispatcher;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (str_starts_with($uri, '/api/') || $uri === '/api') {
    Dispatcher::dispatch();
}

$staticPath = str_starts_with($uri, '/frontend/') ? __DIR__ . $uri : __DIR__ . '/frontend' . $uri;
if ($uri !== '/' && file_exists($staticPath) && is_file($staticPath)) {
    $ext = pathinfo($staticPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'html' => 'text/html',
        'jsx' => 'application/javascript'
    ];
    if (isset($mimeTypes[$ext])) {
        header("Content-Type: {$mimeTypes[$ext]}");
    }
    readfile($staticPath);
    exit;
}

$viewPath = __DIR__ . '/frontend/index.php';
if (file_exists($viewPath)) {
    require $viewPath;
    exit;
}

\Core\Response::error('Frontend index.php entry point not found', 404);
