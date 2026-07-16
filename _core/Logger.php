<?php

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

    /**
     * Resolves and creates the date-namespaced log directory structure.
     * 
     * @return string
     * @example $dir = self::ensureDir();
     */
    private static function ensureDir(): string
    {
        if (self::$logDir === '') {
            $baseDir = Config::$DIR_CORE !== '' ? Config::$DIR_CORE . '/logs' : dirname(__DIR__) . '/_core/logs';
            $year = date('Y');
            $month = date('m');
            $day = date('d');
            $fullPath = "{$baseDir}/{$year}/{$month}/{$day}";

            if (!is_dir($fullPath)) {
                @mkdir($fullPath, 0777, true);
            }

            self::$logDir = $fullPath;
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
     * @example self::write('error', 'Database timeout', ['query' => $sql]);
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

            file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            if (PHP_SAPI === 'cli' && Config::$DEBUG) {
                echo "Logger Error: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * Logs an informational event.
     * 
     * @param string $message Informational description
     * @param array $context Additional debugging data
     * @return void
     * @example \Core\Logger::info('User authenticated successfully', ['userId' => 10]);
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * Logs a warning event.
     * 
     * @param string $message Warning description
     * @param array $context Additional context metadata
     * @return void
     * @example \Core\Logger::warning('High memory consumption detected', ['usage' => $bytes]);
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * Logs an error event.
     * 
     * @param string $message Error description
     * @param array $context Trace details or state variables
     * @return void
     * @example \Core\Logger::error('Failed to execute migration', ['error' => $e->getMessage()]);
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Logs a debugging event when APP_DEBUG is enabled.
     * 
     * @param string $message Debug note
     * @param array $context Diagnostic variables
     * @return void
     * @example \Core\Logger::debug('Payload received', ['body' => $requestBody]);
     */
    public static function debug(string $message, array $context = []): void
    {
        if (Config::$DEBUG) {
            self::write('debug', $message, $context);
        }
    }
}
