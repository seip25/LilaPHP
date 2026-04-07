<?php

/**
 * LilaPHP CLI Entry Point
 * 
 * Command-line interface for running migrations, seeders, and other tasks.
 * 
 * Usage:
 *   php cli.php migrate:run
 *   php cli.php migrate:create
 *   php cli.php seed:run
 * 
 * @package LilaPHP
 * @author Andrés Paiva
 */

require_once __DIR__ . '/index.php';
require_once __DIR__ . '/cli/Command.php';
require_once __DIR__ . '/cli/Migrate.php';
require_once __DIR__ . '/cli/Seed.php';
require_once __DIR__ . '/cli/Model.php';
require_once __DIR__ . '/cli/Test.php';
require_once __DIR__ . '/cli/Minify.php';
require_once __DIR__ . '/cli/Schedule.php';
require_once __DIR__ . '/core/TestCase.php';
require_once __DIR__ . '/core/Schedule.php';

use Cli\Migrate;
use Cli\Seed;
use Cli\Model;
use Cli\Test;
use Cli\Minify;
use Cli\Schedule;

// Parse command line arguments
$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

// Color output helpers
function success(string $msg): void
{
    echo "\033[32m✓ {$msg}\033[0m" . PHP_EOL;
}

function error(string $msg): void
{
    echo "\033[31m✗ {$msg}\033[0m" . PHP_EOL;
}

function info(string $msg): void
{
    echo "\033[36mℹ {$msg}\033[0m" . PHP_EOL;
}

function warning(string $msg): void
{
    echo "\033[33m⚠ {$msg}\033[0m" . PHP_EOL;
}

// Command routing
try {
    switch ($command) {
        case 'migrate:run':
            $migrate = new Migrate();
            $migrate->run($args);
            break;

        case 'migrate:create':
            $migrate = new Migrate();
            $migrate->create($args);
            break;

        case 'migrate:rollback':
            $migrate = new Migrate();
            $migrate->rollback($args);
            break;

        case 'migrate:fresh':
            $migrate = new Migrate();
            $migrate->fresh($args);
            break;

        case 'migrate:status':
            $migrate = new Migrate();
            $migrate->status($args);
            break;

        case 'seed:run':
            $seed = new Seed();
            $seed->run($args);
            break;

        case 'seed:create':
            $seed = new Seed();
            $seed->create($args);
            break;

        case 'model:create':
            $model = new Model();
            $model->create($args);
            break;

        case 'test:run':
            $test = new Test();
            $test->execute($args);
            break;

        case 'assets:minify':
            $minify = new Minify();
            $minify->execute($args);
            break;

        case 'schedule:run':
            $schedule = new Schedule();
            $schedule->execute($args);
            break;

        case 'help':
        default:
            echo <<<HELP
\033[1mLilaPHP CLI\033[0m

\033[33mAvailable Commands:\033[0m

  \033[32mmigrate:run\033[0m         Run pending migrations
  \033[32mmigrate:create\033[0m      Create database and tables from models
  \033[32mmigrate:rollback\033[0m    Rollback last migration
  \033[32mmigrate:fresh\033[0m       Drop all tables and re-migrate
  \033[32mmigrate:status\033[0m      Show migration status

  \033[32mseed:run\033[0m            Run all seeders
  \033[32mseed:create\033[0m         Create a new seeder file

  \033[32mmodel:create\033[0m        Create a new model file
 
  \033[32mtest:run\033[0m            Run all test suites (*Test.php)
  \033[32massets:minify\033[0m       Minify CSS and JS files in assets/
  \033[32mschedule:run\033[0m        Execute scheduled tasks from app/tasks.php

  \033[32mhelp\033[0m                Show this help message

\033[33mExamples:\033[0m
  php cli.php migrate:create
  php cli.php model:create Product
  php cli.php seed:run

HELP;
            break;
    }
} catch (Throwable $e) {
    error("Error: " . $e->getMessage());
    if (\Core\Config::$DEBUG) {
        echo "\n" . $e->getTraceAsString() . PHP_EOL;
    }
    exit(1);
}
