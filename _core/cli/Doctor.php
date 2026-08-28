<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;
use Core\Database;
use Core\Cache;

/**
 * System Doctor & Environment Pre-Flight Diagnostic Engine (`php cli.php doctor`).
 * 
 * Verifies system requirements, essential PHP extensions, dynamic .env port bindings,
 * cryptographic keys, directory write permissions (frontend, cache, logs, db), and live services.
 * 
 * @package Cli
 */
class Doctor extends Command
{
    /**
     * Executes the comprehensive environment doctor.
     * 
     * @param array $args
     * @return int 0 if all checks pass, 1 if warnings/errors found
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP System Doctor & Pre-Flight Diagnostics");
        echo PHP_EOL;

        $issues = 0;
        $warnings = 0;

        // 1. PHP Runtime
        $this->info("1. PHP Runtime Environment");
        $phpVer = PHP_VERSION;
        if (version_compare($phpVer, '8.2.0', '>=')) {
            $this->success("  ✔ PHP Version: {$phpVer} (Supported)");
        } else {
            $this->error("  ✖ PHP Version: {$phpVer} (LilaPHP requires PHP >= 8.2)");
            $issues++;
        }

        // 2. Essential & Recommended Extensions
        $this->info(PHP_EOL . "2. Core & Extension Dependencies");
        $requiredExtensions = [
            'pdo' => 'Database abstraction layer',
            'curl' => 'HTTP & AI Client (Core\Http & Core\AI)',
            'openssl' => 'AES-256-GCM Security & Cryptography',
            'mbstring' => 'Multibyte string sanitization',
            'json' => 'REST API JSON encoder/decoder',
            'filter' => 'Data input sanitization engine'
        ];

        foreach ($requiredExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->success("  ✔ Extension [{$ext}] active ({$purpose})");
            } else {
                $this->error("  ✖ Missing required extension: {$ext} ({$purpose})");
                $issues++;
            }
        }

        $optionalExtensions = [
            'pdo_mysql' => 'MySQL / MariaDB database driver',
            'pdo_sqlite' => 'SQLite database driver (WAL mode)',
            'apcu' => 'Zero-latency shared worker RAM cache',
            'redis' => 'Distributed Redis cache & background job queues',
            'opcache' => 'In-memory bytecode acceleration & preloading'
        ];

        foreach ($optionalExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->success("  ✔ Accelerator [{$ext}] active ({$purpose})");
            } else {
                $this->warning("  ⚠️  Optional extension [{$ext}] not loaded ({$purpose})");
                $warnings++;
            }
        }

        // 3. Dynamic .env Port Availability Pre-Flight Check (Multi-VPS Safe)
        $this->info(PHP_EOL . "3. Network & Dynamic Port Diagnostics (from backend/.env)");
        
        $envHttpPort = Config::$HTTP_PORT;
        $envProdPort = Config::$PROD_HTTP_PORT;
        $envDbPort   = Config::$DB_PORT;
        $envRedisPort = Config::$REDIS_PORT;
        $envWsPort   = (int) (getenv('WS_PORT') ?: 8001);

        $portsToCheck = [
            "Dev Web Server (`HTTP_PORT` = {$envHttpPort})"  => ['port' => $envHttpPort, 'host' => '127.0.0.1'],
            "Prod Web Server (`PROD_HTTP_PORT` = {$envProdPort})" => ['port' => $envProdPort, 'host' => '127.0.0.1'],
            "MySQL Database (`DB_PORT` = {$envDbPort})"     => ['port' => $envDbPort, 'host' => '127.0.0.1'],
            "Redis Cache (`REDIS_PORT` = {$envRedisPort})"  => ['port' => $envRedisPort, 'host' => '127.0.0.1'],
            "WebSocket Server (`WS_PORT` = {$envWsPort})"   => ['port' => $envWsPort, 'host' => '127.0.0.1']
        ];

        foreach ($portsToCheck as $label => $cfg) {
            $inUse = self::isPortOccupied($cfg['port'], $cfg['host']);
            if ($inUse) {
                $this->warning("  ⚠️  Port {$cfg['port']} is currently bound/active on host ({$label}).");
            } else {
                $this->success("  ✔ Port {$cfg['port']} is available on host ({$label}).");
            }
        }

        // 4. Complete Filesystem & Directory Permissions Check
        $this->info(PHP_EOL . "4. Filesystem, Assets & Permission Diagnostics");

        // Check backend/.env
        $envPath = Config::$DIR_BACKEND . '/.env';
        if (file_exists($envPath)) {
            if (is_readable($envPath)) {
                $this->success("  ✔ Environment file `backend/.env` exists and is readable.");
            } else {
                $this->error("  ✖ `backend/.env` is NOT readable by PHP. Check permissions (chmod 640).");
                $issues++;
            }
        } else {
            $this->error("  ✖ Missing `backend/.env` file! Copy from `backend/.env_example`.");
            $issues++;
        }

        // Check Frontend Files & Permissions
        $frontendChecks = [
            'Frontend Root Directory' => Config::$DIR_FRONTEND,
            'Frontend Index SPA (`frontend/index.html`)' => Config::$DIR_FRONTEND . '/index.html',
            'Frontend Dashboard (`frontend/dashboard.html`)' => Config::$DIR_FRONTEND . '/dashboard.html',
            'Frontend About (`frontend/about.html`)' => Config::$DIR_FRONTEND . '/about.html',
            'Bluebird CSS (`frontend/css/bluebird.css`)' => Config::$DIR_FRONTEND . '/css/bluebird.css',
            'Lila.js Engine (`frontend/js/lila.js`)' => Config::$DIR_FRONTEND . '/js/lila.js',
            'Lila TypeScript Source (`frontend/js/lila.ts`)' => Config::$DIR_FRONTEND . '/js/lila.ts',
            'Lila TypeScript Types (`frontend/js/lila.d.ts`)' => Config::$DIR_FRONTEND . '/js/lila.d.ts',
        ];

        foreach ($frontendChecks as $label => $path) {
            if (file_exists($path)) {
                if (is_readable($path)) {
                    $this->success("  ✔ {$label} is accessible.");
                } else {
                    $this->error("  ✖ {$label} is NOT readable (Check chmod permissions).");
                    $issues++;
                }
            } else {
                $this->warning("  ⚠️  {$label} not found at `{$path}`.");
                $warnings++;
            }
        }

        // Check Writable Directories
        $writableDirs = [
            'Core Cache Directory'    => Config::$DIR_CORE . '/cache',
            'Core Logs Directory'     => Config::$DIR_CORE . '/logs',
            'Database Storage (SQLite)' => Config::$DIR_BACKEND . '/database',
        ];

        foreach ($writableDirs as $label => $path) {
            if (!file_exists($path)) {
                @mkdir($path, 0777, true);
            }
            if (is_writable($path)) {
                $this->success("  ✔ Directory `{$label}` is writable ({$path})");
            } else {
                $this->error("  ✖ Directory `{$label}` is NOT writable ({$path}). Run chmod 775/777.");
                $issues++;
            }
        }

        // Check Backend Core Directories
        $backendDirs = [
            'Backend Routes (`backend/routes/`)' => Config::$DIR_BACKEND . '/routes',
            'Backend Models (`backend/models/`)' => Config::$DIR_BACKEND . '/models',
        ];

        foreach ($backendDirs as $label => $path) {
            if (is_dir($path) && is_readable($path)) {
                $this->success("  ✔ {$label} is readable.");
            } else {
                $this->warning("  ⚠️  {$label} is not accessible.");
                $warnings++;
            }
        }

        // 5. Security & Cryptographic Key
        $this->info(PHP_EOL . "5. Security & Encryption Key");
        if (!empty(Config::$APP_KEY)) {
            $this->success("  ✔ APP_KEY configured properly.");
        } else {
            $this->error("  ✖ APP_KEY is missing in backend/.env. Run `php cli.php key:generate`.");
            $issues++;
        }

        // 6. Universal AI Engine Setup
        $this->info(PHP_EOL . "6. Universal AI Engine Setup");
        $this->info("  - Default AI Provider: " . Config::$AI_PROVIDER);
        $resolvedKey = \Core\AI::resolveKey(Config::$AI_PROVIDER);
        if (!empty($resolvedKey)) {
            $maskedKey = substr($resolvedKey, 0, 4) . '...' . substr($resolvedKey, -4);
            $this->success("  ✔ API Key detected for `" . Config::$AI_PROVIDER . "` ({$maskedKey})");
        } else {
            $this->warning("  ⚠️  No API key configured for `" . Config::$AI_PROVIDER . "` (Set `API_IA_KEY` or `DEEPSEEK_API_KEY` / `GEMINI_API_KEY` in .env if using AI).");
        }

        // 7. Live Database & Cache Ping
        $this->info(PHP_EOL . "7. Live Connectivity Checks");
        try {
            $pdo = Database::getInstance();
            if ($pdo) {
                $pdo->query('SELECT 1');
                $this->success("  ✔ Database connection: OK (Driver: " . Config::$DB_TYPE . ")");
            } else {
                $this->warning("  ⚠️  Database instance returned null.");
            }
        } catch (\Throwable $e) {
            $this->warning("  ⚠️  Database connection unavailable: " . $e->getMessage());
        }

        $redis = Cache::getRedis();
        if ($redis) {
            try {
                if ($redis->ping()) {
                    $this->success("  ✔ Redis cluster connection: OK (" . Config::$REDIS_HOST . ":" . Config::$REDIS_PORT . ")");
                }
            } catch (\Throwable $e) {
                $this->warning("  ⚠️  Redis ping failed: " . $e->getMessage());
            }
        } else {
            $this->warning("  ⚠️  Redis offline or unconfigured.");
        }

        // Summary
        echo PHP_EOL;
        $this->banner("Doctor Diagnostic Summary");
        if ($issues === 0 && $warnings === 0) {
            $this->success("  🌟 PERFECT HEALTH: LilaPHP is 100% configured and production ready!");
            return 0;
        } elseif ($issues === 0) {
            $this->success("  ✔ STABLE: All core requirements passed ({$warnings} non-critical warnings).");
            return 0;
        } else {
            $this->error("  ✖ ATTENTION REQUIRED: Found {$issues} critical issue(s) and {$warnings} warning(s).");
            return 1;
        }
    }

    /**
     * Checks whether a TCP port is occupied on the local host.
     * 
     * @param int $port
     * @param string $host
     * @return bool True if occupied, False if free
     */
    public static function isPortOccupied(int $port, string $host = '127.0.0.1'): bool
    {
        if ($port <= 0 || $port > 65535) {
            return false;
        }
        $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }
}
