<?php

/**
 * Health Diagnostics Endpoint (`/api/health`).
 * 
 * Verifies MySQL, APCu, Redis, and OPcache status in microsecond precision.
 */

use Core\Request;
use Core\Response;
use Core\Config;
use Core\Database;
use Core\Cache;

Request::GET(function () {
    $start = microtime(true);

    $mysqlStatus = 'disconnected';
    $mysqlStatus = Cache::db("mysql_health", function () {
        try {
            $pdo = Database::getInstance();
            if ($pdo !== null) {
                $stmt = $pdo->query('SELECT 1');
                if ($stmt && $stmt->fetchColumn() == 1) {
                    return 'connected';
                }
                return "disconnected";
            }
        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }, 10);
    $apcuTested = Cache::db("apcu_health", function () {
        return function_exists('apcu_enabled') && apcu_enabled();
    }, 10);

    $redisStatus = Cache::db("redis_health", function () {
        $redis = Cache::getRedis();
        if ($redis !== null) {
            try {
                if ($redis->ping()) {
                    return 'connected';
                }
            } catch (\Throwable $e) {
                return 'error: ' . $e->getMessage();
            }
        }
    }, 10);

    $elapsedMs = round((microtime(true) - $start) * 1000, 3);

    Response::json([
        'status' => $mysqlStatus === 'connected' ? 'ok' : 'degraded',
        'engine' => 'LilaPHP API Engine',
        'environment' => Config::$APP_ENV,
        'latency_ms' => $elapsedMs,
        'performance_ms' => $elapsedMs . ' ms',
        'services' => [
            'mysql' => $mysqlStatus,
            'apcu_ram' => $apcuTested ? 'active' : 'disabled',
            'redis' => $redisStatus
        ],
        'drivers' => [
            'apcu_ram' => ['enabled' => $apcuTested],
            'redis' => ['functional' => $redisStatus === 'connected', 'status' => $redisStatus],
            'mysql' => ['functional' => $mysqlStatus === 'connected', 'status' => $mysqlStatus]
        ],
        'timestamp' => time()
    ]);
});
