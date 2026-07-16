<?php

/**
 * Performance Benchmark & System Diagnostics Endpoint (`/api/test`).
 * 
 * Verifies APCu RAM caching, Redis cluster connectivity, and MySQL query execution
 * with microsecond precision.
 */

use Core\Response;
use Core\Cache;
use Core\Database;

$start = microtime(true);

$apcuTested = false;
$apcuValue = null;
if (function_exists('apcu_enabled') && apcu_enabled()) {
    $apcuTested = true;
    $apcuValue = Cache::api('benchmark_apcu_key', fn() => ['cached_at' => microtime(true)], 60);
} else {
    $apcuValue = Cache::api('benchmark_apcu_key', fn() => ['cached_at' => microtime(true), 'fallback' => 'memory'], 60);
}

$redisTested = false;
$redisStatus = 'disconnected';
$redis = Cache::getRedis();
if ($redis !== null) {
    try {
        $redis->set('benchmark_redis_key', 'ok', 10);
        if ($redis->get('benchmark_redis_key') === 'ok') {
            $redisTested = true;
            $redisStatus = 'connected';
        }
    } catch (\Throwable $e) {
        $redisStatus = 'error: ' . $e->getMessage();
    }
}

$mysqlTested = false;
$mysqlStatus = 'disconnected';
$pdo = Database::getInstance();
if ($pdo !== null) {
    try {
        $stmt = $pdo->query('SELECT 1 as test');
        if ($stmt && $stmt->fetch()['test'] == 1) {
            $mysqlTested = true;
            $mysqlStatus = 'connected';
        }
    } catch (\Throwable $e) {
        $mysqlStatus = 'error: ' . $e->getMessage();
    }
}

$elapsedMs = round((microtime(true) - $start) * 1000, 3);

Response::json([
    'status' => 'ok',
    'performance_ms' => $elapsedMs,
    'drivers' => [
        'apcu_ram' => [
            'enabled' => $apcuTested,
            'cached_data' => $apcuValue
        ],
        'redis' => [
            'status' => $redisStatus,
            'functional' => $redisTested
        ],
        'mysql' => [
            'status' => $mysqlStatus,
            'functional' => $mysqlTested
        ]
    ]
]);
