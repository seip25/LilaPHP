<?php

use Core\Schedule;

/**
 * LilaPHP Task Scheduler Configuration
 * 
 * Define your scheduled tasks here using the Schedule facade.
 */

Schedule::call(function () {
    $taskFile = __DIR__ . '/my-task.php';
    if (file_exists($taskFile)) {
        require_once $taskFile;
    }
})->everyMinute();
