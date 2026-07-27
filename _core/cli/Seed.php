<?php

namespace Cli;

use Core\Database;

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

        $baseBackend = dirname(__DIR__, 2) . '/backend';
        $seedDirs = array_filter([
            $baseBackend . '/seed',
            $baseBackend . '/seeds',
            $baseBackend . '/seeders'
        ], 'is_dir');

        $executed = 0;
        foreach ($seedDirs as $seedsDir) {
            $seedFiles = glob("{$seedsDir}/*.php");
            if (!empty($seedFiles)) {
                sort($seedFiles);
                foreach ($seedFiles as $seedFile) {
                    $this->info("Executing seeder: " . basename($seedFile));
                    require $seedFile;
                    $executed++;
                }
            }
        }

        if ($executed === 0) {
            $this->warning("No seeders found in backend/seed/, backend/seeds/, or backend/seeders/.");
        } else {
            $this->success("Seeding completed successfully ({$executed} seeder(s) executed).");
        }

        return 0;
    }
}

