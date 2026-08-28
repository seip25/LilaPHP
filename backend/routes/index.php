<?php

declare(strict_types=1);

/**
 * API Root Route (`GET /api` or `GET /api/`).
 */

use Core\Response;
use Core\Request;
use Core\Config;

Request::any([], function () {
    Response::json([
        'status'      => 'ok',
        'engine'      => 'LilaPHP Micro-API Engine',
        'version'     => '2.0.0',
        'environment' => Config::$APP_ENV,
        'debug'       => Config::$DEBUG,
        'database'    => Config::$DB_TYPE,
        'timestamp'   => time(),
        'endpoints'   => [
            'GET  /api'        => 'API overview and metadata',
            'GET  /api/health' => 'System diagnostics, database & redis status',
            'GET  /api/users'  => 'Users resource demo (REST CRUD)',
        ],
        'message'     => 'LilaPHP REST API is running and ready.'
    ]);
});
