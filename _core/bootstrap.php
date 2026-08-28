<?php

declare(strict_types=1);

/**
 * LilaPHP Core Bootstrap & Auto-Prepend Engine (`KISS & Performance First`).
 * 
 * Initializes the PSR-4 autoloader, environment configuration, global helpers,
 * and unified JSON API error/exception handling.
 */

if (defined('LILAPHP_BOOTSTRAPPED')) {
    return;
}
define('LILAPHP_BOOTSTRAPPED', true);

if (!defined('LILAPHP_START_TIME')) {
    define('LILAPHP_START_TIME', microtime(true));
}

// Load Core Config & Helpers
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/helpers.php';

// Composer Autoloader compatibility
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// --------------------------------------------------------------------------
// PSR-4 Zero-Dependency Native Autoloader
// --------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    $namespace = strtolower($parts[0] ?? '');
    $className = implode('/', array_slice($parts, 1));
    $baseDir = dirname(__DIR__);

    if ($namespace === 'core') {
        $file = __DIR__ . "/{$className}.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    } elseif ($namespace === 'backend' || $namespace === 'controllers' || $namespace === 'models' || $namespace === 'services' || $namespace === 'sockets') {
        $target = "{$baseDir}/backend/{$namespace}/{$className}.php";
        if (file_exists($target)) {
            require_once $target;
            return;
        }

        // Direct backend/ class fallback
        $directTarget = "{$baseDir}/backend/{$className}.php";
        if (file_exists($directTarget)) {
            require_once $directTarget;
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

// Class Alias for ultra-clean DB queries (DB::query, DB::fetch, etc.)
if (!class_exists('DB', false)) {
    class_alias(\Core\Database::class, 'DB');
    class_alias(\Core\Database::class, 'Core\DB');
}

// Load Environment Configuration
\Core\Config::load();

// --------------------------------------------------------------------------
// Global JSON Exception Handler
// --------------------------------------------------------------------------
set_exception_handler(function (\Throwable $exception): void {
    \Core\Logger::error('Uncaught Exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    if (PHP_SAPI === 'cli') {
        echo "\033[31m[Fatal Exception] " . $exception->getMessage() . "\033[0m" . PHP_EOL;
        echo "File: " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
        exit(1);
    }

    $statusCode = ($exception->getCode() >= 400 && $exception->getCode() <= 599) 
        ? (int)$exception->getCode() 
        : ($exception instanceof \PDOException ? 500 : 500);

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    \Core\Response::applyCorsHeaders();

    $response = [
        'error'   => true,
        'code'    => $statusCode,
        'message' => \Core\Config::$DEBUG ? $exception->getMessage() : ($statusCode === 500 ? 'Internal Server Error' : $exception->getMessage()),
    ];

    if (\Core\Config::$DEBUG) {
        $response['debug'] = [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => explode("\n", $exception->getTraceAsString()),
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (\Core\Config::$DEBUG ? JSON_PRETTY_PRINT : 0));
    exit;
});

// --------------------------------------------------------------------------
// Global Error Handler
// --------------------------------------------------------------------------
set_error_handler(function (int $level, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $level) || $level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
        return false;
    }
    throw new \ErrorException($message, 0, $level, $file, $line);
});

// Record request metrics only in development debug or when logging is explicitly enabled
if (PHP_SAPI !== 'cli' && (\Core\Config::$DEBUG || \Core\Config::$LOG_ENABLED)) {
    register_shutdown_function([\Core\Debug::class, 'recordRequest']);
}
