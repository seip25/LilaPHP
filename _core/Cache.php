<?php

namespace Core;

use Redis;
use RedisException;

/**
 * High-performance Dual-Level Cache manager (RAM APCu + Redis Cluster/DB).
 * 
 * Implements tiered memory and persistent caching with automatic fallback:
 * - `api()`: Uses PHP native APCu shared RAM for zero-latency API computations.
 * - `db()`: Uses Redis cluster/container for shared state with APCu fallback.
 * 
 * @package Core
 */
class Cache
{
    private static ?Redis $redisInstance = null;
    private static bool $redisAttempted = false;
    private static array $memoryFallback = [];

    /**
     * Executes or retrieves cached data using APCu RAM (or memory fallback).
     * 
     * @param string $key Cache key identifier
     * @param callable $callback Generator callback if cache miss occurs
     * @param int $ttl Time to live in seconds (default: 300)
     * @return mixed
     * @example $users = \Core\Cache::api('all_users', fn() => \Core\Database::fetchAll('SELECT * FROM users'), 60);
     */
    public static function api(string $key, callable $callback, int $ttl = 300): mixed
    {
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($key, $success);
            if ($success) {
                return $cached;
            }
            $data = $callback();
            apcu_store($key, $data, $ttl);
            return $data;
        }

        if (isset(self::$memoryFallback[$key])) {
            $exp = self::$memoryFallback[$key]['expire'];
            if ($exp === 0 || $exp > time()) {
                return self::$memoryFallback[$key]['data'];
            }
        }

        $data = $callback();
        self::$memoryFallback[$key] = [
            'data' => $data,
            'expire' => $ttl === 0 ? 0 : time() + $ttl
        ];
        return $data;
    }

    /**
     * Executes or retrieves cached data using Redis DB (with APCu fallback on connection error).
     * 
     * @param string $key Cache key identifier
     * @param callable $callback Generator callback if cache miss occurs
     * @param int $ttl Time to live in seconds (default: 300, 0 = forever)
     * @return mixed
     * @example $stats = \Core\Cache::db('daily_stats', fn() => $model->computeStats(), 600);
     */
    public static function db(string $key, callable $callback, int $ttl = 300): mixed
    {
        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $cached = $redis->get($key);
                if ($cached !== false && $cached !== null) {
                    $decoded = json_decode($cached, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $cached;
                }

                $data = $callback();
                $serialized = is_scalar($data) ? (string) $data : json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($ttl === 0) {
                    $redis->set($key, $serialized);
                } else {
                    $redis->setex($key, $ttl, $serialized);
                }
                return $data;
            } catch (RedisException $e) {
                Logger::warning("Redis execution error for key {$key}, falling back to APCu: " . $e->getMessage());
            }
        }

        return self::api("db_fb_{$key}", $callback, $ttl);
    }

    /**
     * Stores a value directly into the specified cache tier.
     * 
     * @param string $key Cache key identifier
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds (default: 300, 0 = forever)
     * @param string $driver Target driver ('apcu' or 'redis')
     * @return bool
     * @example \Core\Cache::set('user_token_123', ['userId' => 5], 3600, 'redis');
     */
    public static function set(string $key, mixed $value, int $ttl = 300, string $driver = 'apcu'): bool
    {
        if ($driver === 'redis') {
            $redis = self::getRedis();
            if ($redis !== null) {
                try {
                    $serialized = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                    if ($ttl === 0) {
                        return $redis->set($key, $serialized);
                    }
                    return $redis->setex($key, $ttl, $serialized);
                } catch (RedisException $e) {
                    Logger::warning("Redis store failure, falling back to APCu: " . $e->getMessage());
                }
            }
        }

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            return apcu_store($key, $value, $ttl);
        }

        self::$memoryFallback[$key] = [
            'data' => $value,
            'expire' => $ttl === 0 ? 0 : time() + $ttl
        ];
        return true;
    }

    /**
     * Retrieves a value directly from the specified cache tier.
     * 
     * @param string $key Cache key identifier
     * @param mixed $default Default return value if not found
     * @param string $driver Target driver ('apcu' or 'redis')
     * @return mixed
     * @example $val = \Core\Cache::get('rate_limit_1.2.3.4', 0, 'apcu');
     */
    public static function get(string $key, mixed $default = null, string $driver = 'apcu'): mixed
    {
        if ($driver === 'redis') {
            $redis = self::getRedis();
            if ($redis !== null) {
                try {
                    $val = $redis->get($key);
                    if ($val !== false && $val !== null) {
                        $decoded = json_decode($val, true);
                        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
                    }
                    return $default;
                } catch (RedisException $e) {
                    Logger::warning("Redis get failure, falling back to APCu: " . $e->getMessage());
                }
            }
        }

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $val = apcu_fetch($key, $success);
            return $success ? $val : $default;
        }

        if (isset(self::$memoryFallback[$key])) {
            $exp = self::$memoryFallback[$key]['expire'];
            if ($exp === 0 || $exp > time()) {
                return self::$memoryFallback[$key]['data'];
            }
        }

        return $default;
    }

    /**
     * Removes a key from both APCu and Redis cache tiers.
     * 
     * @param string $key Cache key identifier
     * @return void
     * @example \Core\Cache::delete('user_profile_12');
     */
    public static function delete(string $key): void
    {
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_delete($key);
        }
        unset(self::$memoryFallback[$key]);

        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $redis->del($key);
            } catch (RedisException $e) {
                Logger::warning("Redis delete failure: " . $e->getMessage());
            }
        }
    }

    /**
     * Flushes all cached data across APCu, memory fallback, and Redis DB.
     * 
     * @return void
     * @example \Core\Cache::clear();
     */
    public static function clear(): void
    {
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_clear_cache();
        }
        self::$memoryFallback = [];

        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $redis->flushDB();
            } catch (RedisException $e) {
                Logger::warning("Redis flushDB failure: " . $e->getMessage());
            }
        }
    }

    /**
     * Alias for clear() that returns a boolean status.
     * 
     * @return bool
     */
    public static function flush(): bool
    {
        self::clear();
        return true;
    }

    /**
     * Lazily connects and returns the Redis connection instance.
     * 
     * @return Redis|null
     * @example $redis = \Core\Cache::getRedis();
     */
    public static function getRedis(): ?Redis
    {
        if (self::$redisInstance !== null) {
            return self::$redisInstance;
        }

        if (self::$redisAttempted || !class_exists('Redis')) {
            return null;
        }

        self::$redisAttempted = true;

        try {
            $redis = new Redis();
            $connected = @$redis->connect(Config::$REDIS_HOST, Config::$REDIS_PORT, 1.5);
            if (!$connected) {
                return null;
            }

            if (Config::$REDIS_PASSWORD !== '') {
                $redis->auth(Config::$REDIS_PASSWORD);
            }

            self::$redisInstance = $redis;
            return self::$redisInstance;
        } catch (RedisException $e) {
            Logger::warning("Could not establish Redis connection to " . Config::$REDIS_HOST . ": " . $e->getMessage());
            return null;
        }
    }
}
