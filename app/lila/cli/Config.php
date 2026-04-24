<?php

namespace Cli;

use Core\Config as CoreConfig;
use Dotenv\Dotenv;

/**
 * Configuration Command
 * 
 * Handles environment variable caching for performance optimization.
 * 
 * @package Cli
 */
class Config extends Command
{
    /**
     * Execute configuration command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->cache($args);
    }

    /**
     * Generate environment cache
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function cache(array $args): void
    {
        $this->info("Generating environment cache...");

        $dir = CoreConfig::$DIR_PROJECT;
        $envPath = $dir . '/.env';

        if (!file_exists($envPath)) {
            $this->error(".env file not found at {$envPath}");
            return;
        }

        try {
            $dotenv = Dotenv::createMutable($dir);
            $envVars = $dotenv->load();

            $cacheFile = $dir . '/lila/env_cache.php';
            if (CoreConfig::saveCache($cacheFile, $envVars)) {
                $this->success("Configuration cached successfully at app/lila/env_cache.php");
                $this->info("Opcache will now handle configuration loading for better performance.");
            } else {
                $this->error("Failed to write cache file. Ensure app/lila/ is writable.");
            }
        } catch (\Throwable $e) {
            $this->error("Error caching configuration: " . $e->getMessage());
        }
    }

    /**
     * Clear environment cache
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function clear(array $args): void
    {
        $this->info("Clearing application cache...");
        try {
            CoreConfig::deleteCache(CoreConfig::$DIR_PROJECT);
            $this->success("All application caches cleared successfully (env, routes, manifest, and twig cache).");
        } catch (\Throwable $e) {
            $this->error("Failed to clear cache: " . $e->getMessage());
        }
    }
}
