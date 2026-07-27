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
            'stop', 'down' => $this->execShell('docker compose --env-file ./backend/.env down'),
            'ps', 'status' => $this->showStatus(),
            'logs' => $this->execShell('docker compose --env-file ./backend/.env logs -f --tail=100'),
            'exec-php' => $this->execShell('docker compose --env-file ./backend/.env exec php bash'),
            'exec-mysql', 'mysql' => $this->execMysql($args),
            'clean' => $this->cleanCluster(),
            default => $this->printUsage()
        };
    }

    /**
     * Executes SQL queries or opens an interactive MySQL console inside the MySQL container.
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    private function execMysql(array $args): int
    {
        array_shift($args);
        $dbUser = Config::$DB_USER;
        $dbPass = Config::$DB_PASSWORD;
        $dbName = Config::$DB_NAME;

        $ttyFlag = (function_exists('posix_isatty') && posix_isatty(STDOUT)) ? '' : '-T ';

        if (!empty($args)) {
            $query = implode(' ', $args);
            $this->info("Executing query on MySQL database `{$dbName}`...");
            if (!str_starts_with(trim($query), '-')) {
                $cmd = "docker compose --env-file ./backend/.env exec -e MYSQL_PWD=" . escapeshellarg($dbPass) . " {$ttyFlag}mysql mysql -u{$dbUser} {$dbName} -e " . escapeshellarg($query);
            } else {
                $cmd = "docker compose --env-file ./backend/.env exec -e MYSQL_PWD=" . escapeshellarg($dbPass) . " {$ttyFlag}mysql mysql -u{$dbUser} {$dbName} {$query}";
            }
            return $this->execShell($cmd);
        }

        $this->info("Opening interactive MySQL console for database `{$dbName}`...");
        $cmd = "docker compose --env-file ./backend/.env exec -e MYSQL_PWD=" . escapeshellarg($dbPass) . " {$ttyFlag}mysql mysql -u{$dbUser} {$dbName}";
        return $this->execShell($cmd);
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
        $cmd = "docker compose --env-file ./backend/.env up -d --build";
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
        $status = $this->execShell('docker compose --env-file ./backend/.env ps');

        echo PHP_EOL;
        $this->info("🌐 Live Endpoints:");
        echo "  - Landing Dashboard: http://localhost:" . Config::$HTTP_PORT . "/" . PHP_EOL;
        echo "  - API Status JSON  : http://localhost:" . Config::$HTTP_PORT . "/api/" . PHP_EOL;
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
        return $this->execShell('docker compose --env-file ./backend/.env down -v --remove-orphans');
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
        echo "  php cli.php docker dev                      # Launch in Development Mode" . PHP_EOL;
        echo "  php cli.php docker prod                     # Launch in Production Mode" . PHP_EOL;
        echo "  php cli.php docker stop                     # Stop cluster containers" . PHP_EOL;
        echo "  php cli.php docker ps                       # Show running containers and ports" . PHP_EOL;
        echo "  php cli.php docker logs                     # Tail real-time cluster logs" . PHP_EOL;
        echo "  php cli.php docker exec-php                 # Open interactive bash inside PHP container" . PHP_EOL;
        echo "  php cli.php docker exec-mysql [query]       # Open interactive MySQL CLI or execute SQL query" . PHP_EOL;
        echo "  php cli.php docker clean                    # Destroy containers and reset volumes" . PHP_EOL;
        return 1;
    }
}
