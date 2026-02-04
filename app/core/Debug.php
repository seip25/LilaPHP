<?php

namespace Core;

use PDO;
use PDOException;

/**
 * Debug System Class
 * 
 * Handles request tracking, performance monitoring, and debug data storage using SQLite.
 * Capture execution time, memory usage, and request details for debugging purposes.
 * 
 * @package Core
 */
class Debug
{
    /** @var PDO|null SQLite database connection */
    private static ?PDO $db = null;

    /** @var float Request start timestamp */
    private static float $startTime = 0;

    /** @var int Initial memory usage */
    private static int $startMemory = 0;

    /**
     * Initialize the debug system
     * 
     * Creates the SQLite database connection and ensures the requests table exists.
     * 
     * @return void
     */
    public static function init(): void
    {
        if (!Config::$DEBUG) {
            return;
        }

        try {
            $path = Config::$DIR_PROJECT . "/lila";
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            $dbPath = $path . '/debug.sqlite';
            self::$db = new PDO("sqlite:$dbPath");
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            self::$db->exec("CREATE TABLE IF NOT EXISTS debug_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                method TEXT,
                uri TEXT,
                duration REAL,
                memory_peak INTEGER,
                response_code INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                trace TEXT
            )");
        } catch (PDOException $e) {
            Logger::error("Debug system init failed: " . $e->getMessage());
        }
    }

    /**
     * Start tracking the current request
     * 
     * Captures the initial timestamp and memory usage.
     * 
     * @return void
     */
    public static function start(): void
    {
        if (!Config::$DEBUG) {
            return;
        }

        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }

    /**
     * End request tracking and save data
     * 
     * Calculates duration and memory peak, then saves the request details
     * to the SQLite database.
     * 
     * @param int $responseCode HTTP response code
     * @return void
     */
    public static function end(int $responseCode = 200): void
    {
        if (!Config::$DEBUG || !self::$db) {
            return;
        }

        try {
            $duration = (microtime(true) - self::$startTime) * 1000; // ms
            $memoryPeak = memory_get_peak_usage(true);
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';

            // Basic trace of the request flow (simplified)
            $trace = json_encode([
                'memory_initial' => self::$startMemory,
                'memory_final' => memory_get_usage(),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ]);

            $stmt = self::$db->prepare("INSERT INTO debug_requests 
                (method, uri, duration, memory_peak, response_code, trace) 
                VALUES (?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $method,
                $uri,
                $duration,
                $memoryPeak,
                $responseCode,
                $trace
            ]);
        } catch (PDOException $e) {
            Logger::error("Debug system logging failed: " . $e->getMessage());
        }
    }

    /**
     * Retrieve logged requests
     * 
     * @param int $limit Maximum number of requests to return
     * @return array List of request records
     */
    public static function getRequests(int $limit = 100): array
    {
        if (!self::$db) {
            self::init();
        }

        if (!self::$db) {
            return [];
        }

        try {
            $stmt = self::$db->prepare("SELECT * FROM debug_requests ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    public static function clear(): void
    {
        if (!self::$db) {
            self::init();
        }

        if (!self::$db) {
            return;
        }

        try {
            self::$db->exec("DELETE FROM debug_requests");
        } catch (PDOException $e) {
            Logger::error("Debug system clear failed: " . $e->getMessage());
        }
    }
}
