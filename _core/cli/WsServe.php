<?php

namespace Cli;

use Core\Ws;

/**
 * CLI WebSocket Server Command (`php cli.php ws:serve` or `ws:start`).
 * 
 * Boots the Workerman WebSocket server listening on port 8001 connected to Redis Pub/Sub.
 * 
 * @package Cli
 */
class WsServe extends Command
{
    /**
     * Executes the WebSocket server.
     * 
     * @param array $args Command arguments (optional port number)
     * @return int
     */
    public function run(array $args): int
    {
        $port = isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : 8001;

        $this->banner("LilaPHP Real-Time WebSocket Engine (Workerman)");
        $this->info("Starting WebSocket server on port {$port}...");

        Ws::serve($port);
        return 0;
    }
}
