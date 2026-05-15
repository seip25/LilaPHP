<?php

namespace Cli;

use Core\BaseModel;
use Core\Config;
use PDO;

/**
 * Migration Command
 * 
 * Handles database migrations from model definitions using FieldDatabase attributes.
 * 
 * @package Cli
 */
class Migrate extends Command
{
    private string $migrationsTable = 'migrations';

    /**
     * Execute migration command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->run($args);
    }

    /**
     * Run pending migrations
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function run(array $args): void
    {
        if (!$this->db) {
            $this->connectDatabase(true);
        }


        $this->info("Running migrations...");

        $this->createMigrationsTable();

        $models = $this->discoverModels();

        if (empty($models)) {
            $this->warning("No models found with FieldDatabase attributes.");
            return;
        }

        $migrated = 0;
        foreach ($models as $modelClass) {
            if ($this->migrateModel($modelClass)) {
                $migrated++;
            }
        }

        if ($migrated > 0) {
            $this->success("Migrated {$migrated} table(s) successfully!");
        } else {
            $this->info("Nothing to migrate.");
        }
    }

    /**
     * Create database and tables from models
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function create(array $args): void
    {
        $this->info("Creating database and tables...");
        if ($this->dbConfig['provider'] !== "sqlite") {
            $dbName = $this->dbConfig['dbName'];
            try {
                $tempDb = new \Core\Database(
                    provider: $this->dbConfig['provider'],
                    host: $this->dbConfig['host'],
                    dbUser: $this->dbConfig['dbUser'],
                    dbPassword: $this->dbConfig['dbPassword'],
                    dbName: "",
                    port: $this->dbConfig['port']
                );
                if ($tempDb->createDatabase($dbName)) {
                    $this->success("Database '{$dbName}' created or already exists.");
                } else {
                    $this->error("Failed to create database '{$dbName}'.");
                    return;
                }
            } catch (\Exception $e) {
                $this->error("Error creating database: " . $e->getMessage());
                return;
            }
        }

        $this->connectDatabase(true);

        $this->run($args);
    }

    /**
     * Rollback last migration (placeholder)
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function rollback(array $args): void
    {
        $this->warning("Rollback functionality not yet implemented.");
        $this->info("To manually rollback, drop the table and re-run migrations.");
    }

    /**
     * Drop all tables and re-migrate
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function fresh(array $args): void
    {
        if (!$this->db) {
            $this->connectDatabase(true);
        }

        $this->warning("This will drop all tables. Are you sure? (yes/no)");
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) !== 'yes') {
            $this->info("Operation cancelled.");
            return;
        }

        $this->info("Dropping all tables...");

        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            try {
                $quotedTable = $this->quoteIdentifier($table);
                $this->db->exec("DROP TABLE IF EXISTS {$quotedTable}");
                $this->info("Dropped table: {$table}");
            } catch (\PDOException $e) {
                $this->error("Failed to drop table {$table}: " . $e->getMessage());
            }
        }

        $this->success("All tables dropped.");
        $this->line();

        $this->run($args);
    }

    /**
     * Show migration status
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function status(array $args): void
    {
        if (!$this->db) {
            $this->connectDatabase(true);
        }

        if (!$this->database) {
            $this->error("Database connection failed.");
            return;
        }

        $this->info("Migration Status:");
        $this->line();

        $models = $this->discoverModels();

        if (empty($models)) {
            $this->warning("No models found.");
            return;
        }

        foreach ($models as $modelClass) {
            $tableName = $modelClass::getTableName();
            $exists = $this->database->tableExists($tableName);

            if ($exists) {
                $this->success("✓ {$tableName} (from {$modelClass})");
            } else {
                $this->warning("✗ {$tableName} (from {$modelClass}) - NOT MIGRATED");
            }
        }
    }

    /**
     * Migrate a single model
     * 
     * @param string $modelClass Model class name
     * @return bool True if migrated, false if already exists
     */
    private function migrateModel(string $modelClass): bool
    {
        $tableName = $modelClass::getTableName();
        $schema = $modelClass::getSchema();

        if (empty($schema)) {
            $this->warning("Model {$modelClass} has no FieldDatabase attributes. Skipping.");
            return false;
        }

        if ($this->database->tableExists($tableName) && $this->dbConfig['provider'] !== 'sqlite') {
            $this->info("Table '{$tableName}' already exists. Skipping.");
            return false;
        }

        if ($this->database->createTable($tableName, $schema)) {
            $this->success("Created table: {$tableName}");
            $this->recordMigration($tableName, $modelClass);
            return true;
        } else {
            $this->error("Failed to create table: {$tableName}");
            return false;
        }
    }

