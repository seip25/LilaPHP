<?php

declare(strict_types=1);

/**
 * LilaPHP Unified Front Controller & Development Server Router.
 * 
 * Delivers public static assets directly and routes all API and Web requests
 * to the zero-dependency Dispatcher engine.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri !== '/') {
    $staticPath = __DIR__ . '/public' . $uri;

    if (file_exists($staticPath) && is_file($staticPath)) {
        $ext = strtolower(pathinfo($staticPath, PATHINFO_EXTENSION));

        if ($ext === 'php') {
            http_response_code(403);
            exit('Forbidden');
        }

        $mimeTypes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'txt'   => 'text/plain; charset=utf-8',
            'xml'   => 'application/xml; charset=utf-8',
        ];

        if (isset($mimeTypes[$ext])) {
            header("Content-Type: {$mimeTypes[$ext]}");
        }

        readfile($staticPath);
        exit;
    }
}

require_once __DIR__ . '/_core/bootstrap.php';
\Core\Dispatcher::dispatch();
