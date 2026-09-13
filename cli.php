#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * LilaPHP Master CLI Dispatcher (`cli.php`)
 * 
 * Provides unified terminal orchestration for migrations, seeding, key generation,
 * scaffolding, database switching, sitemap/robots, and Docker cluster management.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI access only']);
    exit(1);
}

require_once __DIR__ . '/_core/bootstrap.php';

use Core\Config;

$args = array_slice($argv, 1);
$commandInput = strtolower(array_shift($args) ?? 'help');

if (str_starts_with($commandInput, 'make:')) {
    $sub = substr($commandInput, 5);
    array_unshift($args, $sub);
    $commandInput = 'make';
}

if ($commandInput === 'sqlite') {
    $commandInput = 'db:switch';
    $args = ['sqlite'];
} elseif ($commandInput === 'mysql') {
    $commandInput = 'db:switch';
    $args = ['mysql'];
}

$commandMap = [
    'migrate'      => \Cli\Migrate::class,
    'seed'         => \Cli\Seed::class,
    'optimize'     => \Cli\Optimize::class,
    'key:generate' => \Cli\KeyGen::class,
    'docker'       => \Cli\Docker::class,
    'make'         => \Cli\Make::class,
    'db:switch'    => \Cli\DbSwitch::class,
    'db'           => \Cli\DbSwitch::class,
    'sitemap'      => \Cli\Sitemap::class,
    'robots'       => \Cli\Robots::class,
    'dev'          => \Cli\Dev::class,
    'health'       => \Cli\Health::class,
    'doctor'       => \Cli\Doctor::class,
    'task:work'    => \Cli\TaskWork::class,
    'task:run'     => \Cli\TaskWork::class,
    'ws:serve'     => \Cli\WsServe::class,
    'ws:start'     => \Cli\WsServe::class,
    'benchmark'    => \Cli\Benchmark::class,
];

if (isset($commandMap[$commandInput])) {
    $nullDev = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    if (!file_exists('/.dockerenv') && in_array($commandInput, ['migrate', 'seed', 'optimize', 'task:work', 'task:run', 'ws:serve', 'ws:start', 'benchmark'], true)) {
        if (trim((string) @shell_exec("docker compose --env-file ./.env ps -q php 2>{$nullDev}")) !== '') {
            echo "\033[36m⚡ [LilaPHP Engine] Auto-forwarding `{$commandInput}` command into Docker container...\033[0m" . PHP_EOL;
            $ttyFlag = (function_exists('posix_isatty') && posix_isatty(STDOUT)) ? '' : '-T ';
            $cmdArgs = !empty($args) ? ' ' . implode(' ', array_map('escapeshellarg', $args)) : '';
            passthru("docker compose --env-file ./.env exec {$ttyFlag}php php cli.php {$commandInput}{$cmdArgs}", $exitCode);
            exit($exitCode);
        }
    }

    $commandClass = $commandMap[$commandInput];
    $cmd = new $commandClass();
    exit($cmd->run($args));
}

if ($commandInput === 'help' || $commandInput === '--help' || $commandInput === '-h') {
    echo PHP_EOL;
    echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
    echo "\033[1;37m ⚡ LilaPHP High-Performance CLI Engine v2.0.0\033[0m" . PHP_EOL;
    echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
    echo PHP_EOL;
    echo "\033[33mUsage:\033[0m php cli.php [command] [arguments]" . PHP_EOL;
    echo PHP_EOL;
    echo "\033[32mAvailable Commands:\033[0m" . PHP_EOL;
    echo "  \033[36mdev [port]\033[0m                Launch development server with live Hot Reload" . PHP_EOL;
    echo "  \033[36mmigrate [--refresh]\033[0m       Synchronize Model schemas with active database" . PHP_EOL;
    echo "  \033[36mseed\033[0m                      Populate database tables with seed data" . PHP_EOL;
    echo "  \033[36moptimize\033[0m                  Pre-cache .env configuration to OPcache memory" . PHP_EOL;
    echo "  \033[36mkey:generate\033[0m              Generate cryptographic 256-bit APP_KEY in .env" . PHP_EOL;
    echo "  \033[36mdb:switch <sqlite|mysql>\033[0m  Switch active database driver and reload config" . PHP_EOL;
    echo "  \033[36msitemap\033[0m                   Generate SEO sitemap at public/sitemap.xml" . PHP_EOL;
    echo "  \033[36mrobots\033[0m                    Generate search crawler rules at public/robots.txt" . PHP_EOL;
    echo "  \033[36mtask:work\033[0m                 Start background Redis job worker queue" . PHP_EOL;
    echo "  \033[36mws:serve [port]\033[0m           Boot real-time WebSocket server" . PHP_EOL;
    echo "  \033[36mbenchmark\033[0m                  Run server load test (--url= --concurrency= --duration=)" . PHP_EOL;
    echo "  \033[36mdocker [action]\033[0m            Orchestrate container stack (dev, sqlite, prod, stop, ps, clean)" . PHP_EOL;
    echo "  \033[36mmake model <Name>\033[0m         Generate Model in app/models/" . PHP_EOL;
    echo "  \033[36mmake route <path>\033[0m         Generate Web Route in app/routes/" . PHP_EOL;
    echo "  \033[36mmake api <Name>\033[0m           Generate full REST Resource in app/routes/api/" . PHP_EOL;
    echo "  \033[36mmake crud <Name> [--embed]\033[0m Generate DataTable CRUD view & route (with embed support)" . PHP_EOL;
    echo "  \033[36mhealth\033[0m                    Run live system diagnostics (DB, Redis, OPcache)" . PHP_EOL;
    echo "  \033[36mdoctor\033[0m                    Run pre-flight checks (views, routes, assets, ports)" . PHP_EOL;
    echo PHP_EOL;
    exit(0);
}

echo "\033[31m[ERROR]\033[0m Unknown command `{$commandInput}`. Run `php cli.php help` to see available commands." . PHP_EOL;
exit(1);
