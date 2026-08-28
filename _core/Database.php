<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Ultralight Multi-Driver PDO Database Wrapper (`KISS & Performance First`).
 * 
 * Supports SQLite, MySQL, and PostgreSQL with zero external dependencies.
 * Provides singleton connection pooling, prepared statement execution helpers,
 * atomic transactions, schema indexing, and simplified CRUD helpers.
 * 
 * @package Core
 */
class Database
{
    private static ?PDO $instance = null;
    private ?PDO $db = null;

    private string $driver;
    private string $host;
    private string $dbName;
    private string $dbUser;
    private string $dbPassword;
    private int $port;
    private string $dbFile;

    /**
     * Initializes database connection parameters from Config.
     */
    public function __construct(
        ?string $driver = null,
        ?string $host = null,
        ?string $dbUser = null,
        ?string $dbPassword = null,
        ?string $dbName = null,
        ?int $port = null,
        ?string $dbFile = null
    ) {
        $this->driver = $driver ?? Config::$DB_TYPE;
        $this->host = $host ?? Config::$DB_HOST;
        $this->dbUser = $dbUser ?? Config::$DB_USER;
        $this->dbPassword = $dbPassword ?? Config::$DB_PASSWORD;
        $this->dbName = $dbName ?? Config::$DB_NAME;
        $this->port = $port ?? Config::$DB_PORT;
        $this->dbFile = $dbFile ?? Config::$DB_FILE;

        $this->db = $this->connect();
    }

    /**
     * Returns a shared singleton PDO connection instance.
     */
    public static function getInstance(): ?PDO
    {
        if (self::$instance === null) {
            $db = new self();
            self::$instance = $db->getConnection();
        }
        return self::$instance;
    }

    /**
     * Returns the active PDO connection instance.
     */
    public function getConnection(): ?PDO
    {
        return $this->db;
    }

    /**
     * Connects to the database driver with optimal PDO attributes.
     */
    private function connect(): ?PDO
    {
        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            if ($this->driver === 'sqlite') {
                $dir = dirname($this->dbFile);
                if (!is_dir($dir) && $this->dbFile !== ':memory:') {
                    @mkdir($dir, 0777, true);
                }

                $pdo = new PDO("sqlite:{$this->dbFile}", null, null, $options);
                // Enable SQLite WAL mode and foreign keys for high concurrency & speed
                $pdo->exec('PRAGMA journal_mode = WAL;');
                $pdo->exec('PRAGMA foreign_keys = ON;');
                return $pdo;
            }

            if ($this->driver === 'pgsql') {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbName};";
                return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
            }

            // Default: MySQL
            $dsn = empty($this->dbName)
                ? "mysql:host={$this->host};port={$this->port};charset=utf8mb4"
                : "mysql:host={$this->host};dbname={$this->dbName};port={$this->port};charset=utf8mb4";

