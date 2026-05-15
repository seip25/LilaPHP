<?php

namespace Cli;

use Models\Admin as AdminModel;
use Core\Database;
use Core\Config;
use PDO;

/**
 * Admin Management Command
 * 
 * Handles administrative user creation and database setup.
 * 
 * @package Cli
 */
class Admin extends Command
{
    /**
     * Execute the admin:add command
     */
    public function execute(array $args): void
    {
        $this->info("Setting up Admin user...");

        // 1. Check Database & Run Migrations if table missing
        if (!$this->db) {
            $this->connectDatabase(true);
        }

        if (!$this->tableExists('admins')) {
            $this->warning("Table 'admins' not found. Running migrations...");
            // Execute migrate:run logic
            $migrate = new Migrate();
            $migrate->run([]);
            
            if (!$this->tableExists('admins')) {
                $this->error("Failed to create 'admins' table. Check your model and database settings.");
                return;
            }
        }

        // 2. Prompt for credentials
        echo "Enter Admin Username: ";
        $handle = fopen("php://stdin", "r");
        $username = trim(fgets($handle));
        
        echo "Enter Admin Password: ";
        // Simple password masking for CLI (wont work on all systems but good enough for PHP)
        $password = trim(fgets($handle));
        fclose($handle);

        if (strlen($username) < 3) {
            $this->error("Username must be at least 3 characters.");
            return;
        }

        if (strlen($password) < 8) {
            $this->error("Password must be at least 8 characters.");
            return;
        }

        // 3. Create Admin
        try {
            $exists = AdminModel::where('username', '=', $username, activeOnly: false);
            if (!empty($exists)) {
                $this->warning("Admin user '{$username}' already exists. Updating password...");
                $admin = $exists[0];
                $admin->password = password_hash($password, PASSWORD_BCRYPT);
            } else {
                $admin = new AdminModel([
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_BCRYPT)
                ]);
            }
            
            if ($admin->save()) {
                $this->success("Admin user '{$username}' saved successfully!");
            } else {
                $this->error("Failed to save admin user.");
            }
        } catch (\Exception $e) {
            $this->error("Error creating admin: " . $e->getMessage());
        }
    }

    /**
     * Helper to check table existence
     */
    private function tableExists(string $table): bool
    {
        try {
            $res = $this->db->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
