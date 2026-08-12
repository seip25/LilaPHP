<?php

/**
 * LilaPHP Unified Front Controller (`Performance First`).
 * 
 * In production, Nginx serves `frontend/` static assets directly
 * and delegates everything else to this file via FastCGI.
 */

require_once __DIR__ . '/_core/bootstrap.php';

use Core\Dispatcher;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Serve static files in development (PHP built-in server)
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
        'ico' => 'image/x-icon',
    ];
    if (isset($mimeTypes[$ext])) {
        header("Content-Type: {$mimeTypes[$ext]}");
    }
    readfile($staticPath);
    exit;
}

// Dispatch everything else (API and Web Views)
Dispatcher::dispatch();
