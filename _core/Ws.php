<?php

namespace Core;

/**
 * Real-Time WebSockets Engine & Redis Pub/Sub Bridge (`Core\Ws`).
 * 
 * Provides Socket.IO style real-time event broadcasting and room management
 * powered by Workerman (`workerman/workerman`) and dual-tier Redis Pub/Sub.
 * 
 * @package Core
 */
class Ws
{
    private static string $channelKey = 'lilaphp:ws_channel';

    /**
     * Broadcasts an event to connected WebSocket clients from any PHP-FPM API route via Redis.
     * 
     * @param string      $event Event name (e.g., 'item_created', 'chat_message')
     * @param mixed       $data  Payload data
     * @param string|null $room  Optional room name to target specific subscribed clients
     * @return bool True if published to Redis channel successfully
     * @example \Core\Ws::publish('order_placed', ['id' => 104], 'orders_room');
     */
    public static function publish(string $event, mixed $data = [], ?string $room = null): bool
    {
        $redis = Cache::getRedis();
        if ($redis === null) {
            Logger::warning("Ws::publish failed: Redis cluster not connected.");
            return false;
        }

        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'room' => $room,
            'timestamp' => microtime(true)
        ], JSON_UNESCAPED_UNICODE);

        try {
            $redis->publish(self::$channelKey, $payload);
            return true;
        } catch (\Throwable $e) {
            Logger::error("Redis publish error in Ws::publish: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Boots the Workerman WebSocket server listening on the specified port (`php cli.php ws:serve`).
     * 
     * @param int $port Port to listen on (default: 8001)
     * @return void
     */
    public static function serve(int $port = 8001): void
    {
        if (!class_exists(\Workerman\Worker::class)) {
            echo "\n [ERROR] Workerman library is not installed.\n";
            echo "Please install it via Composer inside the container or host:\n";
            echo "   composer require workerman/workerman\n\n";
            return;
        }

        if (!extension_loaded('pcntl') && DIRECTORY_SEPARATOR !== '\\') {
            echo "\n [ERROR] PHP `pcntl` extension is not enabled in this container/system.\n";
            echo "To install and enable `pcntl` instantly in the running Docker container, run:\n";
            echo "   docker compose --env-file ./backend/.env exec php docker-php-ext-install pcntl posix\n";
            echo "Or rebuild your containers (`docker compose build`) as `pcntl` and `posix` are now pre-configured in our Dockerfiles.\n\n";
            return;
        }

        echo "⚡ Booting LilaPHP Workerman WebSocket Server on port {$port}...\n";

        $wsWorker = new \Workerman\Worker("websocket://0.0.0.0:{$port}");
        $wsWorker->count = 1;
        $wsWorker->name = 'LilaPHP-WebSocket-Engine';

        $wsWorker->onConnect = function ($connection) {
            $connection->rooms = [];
            if (class_exists(\Sockets\Handler::class) && method_exists(\Sockets\Handler::class, 'onConnect')) {
                \Sockets\Handler::onConnect($connection);
            }
        };

        $wsWorker->onMessage = function ($connection, $data) use ($wsWorker) {
            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                return;
            }

            $action = $decoded['action'] ?? $decoded['event'] ?? '';
            $room = $decoded['room'] ?? null;
            $payload = $decoded['data'] ?? [];

            if ($action === 'ping' || $action === 'heartbeat') {
                $connection->send(json_encode(['event' => 'pong', 'timestamp' => time()]));
                return;
            }

            if ($action === 'join' && !empty($room)) {
                if (class_exists(\Sockets\Handler::class) && method_exists(\Sockets\Handler::class, 'onJoin')) {
                    if (\Sockets\Handler::onJoin($connection, $room, $payload) === false) {
                        return;
                    }
                }
                $connection->rooms[$room] = true;
                $connection->send(json_encode([
                    'event' => 'joined_room',
                    'room' => $room,
                    'socket_id' => $connection->socket_id ?? $connection->id
                ]));
                return;
            }

            if ($action === 'leave' && !empty($room)) {
                unset($connection->rooms[$room]);
                $connection->send(json_encode([
                    'event' => 'left_room',
                    'room' => $room
                ]));
                return;
            }

            if ($action === 'emit' || $action === 'broadcast' || $action === 'broadcastAll') {
                $event = $decoded['event'] ?? 'message';
                if (class_exists(\Sockets\Handler::class) && method_exists(\Sockets\Handler::class, 'onMessage')) {
                    if (\Sockets\Handler::onMessage($connection, $event, $payload, $room, $wsWorker) === false) {
                        return;
                    }
                }
                $excludeId = ($action === 'broadcastAll') ? null : ($connection->id ?? null);
                self::broadcastToConnections($wsWorker, $event, $payload, $room, $excludeId);
                return;
            }
            if (class_exists(\Sockets\Handler::class) && method_exists(\Sockets\Handler::class, 'onEvent')) {
                \Sockets\Handler::onEvent($connection, $action, $payload, $room, $wsWorker);
            }
        };

        $wsWorker->onWorkerStart = function ($worker) {
            echo "WebSocket Worker started. Connecting to Redis Pub/Sub (`" . self::$channelKey . "`)...\n";

            if (class_exists(\Workerman\Timer::class)) {
                \Workerman\Timer::add(0.05, function () use ($worker) {
                    static $redisSub = null;
                    if ($redisSub === null) {
                        try {
                            $host = Config::$REDIS_HOST !== '' ? Config::$REDIS_HOST : 'redis';
                            $port = Config::$REDIS_PORT > 0 ? Config::$REDIS_PORT : 6379;
                            $redisSub = new \Redis();
                            @$redisSub->connect($host, $port, 1.0);
                            if (Config::$REDIS_PASSWORD !== '') {
                                @$redisSub->auth(Config::$REDIS_PASSWORD);
                            }
                            $redisSub->setOption(\Redis::OPT_READ_TIMEOUT, 0.05);
                        } catch (\Throwable $e) {
                            $redisSub = null;
                            return;
                        }
                    }

                    try {
                        @$redisSub->subscribe([self::$channelKey], function ($redis, $channel, $msg) use ($worker) {
                            $decoded = json_decode($msg, true);
                            if (is_array($decoded)) {
                                $event = $decoded['event'] ?? 'notification';
                                $data = $decoded['data'] ?? [];
                                $room = $decoded['room'] ?? null;
                                self::broadcastToConnections($worker, $event, $data, $room);
                            }
                        });
                    } catch (\Throwable $e) {
                    }
                });
            }
        };

        $wsWorker->onClose = function ($connection) {
            if (class_exists(\Sockets\Handler::class) && method_exists(\Sockets\Handler::class, 'onClose')) {
                \Sockets\Handler::onClose($connection);
            }
            $connection->rooms = [];
        };

        $workermanAction = 'start';
        foreach (['stop', 'restart', 'reload', 'status', 'connections'] as $act) {
            if (in_array($act, $_SERVER['argv'] ?? [], true)) {
                $workermanAction = $act;
                break;
            }
        }
        $workermanArgv = [$_SERVER['argv'][0] ?? 'cli.php', $workermanAction];
        if (in_array('-d', $_SERVER['argv'] ?? [], true) || in_array('--daemon', $_SERVER['argv'] ?? [], true)) {
            $workermanArgv[] = '-d';
        }
        $GLOBALS['argv'] = $workermanArgv;
        $_SERVER['argv'] = $workermanArgv;
        $_SERVER['argc'] = count($workermanArgv);

        \Workerman\Worker::runAll();
    }

    /**
     * Helper to broadcast JSON payload to matching sockets (`Workerman\Worker::$connections`).
     */
    public static function broadcastToConnections(object $worker, string $event, mixed $data, ?string $room = null, string|int|null $excludeSocketId = null): void
    {
        $json = json_encode([
            'event' => $event,
            'data' => $data,
            'room' => $room,
            'timestamp' => microtime(true)
        ], JSON_UNESCAPED_UNICODE);

        foreach ($worker->connections as $connection) {
            if ($excludeSocketId !== null && (string) ($connection->id ?? '') === (string) $excludeSocketId) {
                continue;
            }
            if ($room !== null && $room !== '') {
                if (!isset($connection->rooms[$room])) {
                    continue;
                }
            }

            @$connection->send($json);
        }
    }
}
