<?php

/**
 * LilaPHP Core Bootstrap & Auto-Prepend Engine
 * 
 * Initializes autoloader, environment configurations, and global JSON error handling.
 * Designed to be prepended via `auto_prepend_file` in PHP-FPM / CLI or required directly.
 */

if (defined('LILAPHP_BOOTSTRAPPED')) {
    return;
}
define('LILAPHP_BOOTSTRAPPED', true);

require_once __DIR__ . '/Config.php';

spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    $namespace = strtolower($parts[0] ?? '');
    $className = implode('/', array_slice($parts, 1));

    if ($namespace === 'core') {
        $file = __DIR__ . "/{$className}.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    } elseif ($namespace === 'models' || $namespace === 'backend') {
        $file = dirname(__DIR__) . "/backend/models/{$className}.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    } elseif ($namespace === 'cli') {
        $file = __DIR__ . "/cli/{$className}.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

\Core\Config::load();

set_exception_handler(function (\Throwable $exception): void {
    \Core\Logger::error('Uncaught Exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    if (PHP_SAPI === 'cli') {
        echo "\033[31m[Fatal Exception] " . $exception->getMessage() . "\033[0m" . PHP_EOL;
        echo "File: " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
        exit(1);
    }

    $status = $exception instanceof \PDOException ? 500 : ($exception->getCode() >= 400 && $exception->getCode() <= 599 ? (int)$exception->getCode() : 500);
    $trace = \Core\Config::$DEBUG ? [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => explode("\n", $exception->getTraceAsString())
    ] : null;

    \Core\Response::error($exception->getMessage(), $status, $trace);
});

set_error_handler(function (int $level, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $level) || $level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
        return false;
    }
    throw new \ErrorException($message, 0, $level, $file, $line);
});
