<?php

namespace Core;

/**
 * LilaPHP Built-in Debug & Performance Engine.
 * 
 * Provides zero-overhead request profiling via Redis, system health checks
 * (Redis, MySQL, PHP-FPM, CPU/RAM/Disk), route discovery, and performance metrics.
 * 
 * @package Core
 */
class Debug
{
    private const REDIS_KEY_LOGS = 'lilaphp:debug:requests';
    private const MAX_LOG_ITEMS = 200;

    /**
     * Records the current HTTP request metrics into Redis if DEBUG_LOGGING_ENABLED is true.
     * 
     * @return void
     */
    public static function recordRequest(): void
    {
        if (!Config::$DEBUG_LOGGING_ENABLED) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|webp|woff2|ttf|eot)$/i', $path)) {
            return;
        }

        // Ignore internal debug endpoints and dashboard HTML to prevent log recursion
        if (str_starts_with($path, '/api/debug') || $path === '/debug.html' || $path === '/debug') {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $startTime = defined('LILAPHP_START_TIME') ? LILAPHP_START_TIME : microtime(true);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $peakMemoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        $statusCode = http_response_code() ?: 200;

        $queryParams = $_GET ?? [];
        $bodyParams = $_POST ?? [];

        unset($bodyParams['password'], $bodyParams['token']);

        $entry = [
            'id' => uniqid('req_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'microtime' => microtime(true),
            'method' => $method,
            'uri' => $uri,
            'path' => $path,
            'status' => $statusCode,
            'duration_ms' => $durationMs,
            'memory_mb' => $peakMemoryMb,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'query' => $queryParams,
            'body' => $bodyParams
        ];

        try {
            $redis = Cache::getRedis();
            if ($redis) {
                $redis->lPush(self::REDIS_KEY_LOGS, json_encode($entry));
                $redis->lTrim(self::REDIS_KEY_LOGS, 0, self::MAX_LOG_ITEMS - 1);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Retrieves recent logged request metrics from Redis.
     * 
     * @param int $limit Max items to return
     * @return array<string, mixed>
     */
    public static function getMetrics(int $limit = 50): array
    {
        $logs = [];
        $totalDuration = 0;
        $totalMemory = 0;
        $statusCounts = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];

        try {
            $redis = Cache::getRedis();
            if ($redis) {
                $rawLogs = $redis->lRange(self::REDIS_KEY_LOGS, 0, $limit - 1);
                foreach ($rawLogs as $raw) {
                    $item = json_decode($raw, true);
                    if (is_array($item)) {
                        $logs[] = $item;
                        $totalDuration += $item['duration_ms'] ?? 0;
                        $totalMemory += $item['memory_mb'] ?? 0;

                        $st = (int) ($item['status'] ?? 200);
                        if ($st >= 200 && $st < 300)
                            $statusCounts['2xx']++;
                        elseif ($st >= 300 && $st < 400)
                            $statusCounts['3xx']++;
                        elseif ($st >= 400 && $st < 500)
                            $statusCounts['4xx']++;
                        elseif ($st >= 500)
                            $statusCounts['5xx']++;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $count = count($logs);
        return [
            'enabled' => Config::$DEBUG_LOGGING_ENABLED,
            'total_logged' => $count,
            'avg_duration_ms' => $count > 0 ? round($totalDuration / $count, 2) : 0,
            'avg_memory_mb' => $count > 0 ? round($totalMemory / $count, 2) : 0,
            'status_counts' => $statusCounts,
            'requests' => $logs
        ];
    }

    /**
     * Performs a comprehensive health check across Redis, MySQL, PHP-FPM, RAM, CPU, and Disk.
     * 
     * @return array<string, mixed>
     */
    public static function getHealth(): array
    {
        $redisHealth = [
            'status' => 'offline',
            'ping_ms' => 0,
            'used_memory_human' => 'N/A',
            'keys_count' => 0
        ];
        try {
            $start = microtime(true);
            $redis = Cache::getRedis();
            if ($redis) {
                $ping = $redis->ping();
                $pingMs = round((microtime(true) - $start) * 1000, 2);
                $info = $redis->info('memory');
                $dbSize = $redis->dbSize();

                $redisHealth = [
                    'status' => ($ping === true || $ping === '+PONG' || $ping === 'PONG') ? 'online' : 'degraded',
                    'ping_ms' => $pingMs,
                    'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                    'keys_count' => $dbSize
                ];
            }
        } catch (\Throwable $e) {
            $redisHealth['error'] = $e->getMessage();
        }

        $dbHealth = [
            'status' => 'offline',
            'ping_ms' => 0,
            'driver' => Config::$DB_TYPE,
            'version' => 'N/A'
        ];
        try {
            $start = microtime(true);
            $pdo = Database::getInstance();
            $pingMs = round((microtime(true) - $start) * 1000, 2);
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            $dbHealth = [
                'status' => 'online',
                'ping_ms' => $pingMs,
                'driver' => Config::$DB_TYPE,
                'version' => $version
            ];
        } catch (\Throwable $e) {
            $dbHealth['error'] = $e->getMessage();
        }

        $opcacheEnabled = function_exists('opcache_get_status') && is_array(opcache_get_status(false));
        $phpHealth = [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_used_mb' => round(memory_get_usage(true) / (1024 * 1024), 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
            'opcache_enabled' => $opcacheEnabled,
            'apcu_enabled' => extension_loaded('apcu') && apcu_enabled()
        ];

        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $diskFree = @disk_free_space('/');
        $diskTotal = @disk_total_space('/');
        $diskUsedPercent = ($diskFree !== false && $diskTotal > 0) ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        // Container / cgroup Memory & Limits
        $containerMem = null;
        if (file_exists('/sys/fs/cgroup/memory.current')) {
            $memBytes = (int)trim((string)@file_get_contents('/sys/fs/cgroup/memory.current'));
            $maxBytes = (int)trim((string)@file_get_contents('/sys/fs/cgroup/memory.max'));
            if ($memBytes > 0) {
                $containerMem = [
                    'used_mb' => round($memBytes / (1024 * 1024), 2),
                    'limit_mb' => ($maxBytes > 0 && $maxBytes < 9000000000000000000) ? round($maxBytes / (1024 * 1024), 2) : 'unlimited'
                ];
            }
        } elseif (file_exists('/sys/fs/cgroup/memory/memory.usage_in_bytes')) {
            $memBytes = (int)trim((string)@file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes'));
            $limitBytes = (int)trim((string)@file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes'));
            if ($memBytes > 0) {
                $containerMem = [
                    'used_mb' => round($memBytes / (1024 * 1024), 2),
                    'limit_mb' => ($limitBytes > 0 && $limitBytes < 9000000000000000000) ? round($limitBytes / (1024 * 1024), 2) : 'unlimited'
                ];
            }
        }

        // Docker Container Stats (Nginx, PHP, Redis, MySQL)
        $containers = [];
        $envFile = Config::$DIR_BACKEND . '/.env';
        if (file_exists($envFile)) {
            $dockerPs = @shell_exec('docker compose --env-file ' . escapeshellarg($envFile) . ' ps -q 2>/dev/null');
            if (!empty($dockerPs)) {
                $ids = array_filter(explode("\n", trim($dockerPs)));
                if (!empty($ids)) {
                    $statsCmd = 'docker stats --no-stream --format "{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}" ' . implode(' ', array_map('escapeshellarg', $ids)) . ' 2>/dev/null';
                    $rawStats = @shell_exec($statsCmd);
                    if (!empty($rawStats)) {
                        foreach (explode("\n", trim($rawStats)) as $line) {
                            $parts = explode('|', trim($line));
                            if (count($parts) >= 3) {
                                $containers[] = [
                                    'name' => $parts[0],
                                    'cpu' => $parts[1],
                                    'mem' => $parts[2]
                                ];
                            }
                        }
                    }
                }
            }
        }

        $systemHealth = [
            'is_container' => file_exists('/.dockerenv') || $containerMem !== null || !empty($containers),
            'container_memory' => $containerMem,
            'containers' => $containers,
            'cpu_load_1m' => $cpuLoad[0] ?? 0,
            'cpu_load_5m' => $cpuLoad[1] ?? 0,
            'cpu_load_15m' => $cpuLoad[2] ?? 0,
            'disk_free_gb' => $diskFree !== false ? round($diskFree / (1024 * 1024 * 1024), 2) : 'N/A',
            'disk_total_gb' => $diskTotal !== false ? round($diskTotal / (1024 * 1024 * 1024), 2) : 'N/A',
            'disk_used_percent' => $diskUsedPercent
        ];

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'debug_logging_enabled' => Config::$DEBUG_LOGGING_ENABLED,
            'cache_driver' => Config::$CACHE_DRIVER,
            'redis' => $redisHealth,
            'database' => $dbHealth,
            'php' => $phpHealth,
            'system' => $systemHealth
        ];
    }

    /**
     * Recursively scans `backend/routes/` and `frontend/` to list all testable API routes and pages.
     * 
     * @return array<int, array<string, string>>
     */
    public static function getAvailableRoutes(): array
    {
        $routes = [];

        $routesDir = Config::$DIR_BACKEND . '/routes';
        if (is_dir($routesDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($routesDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($routesDir, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    $relativePath = preg_replace('/\.php$/', '', $relativePath);

                    if (str_ends_with($relativePath, '/index')) {
                        $relativePath = substr($relativePath, 0, -6);
                    }
                    if ($relativePath === '/index' || $relativePath === '') {
                        $path = '/api/';
                    } else {
                        $path = '/api' . $relativePath;
                    }

                    if (str_starts_with($path, '/api/debug')) {
                        continue;
                    }

                    $routes[] = [
                        'type' => 'api',
                        'path' => $path,
                        'name' => 'API Route: ' . $path
                    ];
                }
            }
        }

        $frontendDir = Config::$DIR_FRONTEND;
        if (is_dir($frontendDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($frontendDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'html') {
                    $relativePath = str_replace($frontendDir, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);

                    if ($relativePath === '/index.html') {
                        $path = '/';
                    } else {
                        $path = $relativePath;
                    }

                    if ($path === '/debug.html') {
                        continue;
                    }

                    $routes[] = [
                        'type' => 'frontend',
                        'path' => $path,
                        'name' => 'Page: ' . $path
                    ];
                }
            }
        }

        $unique = [];
        foreach ($routes as $r) {
            $unique[$r['path']] = $r;
        }

        ksort($unique);
        return array_values($unique);
    }

    /**
     * Clears Redis debug request logs.
     * 
     * @return bool
     */
    public static function clearLogs(): bool
    {
        try {
            $redis = Cache::getRedis();
            if ($redis) {
                $redis->del(self::REDIS_KEY_LOGS);
                return true;
            }
        } catch (\Throwable $e) {

        }
        return false;
    }

    /**
     * Purges all dual-tier caches (APCu and Redis).
     * 
     * @return bool
     */
    public static function purgeCache(): bool
    {
        try {
            Cache::clear();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Deletes a specific cache key from all cache tiers.
     * 
     * @param string $key
     * @return bool
     */
    public static function clearKey(string $key): bool
    {
        try {
            Cache::delete($key);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
