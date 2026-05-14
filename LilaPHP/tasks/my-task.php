<?php

use Core\Logger;

/**
 * Test Task for LilaPHP Scheduler
 */
echo "ℹ Executing MyTask...\n";
Logger::info("Scheduled task 'my-task' executed successfully.", "scheduler");
echo "✓ Task completed!\n";
