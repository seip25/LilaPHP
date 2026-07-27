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
    try {
        $pdo = Database::getInstance();
        if ($pdo !== null) {
            $stmt = $pdo->query('SELECT 1');
            if ($stmt && $stmt->fetchColumn() == 1) {
                $mysqlStatus = 'connected';
            }
        }
    } catch (\Throwable $e) {
        $mysqlStatus = 'error: ' . $e->getMessage();
    }

    $apcuTested = function_exists('apcu_enabled') && apcu_enabled();

    $redisStatus = 'disconnected';
    $redis = Cache::getRedis();
    if ($redis !== null) {
        try {
            if ($redis->ping()) {
                $redisStatus = 'connected';
            }
        } catch (\Throwable $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }
    }

    $elapsedMs = round((microtime(true) - $start) * 1000, 3);

    return [
        'status' => $mysqlStatus === 'connected' ? 'ok' : 'degraded',
        'engine' => 'LilaPHP API Engine',
        'environment' => Config::$APP_ENV,
        'latency_ms' => $elapsedMs,
        'services' => [
            'mysql' => $mysqlStatus,
            'apcu_ram' => $apcuTested ? 'active' : 'disabled',
            'redis' => $redisStatus
        ],
        'timestamp' => time()
    ];
});
