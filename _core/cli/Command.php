<?php

namespace Cli;

/**
 * Base Console Command & Terminal Formatting utility (`Blue-bird` style).
 * 
 * Provides ANSI colorized output formatting, prompt utilities, and command execution contracts.
 * 
 * @package Cli
 */
abstract class Command
{
    /**
     * Executes the console command with provided arguments.
     * 
     * @param array $args Command line arguments array
     * @return int Exit status code (0 for success, >0 for failure)
     */
    abstract public function run(array $args): int;

    /**
     * Prints an informational cyan message.
     * 
     * @param string $message Message text
     * @return void
     */
    protected function info(string $message): void
    {
        echo "\033[36m[INFO]\033[0m {$message}" . PHP_EOL;
    }

    /**
     * Prints a success green message.
     * 
     * @param string $message Message text
     * @return void
     */
    protected function success(string $message): void
    {
        echo "\033[32m[SUCCESS]\033[0m {$message}" . PHP_EOL;
    }

    /**
     * Prints a warning yellow message.
     * 
     * @param string $message Message text
     * @return void
     */
    protected function warning(string $message): void
    {
        echo "\033[33m[WARNING]\033[0m {$message}" . PHP_EOL;
    }

    /**
     * Prints a red error message.
     * 
     * @param string $message Message text
     * @return void
     */
    protected function error(string $message): void
    {
        echo "\033[31m[ERROR]\033[0m {$message}" . PHP_EOL;
    }

    /**
     * Prints a styled banner header.
     * 
     * @param string $title Banner title
     * @return void
     */
    protected function banner(string $title): void
    {
        echo PHP_EOL;
        echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
        echo "\033[1;37m ⚡ {$title}\033[0m" . PHP_EOL;
        echo "\033[1;35m===================================================\033[0m" . PHP_EOL;
    }

    /**
     * Executes an external shell command and pipes output directly to console.
     * 
     * @param string $command Shell command string
     * @return int Return status code
     */
    protected function execShell(string $command): int
    {
        $this->info("Running: {$command}");
        passthru($command, $status);
        return $status;
    }
}
