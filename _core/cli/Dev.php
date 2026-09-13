<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;

/**
 * Local Development Server with Hot Reload (`php cli.php dev`).
 * 
 * Boots the PHP built-in web server configured with front controller routing
 * and real-time live browser refresh on file changes.
 * 
 * @package Cli
 */
class Dev extends Command
{
    /**
     * Executes the development server.
     * 
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        $port = (int)($args[0] ?? Config::$HTTP_PORT);
        if ($port <= 0) {
            $port = 8080;
        }

        $this->banner("LilaPHP Development Server (with Hot Reload)");

        if (Doctor::isPortOccupied($port)) {
            $this->warning("Port {$port} appears to be occupied on host. Attempting to bind anyway...");
        }

        $host = "0.0.0.0:{$port}";
        $rootIndex = dirname(__DIR__, 2) . '/index.php';

        echo PHP_EOL;
        $this->info("🚀 Development server starting...");
        echo "  - Local Web URL : \033[36mhttp://localhost:{$port}/\033[0m" . PHP_EOL;
        echo "  - REST API JSON : \033[36mhttp://localhost:{$port}/api/\033[0m" . PHP_EOL;
        echo "  - System Health : \033[36mhttp://localhost:{$port}/api/health\033[0m" . PHP_EOL;
        echo "  - Hot Reload    : \033[32mActive\033[0m (watching app/views/, app/routes/, public/)" . PHP_EOL;
        echo PHP_EOL;
        echo "\033[2mPress Ctrl+C to stop the server.\033[0m" . PHP_EOL . PHP_EOL;

        $cmd = escapeshellarg(PHP_BINARY) . " -S {$host} " . escapeshellarg($rootIndex);
        passthru($cmd, $exitCode);

        return $exitCode;
    }
}
