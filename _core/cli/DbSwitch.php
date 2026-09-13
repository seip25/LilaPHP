<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;

/**
 * Database Engine Switcher CLI (`php cli.php db:switch sqlite|mysql`).
 * 
 * Dynamically switches the active database driver between MySQL and SQLite,
 * updates .env configuration, and flushes OPcache / APCu environment cache.
 * 
 * @package Cli
 */
class DbSwitch extends Command
{
    /**
     * Executes database driver switch.
     * 
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        $targetDriver = strtolower(trim($args[0] ?? ''));

        if (!in_array($targetDriver, ['mysql', 'sqlite'], true)) {
            $this->error("Invalid database driver `{$targetDriver}`.");
            echo "Usage:" . PHP_EOL;
            echo "  php cli.php db:switch sqlite" . PHP_EOL;
            echo "  php cli.php db:switch mysql" . PHP_EOL;
            return 1;
        }

        $envFile = dirname(__DIR__, 2) . '/.env';

        if (!file_exists($envFile)) {
            $this->error("Root .env file not found at: {$envFile}");
            return 1;
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            $this->error("Unable to read .env file.");
            return 1;
        }

        if (preg_match('/^DB_TYPE\s*=\s*.*$/m', $content)) {
            $newContent = preg_replace('/^DB_TYPE\s*=\s*.*$/m', "DB_TYPE={$targetDriver}", $content);
        } else {
            $newContent = $content . PHP_EOL . "DB_TYPE={$targetDriver}";
        }

        if ($targetDriver === 'sqlite') {
            $dbDir = Config::$DIR_APP . '/database';
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0777, true);
            }
            if (!str_contains($newContent, 'DB_FILE=')) {
                $newContent .= PHP_EOL . 'DB_FILE=' . Config::$DIR_APP . '/database/app.sqlite';
            }
        }

        file_put_contents($envFile, $newContent);

        Config::clearCache();
        Config::cache($envFile);

        $this->success("Database switched successfully to [{$targetDriver}].");
        $this->info("Updated root `.env` and refreshed OPcache environment cache.");

        if ($targetDriver === 'sqlite') {
            $this->info("Standalone SQLite database storage path: `app/database/app.sqlite`");
        }

        return 0;
    }
}
