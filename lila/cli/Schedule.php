<?php

namespace Cli;

use Core\Config;
use Core\Schedule as CoreSchedule;

/**
 * Schedule Runner Command
 * 
 * Executes scheduled cron tasks.
 * 
 * @package Cli
 */
class Schedule extends Command
{
    public function execute(array $args): void
    {
        $this->info("Running scheduler...");
        $tasksFile = Config::$DIR_PROJECT . '/tasks/tasks.php';

        if (!file_exists($tasksFile)) {
            $this->warning("No tasks.php found in tasks/ directory.");
            $this->line("Create one using: Core\Schedule::call(fn() => ...)->everyMinute();");
            return;
        }

        require_once $tasksFile;

        $results = CoreSchedule::run();
        $ran = count($results);
        $passed = count(array_filter($results));
        $failed = $ran - $passed;

        if ($ran === 0) {
            $this->info("No tasks to run at this time.");
        } else {
            $this->success("Ran {$ran} tasks ({$passed} passed, {$failed} failed).");
        }
    }
}
