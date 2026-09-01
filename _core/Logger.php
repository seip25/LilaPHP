<?php

declare(strict_types=1);

namespace Core;

/**
 * High-performance async/non-blocking File Logger.
 * 
 * Records structured log events cleanly organized by date hierarchies
 * under `_core/logs/Y/m/d/type.log`.
 * 
 * @package Core
 */
class Logger
{
    private static string $logDir = '';
    private static string $logDate = '';

    /**
     * Resolves and creates the date-namespaced log directory structure.
     */
    private static function ensureDir(): string
    {
        $today = date('Ymd');
        if (self::$logDir === '' || self::$logDate !== $today) {
            $baseDir = Config::$DIR_CORE !== '' ? Config::$DIR_CORE . '/logs' : dirname(__DIR__) . '/_core/logs';
            $year = date('Y');
            $month = date('m');
            $day = date('d');
            $fullPath = "{$baseDir}/{$year}/{$month}/{$day}";

            if (!is_dir($fullPath)) {
                @mkdir($fullPath, 0755, true);
            }

            self::$logDir = $fullPath;
            self::$logDate = $today;
        }

        return self::$logDir;
    }

    /**
     * Appends a formatted log entry to the respective log file.
     * 
     * @param string $type Severity level (info, warning, error, debug)
     * @param string $message Detailed message string
     * @param array $context Optional context metadata
     * @return void
     */
    private static function write(string $type, string $message, array $context = []): void
    {
        if (PHP_SAPI === 'cli') {
            $timestamp = date('H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            echo "[{$timestamp}] [{$type}] {$message}{$contextStr}" . PHP_EOL;
        }

        if (!Config::$LOG_ENABLED && !Config::$DEBUG) {
            return;
        }

        try {
            $logDir = self::ensureDir();
            $file = "{$logDir}/{$type}.log";
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $entry = "[{$timestamp}] [{$type}] {$message}{$contextStr}\n";

            @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            if (PHP_SAPI === 'cli' && Config::$DEBUG) {
                echo "Logger Error: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * Logs an informational event.
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * Logs a warning event.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * Logs an error event.
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Logs a debugging event when APP_DEBUG is enabled.
     */
    public static function debug(string $message, array $context = []): void
    {
        if (Config::$DEBUG) {
            self::write('debug', $message, $context);
        }
    }
}
