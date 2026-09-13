<?php

namespace Cli;

use Core\Config;
use Core\Database;
use Core\Cache;

/**
 * System Health Diagnostic Command (`php cli.php health`).
 * 
 * Verifies MySQL connection, APCu RAM cache, Redis cluster, OPcache preload,
 * and disk space in microsecond precision.
 * 
 * @package Cli
 */
class Health extends Command
{
    /**
     * Executes the system health check.
     * 
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        $start = microtime(true);
        $this->info("Running LilaPHP System Health Diagnostics...");
        echo PHP_EOL;

        $allOk = true;
        try {
            $pdo = Database::getInstance();
            if ($pdo !== null) {
                $stmt = $pdo->query("SELECT 1");
                if ($stmt && $stmt->fetchColumn() == 1) {
                    $this->success("[MySQL DB] Connected to " . Config::$DB_HOST . ":" . Config::$DB_PORT . " (`" . Config::$DB_NAME . "`)");
                } else {
                    $this->error("[MySQL DB] Query verification failed.");
                    $allOk = false;
                }
            } else {
                $this->error("[MySQL DB] Disconnected or unconfigured.");
                $allOk = false;
            }
        } catch (\Throwable $e) {
            $this->error("[MySQL DB] Connection error: " . $e->getMessage());
            $allOk = false;
        }

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $this->success("[APCu RAM] Shared worker RAM cache active.");
        } else {
            $this->warning("[APCu RAM] APCu extension inactive (Fallback mode).");
        }

        $redis = Cache::getRedis();
        if ($redis !== null) {
            try {
                $ping = $redis->ping();
                if ($ping) {
                    $this->success("[Redis Cluster] Connected on " . Config::$REDIS_HOST . ":" . Config::$REDIS_PORT);
                } else {
                    $this->warning("[Redis Cluster] Ping returned unexpected response.");
                }
            } catch (\Throwable $e) {
                $this->warning("[Redis Cluster] Connection error: " . $e->getMessage());
            }
        } else {
            $this->warning("[Redis Cluster] Disconnected or unconfigured.");
        }

        if (function_exists('opcache_get_status')) {
            $opStats = @opcache_get_status(false);
            if ($opStats && ($opStats['opcache_enabled'] ?? false)) {
                $this->success("[OPcache] Preload RAM memory active.");
            } else {
                $this->warning("[OPcache] OPcache disabled or inactive.");
            }
        } else {
            $this->warning("[OPcache] Extension unconfigured.");
        }

        $freeMb = round(disk_free_space(Config::$DIR_PROJECT) / 1024 / 1024, 2);
        $this->info("[Disk Storage] Free space available: {$freeMb} MB");

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        echo PHP_EOL;

        if ($allOk) {
            $this->success("Overall Health Status: OK (Checked in {$elapsed} ms)");
            return 0;
        }

        if (!file_exists('/.dockerenv') && (Config::$DB_HOST === 'mysql' || Config::$REDIS_HOST === 'redis')) {
            $this->info("💡 Tip: You are running `php cli.php health` from the host machine.");
            $this->info("   Container names (`mysql`, `redis`) resolve automatically inside the Docker network.");
            $this->info("   - Run inside Docker container: docker compose exec php php cli.php health");
            $this->info("   - Or for host CLI access: set DB_HOST=127.0.0.1 and REDIS_HOST=127.0.0.1 in .env");
            echo PHP_EOL;
        }

        $this->error("Health Check finished with warnings (Checked in {$elapsed} ms)");
        return 1;
    }
}
