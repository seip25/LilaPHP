<?php

namespace Cli;

use Core\Config;
use Core\Cache;

/**
 * Production Performance Optimization Command (`php cli.php optimize`).
 * 
 * Compiles environment configurations into static PHP arrays (`_core/cache/env.php`),
 * flushes stale cache entries, and resets OPcache memory pools.
 * 
 * @package Cli
 */
class Optimize extends Command
{
    /**
     * Runs configuration compilation and cache clearing.
     * 
     * @param array $args Command arguments
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Production Optimizer");

        $this->info("Compiling environment configurations to OPcache-ready static file...");
        if (Config::cache()) {
            $this->success("Configuration cached directly to `_core/cache/env.php`.");
        } else {
            $this->error("Failed to write `_core/cache/env.php`.");
            return 1;
        }

        $this->info("Clearing memory and persistent caches...");
        Cache::clear();
        $this->success("Dual-tier caches (APCu + Redis fallback) flushed successfully.");

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $this->success("OPcache memory pool re-compiled.");
        }

        $this->success("System optimized for maximum concurrency.");
        return 0;
    }
}
