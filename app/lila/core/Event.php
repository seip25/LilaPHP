<?php

namespace Core;

/**
 * Event Dispatcher
 * 
 * A lightweight memory-based event bus for decoupling framework components
 * and user code.
 * 
 * @package Core
 */
class Event
{
    /** @var array<string, callable[]> Registered event listeners */
    protected static array $listeners = [];

    /**
     * Register an event listener
     * 
     * @param string $eventName Name of the event to listen for
     * @param callable $callback The listener callback to execute
     * @return void
     * 
     * @example
     * ```php
     * Event::listen('user.registered', function($user) {
     *     // Send welcome email...
     * });
     * ```
     */
    public static function listen(string $eventName, callable $callback): void
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        self::$listeners[$eventName][] = $callback;
    }

    /**
     * Dispatch an event to all its listeners
     * 
     * @param string $eventName Name of the event to dispatch
     * @param mixed ...$payload Variables to pass to the listeners
     * @return void
     * 
     * @example
     * ```php
     * Event::dispatch('user.registered', $userData);
     * ```
     */
    public static function dispatch(string $eventName, ...$payload): void
    {
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                call_user_func_array($listener, $payload);
            }
        }
    }

    /**
     * Check if an event has registered listeners
     * 
     * @param string $eventName
     * @return bool
     */
    public static function hasListeners(string $eventName): bool
    {
        return !empty(self::$listeners[$eventName]);
    }

    /**
     * Clear all listeners for a specific event or all events
     * 
     * @param string|null $eventName Name of event to clear, or null to clear all
     * @return void
     */
    public static function clear(?string $eventName = null): void
    {
        if ($eventName !== null) {
            unset(self::$listeners[$eventName]);
        } else {
            self::$listeners = [];
        }
    }
}
