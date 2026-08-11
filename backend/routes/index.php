<?php

/**
 * API Root Index Route (`/api/` or `/api/index`).
 * 
 * Returns framework engine status and environment metadata.
 */

use Core\Response;
use Core\Request;
use Core\Config;

Request::any(['cache' => true, 'cache_ttl' => 10], function () {

    $method = Request::getMethod();

    Response::json([
        'method' => $method,
        'status' => 'ok',
        'engine' => 'LilaPHP High-Performance API Framework',
        'version' => '1.0.0',
        'environment' => Config::$APP_ENV,
        'timestamp' => time(),
        'message' => 'API is running and ready for requests.'
    ]);
});