    /**
     * Discover all model classes in the app directory
     * 
     * @return array Array of model class names
     */
    private function discoverModels(): array
    {
        $models = [];
        $modelsDir = Config::$DIR_PROJECT . '/models';

        if (!is_dir($modelsDir)) {
            return [];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                if (preg_match('/class\s+(\w+)\s+extends\s+BaseModel/', $content, $matches)) {
                    $namespace = '';
                    if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $nsMatches)) {
                        $namespace = $nsMatches[1] . '\\';
                    }

                    $className = $namespace . $matches[1];

                    require_once $file->getPathname();

                    if (class_exists($className)) {
                        $schema = $className::getSchema();
                        if (!empty($schema)) {
                            $models[] = $className;
                        }
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Create migrations tracking table
     * 
     * @return void
     */
    private function createMigrationsTable(): void
    {
        if ($this->database->tableExists($this->migrationsTable)) {
            return;
        }

        $quotedTable = $this->quoteIdentifier($this->migrationsTable);

        if ($this->dbConfig['provider'] === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$quotedTable} (
            " . $this->quoteIdentifier('id') . " INTEGER PRIMARY KEY AUTOINCREMENT,
            " . $this->quoteIdentifier('table_name') . " TEXT NOT NULL,
            " . $this->quoteIdentifier('model_class') . " TEXT NOT NULL,
            " . $this->quoteIdentifier('migrated_at') . " TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (" . $this->quoteIdentifier('table_name') . ")
        )";
        } elseif ($this->dbConfig['provider'] === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS {$quotedTable} (
            " . $this->quoteIdentifier('id') . " SERIAL PRIMARY KEY,
            " . $this->quoteIdentifier('table_name') . " VARCHAR(255) NOT NULL,
            " . $this->quoteIdentifier('model_class') . " VARCHAR(255) NOT NULL,
            " . $this->quoteIdentifier('migrated_at') . " TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (" . $this->quoteIdentifier('table_name') . ")
        )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$quotedTable} (
            " . $this->quoteIdentifier('id') . " INT AUTO_INCREMENT PRIMARY KEY,
            " . $this->quoteIdentifier('table_name') . " VARCHAR(255) NOT NULL,
            " . $this->quoteIdentifier('model_class') . " VARCHAR(255) NOT NULL,
            " . $this->quoteIdentifier('migrated_at') . " TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY " . $this->quoteIdentifier('unique_table') . " (" . $this->quoteIdentifier('table_name') . ")
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            $this->error("Failed to create migrations table: " . $e->getMessage());
        }
    }

    /**
     * Record a migration
     * 
     * @param string $tableName Table name
     * @param string $modelClass Model class name
     * @return void
     */
    private function recordMigration(string $tableName, string $modelClass): void
    {
        try {
            $quotedTable = $this->quoteIdentifier($this->migrationsTable);
            $quotedTableName = $this->quoteIdentifier('table_name');
            $quotedModelClass = $this->quoteIdentifier('model_class');

            $stmt = $this->db->prepare(
                "INSERT INTO {$quotedTable} ({$quotedTableName}, {$quotedModelClass}) VALUES (?, ?)"
            );
            $stmt->execute([$tableName, $modelClass]);
        } catch (\PDOException $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * Quote identifier based on database provider
     * 
     * @param string $identifier Identifier to quote
     * @return string Quoted identifier
     */
    private function quoteIdentifier(string $identifier): string
    {
        $provider = $this->dbConfig['provider'] ?? 'mysql';
        return match ($provider) {
            'pgsql' => "\"{$identifier}\"",
            'mysql', 'sqlite' => "`{$identifier}`",
            default => "`{$identifier}`"
        };
    }

    /**
     * Get all tables in the database
     * 
     * @return array Array of table names
     */
    private function getAllTables(): array
    {
        try {
            $provider = Config::Env("DB_PROVIDER") ?? 'mysql';

            $sql = match ($provider) {
                'mysql' => "SHOW TABLES",
                'pgsql' => "SELECT tablename FROM pg_tables WHERE schemaname = 'public'",
                'sqlite' => "SELECT name FROM sqlite_master WHERE type='table'",
                default => "SHOW TABLES"
            };

            $result = $this->db->query($sql);
            $tables = [];

            foreach ($result->fetchAll(PDO::FETCH_NUM) as $row) {
                $tables[] = $row[0];
            }

            return $tables;
        } catch (\PDOException $e) {
            $this->error("Failed to get tables: " . $e->getMessage());
            return [];
        }
    }
}
