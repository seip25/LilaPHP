<?php

declare(strict_types=1);

/**
 * Health Diagnostics Route (`GET /api/health`).
 * 
 * Verifies PHP runtime, PDO database connectivity, Redis cache availability,
 * and memory metrics with zero overhead.
 */

use Core\Response;
use Core\Request;
use Core\Config;
use Core\Database;
use Core\Cache;

Request::GET(['cache' => true, 'cache_ttl' => 10], function () {

    $dbConnected = false;
    $dbDriver = Config::$DB_TYPE;
    $dbError = null;

    try {
        $pdo = Database::getInstance();
        if ($pdo) {
            $pdo->query('SELECT 1');
            $dbConnected = true;
        }
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }


    $redisConnected = false;
    $redisError = null;

    try {
        $redis = Cache::getRedis();
        if ($redis !== null) {
            $redisConnected = true;
        }
    } catch (\Throwable $e) {
        $redisError = $e->getMessage();
    }

    $opcacheEnabled = function_exists('opcache_get_status') && (opcache_get_status() !== false);
    $apcuEnabled = function_exists('apcu_enabled') && apcu_enabled();

    $status = ($dbConnected || $dbDriver === 'sqlite') ? 'healthy' : 'degraded';

    Response::json([
        'status'     => $status,
        'timestamp'  => time(),
        'php'        => [
            'version'   => PHP_VERSION,
            'sapi'      => PHP_SAPI,
            'opcache'   => $opcacheEnabled,
            'apcu'      => $apcuEnabled,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ],
        'database'   => [
            'driver'    => $dbDriver,
            'connected' => $dbConnected,
            'error'     => Config::$DEBUG ? $dbError : null,
        ],
        'redis'      => [
            'host'      => Config::$REDIS_HOST,
            'connected' => $redisConnected,
            'error'     => Config::$DEBUG ? $redisError : null,
        ],
        'uptime_sec' => defined('LILAPHP_START_TIME') ? round(microtime(true) - LILAPHP_START_TIME, 4) : 0,
    ]);
});
