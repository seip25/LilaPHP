<?php

namespace Cli;

use Core\Config;
use Core\Database;
use PDO;

/**
 * Base Command Class
 * 
 * Abstract base class for all CLI commands providing common functionality
 * like database access, output formatting, and error handling.
 * 
 * @package Cli
 */
abstract class Command
{
    protected ?PDO $db = null;
    protected ?Database $database = null;
    protected array $dbConfig = [];

    public function __construct()
    {
        // Load configuration
        Config::load();

        // Store database configuration but don't connect yet
        $this->dbConfig = [
            'provider' => Config::Env("DB_PROVIDER") ?? 'mysql',
            'host' => Config::Env("DB_HOST") ?? 'localhost',
            'dbUser' => Config::Env("DB_USER") ?? 'root',
            'dbPassword' => Config::Env("DB_PASSWORD") ?? '',
            'dbName' => Config::Env("DB_NAME") ?? 'db_test',
            'port' => (int)(Config::Env("DB_PORT") ?? 3306)
        ];
    }

    /**
     * Connect to database
     * 
     * @param bool $withDatabase Whether to connect to a specific database or just the server
     * @return void
     */
    protected function connectDatabase(bool $withDatabase = true): void
    {
        $dbName = $withDatabase ? $this->dbConfig['dbName'] : null;

        $this->database = new Database(
            provider: $this->dbConfig['provider'],
            host: $this->dbConfig['host'],
            dbUser: $this->dbConfig['dbUser'],
            dbPassword: $this->dbConfig['dbPassword'],
            dbName: $dbName ?? '',
            port: $this->dbConfig['port']
        );

        $this->db = $this->database->getConnection();
    }

    /**
     * Execute the command
     * 
     * @param array $args Command arguments
     * @return void
     */
    abstract public function execute(array $args): void;

    /**
     * Print success message
     * 
     * @param string $msg Message to print
     * @return void
     */
    protected function success(string $msg): void
    {
        echo "\033[32m✓ {$msg}\033[0m" . PHP_EOL;
    }

    /**
     * Print error message
     * 
     * @param string $msg Message to print
     * @return void
     */
    protected function error(string $msg): void
    {
        echo "\033[31m✗ {$msg}\033[0m" . PHP_EOL;
    }

    /**
     * Print info message
     * 
     * @param string $msg Message to print
     * @return void
     */
    protected function info(string $msg): void
    {
        echo "\033[36mℹ {$msg}\033[0m" . PHP_EOL;
    }

    /**
     * Print warning message
     * 
     * @param string $msg Message to print
     * @return void
     */
    protected function warning(string $msg): void
    {
        echo "\033[33m⚠ {$msg}\033[0m" . PHP_EOL;
    }

    /**
     * Print a line
     * 
     * @param string $msg Message to print
     * @return void
     */
    protected function line(string $msg = ''): void
    {
        echo $msg . PHP_EOL;
    }

    /**
     * Get database connection
     * 
     * @return PDO|null
     */
    protected function getDb(): ?PDO
    {
        return $this->db;
    }

    /**
     * Get database instance
     * 
     * @return Database|null
     */
    protected function getDatabase(): ?Database
    {
        return $this->database;
    }
}
