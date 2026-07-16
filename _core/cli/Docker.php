<?php

namespace Cli;

use Core\Config;

/**
 * Blue-bird style Docker Orchestration Engine (`php cli.php docker [action]`).
 * 
 * Manages multi-container clusters (`nginx`, `php-fpm`, `mysql`, `redis`) with dynamic
 * port bindings (`HTTP_PORT`, `DB_PORT`, `REDIS_PORT`) and dedicated `APP_ENV` profiles.
 * 
 * @package Cli
 */
class Docker extends Command
{
    /**
     * Executes Docker cluster management workflows.
     * 
     * @param array $args CLI arguments list (`dev`, `prod`, `stop`, `logs`, `ps`)
     * @return int
     */
    public function run(array $args): int
    {
        $action = strtolower($args[0] ?? 'ps');
        $this->banner("LilaPHP Docker Cluster Orchestration ({$action})");

        return match ($action) {
            'dev' => $this->launchCluster('development'),
            'prod', 'production' => $this->launchCluster('production'),
            'stop', 'down' => $this->execShell('docker compose down'),
            'ps', 'status' => $this->showStatus(),
            'logs' => $this->execShell('docker compose logs -f --tail=100'),
            'exec-php' => $this->execShell('docker compose exec php bash'),
            'exec-mysql' => $this->execShell('docker compose exec mysql mysql -u' . Config::$DB_USER . ' -p' . Config::$DB_PASSWORD . ' ' . Config::$DB_NAME),
            'clean' => $this->cleanCluster(),
            default => $this->printUsage()
        };
    }

    /**
     * Boots the Docker cluster under a specific environment mode.
     * 
     * @param string $env Target environment ('development' or 'production')
     * @return int
     */
    private function launchCluster(string $env): int
    {
        $shortEnv = $env === 'production' ? 'prod' : 'dev';
        $this->info("Starting cluster in `{$env}` mode using `docker/php/Dockerfile.{$shortEnv}`...");
        $this->info("Dynamic Ports Assigned: HTTP=" . Config::$HTTP_PORT . " | MySQL=" . Config::$DB_PORT . " | Redis=" . Config::$REDIS_PORT);

        putenv("APP_ENV={$shortEnv}");
        $cmd = "docker compose up -d --build";
        $status = $this->execShell($cmd);

        if ($status === 0) {
            $this->success("Cluster deployed successfully.");
            $this->showStatus();
        }

        return $status;
    }

    /**
     * Displays running containers and port mappings.
     * 
     * @return int
     */
    private function showStatus(): int
    {
        $this->info("Current Cluster Status (`docker compose ps`):");
        $status = $this->execShell('docker compose ps');

        echo PHP_EOL;
        $this->info("🌐 Live Endpoints:");
        echo "  - Landing Dashboard: http://localhost:" . Config::$HTTP_PORT . "/" . PHP_EOL;
        echo "  - API Status JSON  : http://localhost:" . Config::$HTTP_PORT . "/api/" . PHP_EOL;
        echo "  - API Diagnostics  : http://localhost:" . Config::$HTTP_PORT . "/api/test" . PHP_EOL;
        echo PHP_EOL;

        return $status;
    }

    /**
     * Completely removes containers, volumes, and dangling images.
     * 
     * @return int
     */
    private function cleanCluster(): int
    {
        $this->warning("Stopping and cleaning all cluster containers and volumes...");
        return $this->execShell('docker compose down -v --remove-orphans');
    }

    /**
     * Displays usage instructions for the `docker` command.
     * 
     * @return int
     */
    private function printUsage(): int
    {
        $this->error("Unknown docker action.");
        echo "Available Actions:" . PHP_EOL;
        echo "  php cli.php docker dev         # Launch in Development Mode" . PHP_EOL;
        echo "  php cli.php docker prod        # Launch in Production Mode (Preload + Multi-worker)" . PHP_EOL;
        echo "  php cli.php docker stop        # Stop cluster containers" . PHP_EOL;
        echo "  php cli.php docker ps          # Show running containers and port links" . PHP_EOL;
        echo "  php cli.php docker logs        # Tail real-time cluster logs" . PHP_EOL;
        echo "  php cli.php docker exec-php    # Open interactive bash inside PHP worker" . PHP_EOL;
        echo "  php cli.php docker exec-mysql  # Open interactive MySQL CLI inside DB container" . PHP_EOL;
        echo "  php cli.php docker clean       # Destroy containers and reset volumes" . PHP_EOL;
        return 1;
    }
}
