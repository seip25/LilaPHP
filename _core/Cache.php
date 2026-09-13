<?php

declare(strict_types=1);

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
     */
    public static function db(string $key, callable $callback, int $ttl = 300): mixed
    {
        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $cached = $redis->get($key);
                if ($cached !== false && $cached !== null) {
                    $decoded = json_decode($cached, true);
                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded === true || $decoded === false || ($decoded === null && $cached === 'null'))) {
                        return $decoded;
                    }
                    return $cached;
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
     */
    public static function fileSet(string $key, mixed $value, int $ttl = 300): bool
    {
        $dir = Config::$DIR_CORE . '/cache/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . md5($key) . '.cache';
        $data = [
            'expire'  => $ttl === 0 ? 0 : time() + $ttl,
            'payload' => $value
        ];
        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public static function fileGet(string $key, mixed $default = null): mixed
    {
        $dir = Config::$DIR_CORE . '/cache/data';
        $file = $dir . '/' . md5($key) . '.cache';
        if (!file_exists($file)) {
            return $default;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }
        $data = @unserialize($content);
        if (!is_array($data) || !isset($data['expire'], $data['payload'])) {
            return $default;
        }
        if ($data['expire'] !== 0 && $data['expire'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['payload'];
    }

    public static function set(string $key, mixed $value, int $ttl = 300, string $driver = 'auto'): bool
    {
        if ($driver === 'file') {
            return self::fileSet($key, $value, $ttl);
        }

        if ($driver === 'redis' || $driver === 'auto') {
            $redis = self::getRedis();
            if ($redis !== null) {
                try {
                    $serialized = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                    if ($ttl === 0) {
                        return $redis->set($key, $serialized);
                    }
                    return $redis->setex($key, $ttl, $serialized);
                } catch (RedisException $e) {
                    Logger::warning("Redis store failure, falling back to next tier: " . $e->getMessage());
                }
            }
            if ($driver === 'redis') {
                return false;
            }
        }

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            return apcu_store($key, $value, $ttl);
        }

        if ($driver === 'auto') {
            return self::fileSet($key, $value, $ttl);
        }

        self::$memoryFallback[$key] = [
            'data' => $value,
            'expire' => $ttl === 0 ? 0 : time() + $ttl
        ];
        return true;
    }

    public static function get(string $key, mixed $default = null, string $driver = 'auto'): mixed
    {
        if ($driver === 'file') {
            return self::fileGet($key, $default);
        }

        if ($driver === 'redis' || $driver === 'auto') {
            $redis = self::getRedis();
            if ($redis !== null) {
                try {
                    $val = $redis->get($key);
                    if ($val !== false && $val !== null) {
                        $decoded = json_decode($val, true);
                        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded === true || $decoded === false || ($decoded === null && $val === 'null'))) {
                            return $decoded;
                        }
                        return $val;
                    }
                    if ($driver === 'redis') {
                        return $default;
                    }
                } catch (RedisException $e) {
                    Logger::warning("Redis get failure, falling back to next tier: " . $e->getMessage());
                }
            }
        }

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $val = apcu_fetch($key, $success);
            if ($success) {
                return $val;
            }
            if ($driver === 'apcu') {
                return $default;
            }
        }

        if ($driver === 'auto') {
            return self::fileGet($key, $default);
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
     * Removes a key from APCu, memory fallback, or Redis cache tiers.
     * 
     * @param string $key Cache key identifier
     * @param string $driver Target tier ('both', 'apcu', 'redis')
     * @return void
     */
    public static function delete(string $key, string $driver = 'both'): void
    {
        if ($driver === 'both' || $driver === 'apcu') {
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                apcu_delete($key);
            }
            unset(self::$memoryFallback[$key]);
        }

        if ($driver === 'both' || $driver === 'redis') {
            $redis = self::getRedis();
            if ($redis !== null) {
                try {
                    $redis->del($key);
                } catch (RedisException $e) {
                    Logger::warning("Redis delete failure: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Flushes all cached data across APCu, memory fallback, and Redis DB.
     * 
     * @return void
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
