<?php

namespace Cli;

use Core\Config;

/**
 * Security Key Generation Command (`php cli.php key:generate`).
 * 
 * Generates and saves a cryptographic 256-bit base64 key into `backend/.env`.
 * 
 * @package Cli
 */
class KeyGen extends Command
{
    /**
     * Generates secure encryption key and modifies `backend/.env`.
     * 
     * @param array $args Command arguments
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Key Generation");

        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envPath)) {
            $this->error("Environment file `.env` not found.");
            return 1;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $content = file_get_contents($envPath);

        if (preg_match('/^APP_KEY=/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content);
        } else {
            $content .= "\nAPP_KEY={$key}\n";
        }

        file_put_contents($envPath, $content);
        $this->success("Generated new APP_KEY: {$key}");

        Config::cache();
        $this->info("Updated configuration cache `_core/cache/env.php` automatically.");

        return 0;
    }
}
