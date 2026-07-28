<?php

use Core\Request;
use Core\Response;
use Core\Config;

Request::GET(function () {
    return [
        'status' => 'success',
        'service' => 'LilaPHP Debug & Performance Engine',
        'debug_logging_enabled' => Config::$DEBUG_LOGGING_ENABLED,
        'cache_driver' => Config::$CACHE_DRIVER
    ];
});
