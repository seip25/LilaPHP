<?php

namespace Cli;

use Core\Config as CoreConfig;

/**
 * Key Generator Command
 * 
 * Generates a cryptographically secure random SECRET_KEY and writes it to .env.
 * 
 * @package Cli
 */
class KeyGen extends Command
{
    /**
     * Execute the key generation command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->info("Generating secure SECRET_KEY...");

        $envPath = CoreConfig::$DIR_PROJECT . '/.env';

        if (!file_exists($envPath)) {
            $this->error(".env file not found at: {$envPath}");
            return;
        }

        try {
            $key = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $this->error("Failed to generate random key: " . $e->getMessage());
            return;
        }

        $content = file_get_contents($envPath);

        if ($content === false) {
            $this->error("Could not read .env file.");
            return;
        }

        if (preg_match('/^SECRET_KEY=.*/m', $content)) {
            $newContent = preg_replace(
                '/^SECRET_KEY=.*/m',
                'SECRET_KEY="' . $key . '"',
                $content
            );
        } else {
            // Append to end of file
            $newContent = rtrim($content) . "\nSECRET_KEY=\"{$key}\"\n";
        }

        if (file_put_contents($envPath, $newContent) === false) {
            $this->error("Could not write to .env file. Check permissions.");
            return;
        }

        $this->success("SECRET_KEY generated and written to .env");
        $this->line("");
        $this->line("  \033[33mKey:\033[0m {$key}");
        $this->line("");
        $this->info("Run 'php cli.php config:clear' to invalidate any existing env cache.");
    }
}
