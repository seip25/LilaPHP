<?php

use Core\Request;
use Core\Response;
use Core\Debug;

Request::GET(function () {
    $target = Request::input('target', 'logs');
    $key = Request::input('key', null);

    if ($key) {
        $success = Debug::clearKey((string)$key);
    } elseif ($target === 'cache') {
        $success = Debug::purgeCache();
    } else {
        $success = Debug::clearLogs();
    }

    Response::json([
        'status' => $success ? 'success' : 'error',
        'message' => $success ? "Cleared target `{$target}` successfully" : "Failed to clear target `{$target}`"
    ]);
});
