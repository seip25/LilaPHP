<?php

declare(strict_types=1);

/**
 * LilaPHP Unified Front Controller & Development Server Router.
 * 
 * - In Production: Nginx serves `frontend/` static assets directly and
 *   proxies `/api/` requests to `backend/index.php`.
 * - In Development (`php -S localhost:8080 index.php`): This file serves
 *   frontend static files directly and routes `/api/*` to the PHP backend.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// --------------------------------------------------------------------------
// 1. API Requests -> Route to Backend REST API Engine
// --------------------------------------------------------------------------
if (str_starts_with($uri, '/api/') || $uri === '/api') {
    require_once __DIR__ . '/_core/bootstrap.php';
    \Core\Dispatcher::dispatch();
    exit;
}

// --------------------------------------------------------------------------
// 2. Static Frontend Delivery (HTML, CSS, JS, Images, Fonts)
// --------------------------------------------------------------------------
$candidatePaths = [
    __DIR__ . '/frontend' . $uri,
    __DIR__ . '/frontend' . $uri . '.html',
    __DIR__ . '/frontend' . $uri . '/index.html',
];

if (str_starts_with($uri, '/frontend/')) {
    $candidatePaths[] = __DIR__ . $uri;
}

foreach ($candidatePaths as $staticPath) {
    if (file_exists($staticPath) && is_file($staticPath)) {
        $ext = strtolower(pathinfo($staticPath, PATHINFO_EXTENSION));

        // Security: Never serve PHP source files directly
        if ($ext === 'php') {
            http_response_code(403);
            exit('Forbidden');
        }

        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'ts'   => 'text/plain; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'eot'  => 'application/vnd.ms-fontobject',
        ];

        if (isset($mimeTypes[$ext])) {
            header("Content-Type: {$mimeTypes[$ext]}");
        }

        readfile($staticPath);
        exit;
    }
}

// --------------------------------------------------------------------------
// 3. Fallback to Frontend index.html (SPA / Clean HTML Routing)
// --------------------------------------------------------------------------
$indexHtml = __DIR__ . '/frontend/index.html';
if (file_exists($indexHtml)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexHtml);
    exit;
}

http_response_code(404);
echo '404 - Not Found';
