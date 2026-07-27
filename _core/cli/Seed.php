<?php

namespace Cli;

use Core\Database;
use Models\User;

/**
 * Database Seeder Command (`php cli.php seed`).
 * 
 * Populates tables with initial development or production data.
 * 
 * @package Cli
 */
class Seed extends Command
{
    /**
     * Executes seed scripts and default record creation.
     * 
     * @param array $args Command arguments
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Database Seeder");

        $pdo = Database::getInstance();
        if ($pdo === null) {
            $this->error("Cannot execute seeders: MySQL connection offline.");
            return 1;
        }

        $this->info("Checking sample User records...");

        $seedsDir = dirname(__DIR__, 2) . '/backend/seeds';
        if (is_dir($seedsDir)) {
            foreach (glob("{$seedsDir}/*.php") as $seedFile) {
                $this->info("Executing seeder: " . basename($seedFile));
                require $seedFile;
            }
        }

        $this->success("Seeding completed.");
        return 0;
    }
}
