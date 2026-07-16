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

        $admin = User::find(1);
        if ($admin === null) {
            $user = new User([
                'name' => 'Admin User',
                'email' => 'admin@lilaphp.dev',
                'password' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'admin'
            ]);

            if ($user->save()) {
                $this->success("Created default Admin account -> admin@lilaphp.dev / secret123 (ID: {$user->id})");
            } else {
                $this->error("Failed to insert default Admin account.");
            }
        } else {
            $this->info("Admin account (ID: 1) already exists. No actions taken.");
        }

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
