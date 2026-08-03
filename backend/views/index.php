<?php

use Services\AuthService;

/**
 * LilaPHP React SPA Layout Configuration.
 * 
 * Defines route-level SEO metadata and server-side middleware protection guards.
 * Delegates rendering and Vite asset resolution to \Core\ViewEngine.
 * 
 * @package LilaPHP
 */

$seoRoutes = [
    '/' => [
        'title' => 'LilaPHP | High-Performance React & PHP 8.4 Framework',
        'description' => 'Ultra-fast PHP 8.4 API engine integrated with React 19 and Vite HMR.',
        'keywords' => 'PHP 8.4, React 19, Vite, High Performance, Nginx, Redis',
    ],
    '/about' => [
        'title' => 'About | LilaPHP Framework',
        'description' => 'Learn about the zero-overhead architecture combining PHP pre-rendering and React SPA hydration.',
        'keywords' => 'Architecture, Micro-caching, OPcache, APCu, FastCGI',
    ],
    '/dashboard' => [
        'title' => 'Dashboard | LilaPHP',
        'description' => 'Protected administrative and real-time diagnostics dashboard.',
        'protected' => true,
        'redirectTo' => '/login',
        'callback' => fn() => AuthService::validateAuth(autoEmit401: false),
    ],
    '/login' => [
        'title' => 'Sign In | LilaPHP',
        'description' => 'Authenticate into your LilaPHP administrative session.',
    ],
];

\Core\ViewEngine::render($seoRoutes);