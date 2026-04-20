<?php

namespace Cli;

use Core\Config as CoreConfig;

/**
 * Route Cache Command
 * 
 * Manages the on-the-fly Dependency Injection and routing cache.
 * 
 * @package Cli
 */
class RouteCache extends Command
{
    /**
     * Execute the cache command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $command = $args[0] ?? 'clear';

        if ($command === 'clear') {
            $this->clear();
        } else {
            $this->error("Unknown command. Usage: php app/cli.php route:cache [clear]");
        }
    }

    /**
     * Clear the route DI cache file
     * 
     * @return void
     */
    public function clear(): void
    {
        CoreConfig::load();
        $cacheFile = CoreConfig::$DIR_PROJECT . '/lila/route_di_cache.php';

        if (file_exists($cacheFile)) {
            if (unlink($cacheFile)) {
                $this->success("Route DI cache cleared successfully.");
            } else {
                $this->error("Failed to clear route DI cache. Check permissions for: {$cacheFile}");
            }
        } else {
            $this->info("No route DI cache found. Cache is generated automatically in production mode (DEBUG=false) on the fly.");
        }
    }
}
