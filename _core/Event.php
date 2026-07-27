<?php

namespace Core;

/**
 * High-Performance Event Emitter & Listener Suite.
 * 
 * Provides static in-memory & Redis Pub/Sub event dispatching with APCu RAM fallback.
 * 
 * @package Core
 */
class Event
{
    private static array $listeners = [];

    /**
     * Registers an event listener callback.
     * 
     * @param string $event Event identifier name (e.g. 'user.created')
     * @param callable $listener Callback handler
     * @return void
     * @example \Core\Event::listen('user.created', function($user) { ... });
     */
    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * Dispatches an event locally and broadcasts via Redis if connected.
     * 
     * @param string $event Event identifier name
     * @param mixed $payload Data payload passed to listeners
     * @return void
     * @example \Core\Event::dispatch('user.created', ['id' => 42, 'email' => 'user@example.com']);
     */
    public static function dispatch(string $event, mixed $payload = null): void
    {
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                call_user_func($listener, $payload);
            }
        }

        $redis = Cache::getRedis();
        if ($redis !== null) {
            try {
                $channel = "lilaphp:events:{$event}";
                $message = json_encode(['event' => $event, 'payload' => $payload, 'timestamp' => time()]);
                $redis->publish($channel, $message);
            } catch (\Throwable $e) {
                Logger::warning("Event Redis publish failed for `{$event}`: " . $e->getMessage());
            }
        }
    }
}