            return new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
        } catch (PDOException $e) {
            Logger::error('Database Connection Error: ' . $e->getMessage());
            if (Config::$DEBUG) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Creates a database if it does not exist (MySQL/PostgreSQL).
     */
    public function createDatabase(string $name): bool
    {
        if ($this->driver === 'sqlite' || !$this->db) {
            return true;
        }
        try {
            $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Drops all user tables in the database.
     */
    public function dropAllTables(): bool
    {
        if (!$this->db) return false;
        try {
            if ($this->driver === 'sqlite') {
                $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $t) {
                    $this->db->exec("DROP TABLE IF EXISTS `{$t}`");
                }
                return true;
            }

            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                $this->db->exec("DROP TABLE IF EXISTS `{$t}`");
            }
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Creates a table from a schema definition and builds required indexes.
     */
    public function createTable(string $table, array $schema): bool
    {
        if (!$this->db) return false;

        try {
            $cols = [];
            $indexes = [];
            $primaryKey = null;

            foreach ($schema as $name => $def) {
                $type = strtolower($def['type'] ?? 'string');
                $sqlType = match ($type) {
                    'int', 'integer' => $this->driver === 'sqlite' ? 'INTEGER' : 'INT',
                    'string', 'varchar' => ($this->driver === 'sqlite' ? 'TEXT' : 'VARCHAR(' . ($def['length'] ?? 255) . ')'),
                    'text' => 'TEXT',
                    'timestamp', 'datetime' => ($this->driver === 'sqlite' ? 'DATETIME' : 'TIMESTAMP'),
                    'float', 'double' => ($this->driver === 'sqlite' ? 'REAL' : 'DOUBLE'),
                    'bool', 'boolean' => ($this->driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)'),
                    default => 'TEXT'
                };

                $nullPart = ($def['nullable'] ?? true) ? 'NULL' : 'NOT NULL';
                $defaultPart = isset($def['default']) 
                    ? ($def['default'] === 'CURRENT_TIMESTAMP' ? 'DEFAULT CURRENT_TIMESTAMP' : ($def['default'] === null ? 'DEFAULT NULL' : "DEFAULT '" . addslashes((string)$def['default']) . "'"))
                    : '';

                $autoInc = '';
                if (!empty($def['autoIncrement'])) {
                    if ($this->driver === 'sqlite') {
                        $sqlType = 'INTEGER';
                        $autoInc = 'PRIMARY KEY AUTOINCREMENT';
                        $primaryKey = $name;
                    } else {
                        $autoInc = 'AUTO_INCREMENT';
                    }
                }

                if (!empty($def['primaryKey']) && $this->driver !== 'sqlite') {
                    $primaryKey = $name;
                }

                $cols[] = trim("`{$name}` {$sqlType} {$nullPart} {$defaultPart} {$autoInc}");

                // Register indexes
                if (!empty($def['unique'])) {
                    $indexes[] = ['type' => 'UNIQUE', 'column' => $name, 'name' => "uniq_{$table}_{$name}"];
                } elseif (!empty($def['index']) || in_array($name, ['email', 'role', 'status', 'created_at', 'deleted_at', 'user_id', 'product_id'], true)) {
                    $indexes[] = ['type' => 'INDEX', 'column' => $name, 'name' => "idx_{$table}_{$name}"];
                }
            }

            if ($primaryKey && $this->driver !== 'sqlite') {
                $cols[] = "PRIMARY KEY (`{$primaryKey}`)";
            }

            $colsSql = implode(",\n  ", $cols);
            $engine = $this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
            $createSql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n  {$colsSql}\n){$engine};";
            $this->db->exec($createSql);

            // Create secondary and unique indexes
            foreach ($indexes as $idx) {
                if ($this->driver === 'sqlite') {
                    $uniqueKw = $idx['type'] === 'UNIQUE' ? 'UNIQUE' : '';
                    $this->db->exec("CREATE {$uniqueKw} INDEX IF NOT EXISTS `{$idx['name']}` ON `{$table}` (`{$idx['column']}`);");
                } else {
                    $exists = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$idx['name']}'")->fetch();
                    if (!$exists) {
                        $typeKw = $idx['type'] === 'UNIQUE' ? 'UNIQUE INDEX' : 'INDEX';
                        $this->db->exec("ALTER TABLE `{$table}` ADD {$typeKw} `{$idx['name']}` (`{$idx['column']}`);");
                    }
                }
            }

            return true;
        } catch (\Throwable $e) {
            Logger::error("Table creation failed for `{$table}`: " . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------
    // Fluent Static Query Helpers (`DB::query`, `DB::fetch`, etc.)
    // --------------------------------------------------------------------------

    /**
     * Execute a prepared SQL query and return the PDOStatement.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            throw new \RuntimeException('Database connection is not available.');
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array.
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all matching rows as an array of associative arrays.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single column value (scalar).
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Insert a record into a table and return the last inserted ID.
     */
    public static function insert(string $table, array $data): int|string
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            throw new \RuntimeException('Database connection is not available.');
        }

        $keys = array_keys($data);
        $fields = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", $keys));

        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $pdo->lastInsertId();
    }

    /**
     * Update records in a table matching a WHERE clause.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            throw new \RuntimeException('Database connection is not available.');
        }

        $setParts = [];
        $params = [];
        foreach ($data as $key => $val) {
            $paramName = "set_{$key}";
            $setParts[] = "`{$key}` = :{$paramName}";
            $params[$paramName] = $val;
        }

        $setSql = implode(', ', $setParts);
        $sql = "UPDATE `{$table}` SET {$setSql} WHERE {$where}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * Delete records from a table matching a WHERE clause.
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = self::query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Execute a callback inside an atomic database transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            throw new \RuntimeException('Database connection is not available.');
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Return the last inserted row ID.
     */
    public static function lastInsertId(): string|false
    {
        return self::getInstance()?->lastInsertId() ?? false;
    }
}
