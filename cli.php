#!/usr/bin/env php
<?php

/**
 * LilaPHP Blue-bird style Master CLI Dispatcher (`cli.php`)
 * 
 * Provides unified terminal orchestration for migrations, seeding, key generation,
 * caching optimization, and Docker container management.
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

$commandMap = [
    'migrate'       => \Cli\Migrate::class,
    'seed'          => \Cli\Seed::class,
    'optimize'      => \Cli\Optimize::class,
    'key:generate'  => \Cli\KeyGen::class,
    'docker'        => \Cli\Docker::class,
    'make'          => \Cli\Make::class,
    'task:work'     => \Cli\TaskWork::class,
    'task:run'      => \Cli\TaskWork::class,
];

if (isset($commandMap[$commandInput])) {
    $commandClass = $commandMap[$commandInput];
    $cmd = new $commandClass();
    exit($cmd->run($args));
}

if ($commandInput === 'help' || $commandInput === '--help' || $commandInput === '-h') {
    echo PHP_EOL;
    echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
    echo "\033[1;37m ⚡ LilaPHP High-Performance CLI Engine v1.0.0\033[0m" . PHP_EOL;
    echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
    echo PHP_EOL;
    echo "\033[33mUsage:\033[0m php cli.php [command] [arguments]" . PHP_EOL;
    echo PHP_EOL;
    echo "\033[32mAvailable Commands:\033[0m" . PHP_EOL;
    echo "  \033[36mmigrate\033[0m        Synchronize all API Model table schemas with MySQL" . PHP_EOL;
    echo "  \033[36mseed\033[0m           Populate database tables with initial seed records" . PHP_EOL;
    echo "  \033[36moptimize\033[0m       Pre-cache .env settings to OPcache memory and flush APCu/Redis" . PHP_EOL;
    echo "  \033[36mkey:generate\033[0m   Generate secure 256-bit cryptographic APP_KEY in backend/.env" . PHP_EOL;
    echo "  \033[36mtask:work\033[0m      Start continuous background worker consuming Redis job queues (`lilaphp:jobs`)" . PHP_EOL;
    echo "  \033[36mdocker [dev|prod|stop|ps|logs|clean]\033[0m  Orchestrate Nginx, PHP, MySQL, Redis cluster" . PHP_EOL;
    echo "  \033[36mmake model <Name>\033[0m      Generate boilerplate API Model inside backend/models/" . PHP_EOL;
    echo "  \033[36mmake route <path>\033[0m      Generate file-based API route inside backend/routes/" . PHP_EOL;
    echo PHP_EOL;
    exit(0);
}

echo "\033[31m[ERROR]\033[0m Unknown command `{$commandInput}`. Run `php cli.php help` to see available commands." . PHP_EOL;
exit(1);
