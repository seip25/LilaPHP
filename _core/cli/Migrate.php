<?php

namespace Cli;

use Core\Database;
use Core\Config;
use ReflectionClass;

/**
 * High-performance Automated Schema Migrations (`php cli.php migrate [--refresh]`).
 * 
 * Inspects all API Models (`backend/models/*.php`) and synchronizes MySQL schema tables
 * directly without writing tedious boilerplate migration files.
 * 
 * @package Cli
 */
class Migrate extends Command
{
    /**
     * Executes the automatic schema migration across all discovered models.
     * 
     * @param array $args Command arguments (`--refresh`, `--fresh`)
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Schema Migration Engine");

        $db = new Database();
        $pdo = $db->getConnection();

        if ($pdo === null) {
            $this->error("Cannot connect to MySQL at " . Config::$DB_HOST . ":" . Config::$DB_PORT);
            return 1;
        }

        $this->info("Connected to MySQL DB: `" . Config::$DB_NAME . "`");

        $isFresh = in_array('--refresh', $args, true) || in_array('--fresh', $args, true) || in_array('-f', $args, true) || in_array('-r', $args, true);

        if ($isFresh) {
            $this->warning("Wiping existing database tables in `" . Config::$DB_NAME . "`...");
            if ($db->dropAllTables()) {
                $this->success("All tables dropped successfully.");
            } else {
                $this->error("Failed to drop database tables.");
                return 1;
            }
        }

        $modelsDir = dirname(__DIR__, 2) . '/backend/models';
        if (!is_dir($modelsDir)) {
            $this->error("Models directory not found at: {$modelsDir}");
            return 1;
        }

        $files = glob("{$modelsDir}/*.php");
        $migratedCount = 0;

        foreach ($files as $file) {
            require_once $file;
            $className = 'Models\\' . basename($file, '.php');

            if (!class_exists($className) || $className === 'Models\\BaseModel') {
                continue;
            }

            $ref = new ReflectionClass($className);
            if (!$ref->isSubclassOf('Models\\BaseModel') || $ref->isAbstract()) {
                continue;
            }

            if (!method_exists($className, 'getSchema')) {
                $this->warning("Model {$className} has no getSchema() method defined. Skipping.");
                continue;
            }

            $instance = new $className();
            $table = $instance->table;
            $schema = $className::getSchema();

            $this->info("Migrating Table: `{$table}` (Model: {$className})...");
            if ($db->createTable($table, $schema)) {
                $this->success("Table `{$table}` synchronized successfully.");
                $migratedCount++;
            } else {
                $this->error("Failed to synchronize table `{$table}`.");
            }
        }

        $this->success("Migration process completed. Synchronized {$migratedCount} tables.");
        return 0;
    }
}
