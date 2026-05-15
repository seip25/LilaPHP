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
require_once __DIR__ . '/lila/cli/Command.php';
require_once __DIR__ . '/lila/cli/Migrate.php';
require_once __DIR__ . '/lila/cli/Seed.php';
require_once __DIR__ . '/lila/cli/Model.php';
require_once __DIR__ . '/lila/cli/Test.php';
require_once __DIR__ . '/lila/cli/Minify.php';
require_once __DIR__ . '/lila/cli/Schedule.php';
require_once __DIR__ . '/lila/cli/Admin.php';
require_once __DIR__ . '/lila/cli/Init.php';
require_once __DIR__ . '/lila/cli/Config.php';
require_once __DIR__ . '/lila/cli/Optimize.php';
require_once __DIR__ . '/lila/cli/Sitemap.php';
require_once __DIR__ . '/lila/cli/KeyGen.php';
require_once __DIR__ . '/lila/core/TestCase.php';
require_once __DIR__ . '/lila/core/Schedule.php';

use Cli\Migrate;
use Cli\Seed;
use Cli\Model;
use Cli\Test;
use Cli\Minify;
use Cli\Schedule;
use Cli\Admin;
use Cli\Init;
use Cli\Config as ConfigCmd;
use Cli\Optimize;
use Cli\Sitemap;
use Cli\KeyGen;

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

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

        case 'model:cache':
            $model = new Model();
            $model->cache($args);
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

        case 'admin:add':
            $admin = new Admin();
            $admin->execute($args);
            break;

        case 'app:init':
        case 'init':
            $init = new Init();
            $init->execute($args);
            break;

        case 'config:cache':
            $config = new ConfigCmd();
            $config->cache($args);
            break;

        case 'config:clear':
            $config = new ConfigCmd();
            $config->clear($args);
            break;

        case 'route:cache':
        case 'route:clear':
            $route = new \Cli\RouteCache();
            $route->execute(['clear']);
            break;

        case 'queue:work':
            require_once __DIR__ . '/lila/cli/QueueWork.php';
            $queue = new \Cli\QueueWork();
            $queue->execute($args);
            break;

        case 'app:optimize':
            $optimize = new Optimize();
            $optimize->execute($args);
            break;

        case 'sitemap:generate':
            $sitemap = new Sitemap();
            $sitemap->execute($args);
            break;

        case 'key:generate':
            $keygen = new KeyGen();
            $keygen->execute($args);
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
  \033[32mmodel:cache\033[0m         Generate model metadata cache for production

  \033[32madmin:add\033[0m           Create or update an admin user

  \033[32mschedule:run\033[0m        Execute scheduled tasks from /tasks/my-task.php 
  \033[32mqueue:work\033[0m          Start the queue worker to process background jobs
 
  \033[32mtest:run\033[0m            Run all test suites (*Test.php)

  \033[32msitemap:generate\033[0m    Generate a multilingual sitemap.xml for SEO
  \033[32massets:minify\033[0m       Minify CSS and JS files in assets/
 
  \033[32mkey:generate\033[0m        Generate a secure random SECRET_KEY and write it to .env
  \033[32mconfig:cache\033[0m        Generate environment variables cache for production
  \033[32mconfig:clear\033[0m        Clear all application caches (lila/cache/)
  \033[32mapp:optimize\033[0m        Unified production optimization (config + models + assets)
  \033[32mapp:init\033[0m            Initialize application scaffolding from lila/scaffold
  
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
