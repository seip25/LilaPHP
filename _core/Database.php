<?php

namespace Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * High-performance MySQL Database Connection and Schema Management class.
 * 
 * Provides lazy-loaded, pooled PDO MySQL connections with automatic reconnects,
 * prepared statement execution helpers, and schema introspection for migrations.
 * 
 * @package Core
 */
class Database
{
    private static ?PDO $instance = null;
    private ?PDO $db = null;
    private string $host;
    private string $dbName;
    private string $dbUser;
    private string $dbPassword;
    private int $port;
    private int $maxAttempts;

    /**
     * Initializes database connection parameters.
     * 
     * @param string|null $host MySQL hostname
     * @param string|null $dbUser MySQL username
     * @param string|null $dbPassword MySQL password
     * @param string|null $dbName Database name
     * @param int|null $port MySQL port (default: 3306)
     * @param int $maxAttempts Maximum connection retry attempts
     * @example $db = new \Core\Database('mysql', 'root', 'root', 'lilaphp', 3306);
     */
    public function __construct(
        ?string $host = null,
        ?string $dbUser = null,
        ?string $dbPassword = null,
        ?string $dbName = null,
        ?int $port = null,
        int $maxAttempts = 5
    ) {
        $this->host = $host ?? Config::$DB_HOST;
        $this->dbUser = $dbUser ?? Config::$DB_USER;
        $this->dbPassword = $dbPassword ?? Config::$DB_PASSWORD;
        $this->dbName = $dbName ?? Config::$DB_NAME;
        $this->port = $port ?? Config::$DB_PORT;
        $this->maxAttempts = $maxAttempts;

        $this->db = $this->connectWithRetry();
    }

    /**
     * Returns a shared singleton PDO connection instance.
     * 
     * @return PDO|null
     * @example $pdo = \Core\Database::getInstance();
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
     * 
     * @return PDO|null
     * @example $pdo = $db->getConnection();
     */
    public function getConnection(): ?PDO
    {
        return $this->db;
    }

    /**
     * Constructs the MySQL PDO DSN string.
     * 
     * @return string
     * @example $dsn = $db->getDsn();
     */
    private function getDsn(): string
    {
        return "mysql:host={$this->host};dbname={$this->dbName};port={$this->port};charset=utf8mb4";
    }

    /**
     * Attempts PDO connection with retry and exponential backoff.
     * 
     * @return PDO|null
     * @example $pdo = $this->connectWithRetry();
     */
    private function connectWithRetry(): ?PDO
    {
        $attempt = 0;

        if (!extension_loaded('pdo_mysql')) {
            throw new \RuntimeException("MySQL PDO extension (`pdo_mysql`) is not loaded in this PHP environment (" . PHP_BINARY . "). If running locally on host, please run inside Docker (`docker compose exec php php cli.php migrate`).");
        }

        while ($attempt < $this->maxAttempts) {
            try {
                $dsn = $this->getDsn();
                $initCmdAttr = defined('Pdo\\Mysql::ATTR_INIT_COMMAND') ? \Pdo\Mysql::ATTR_INIT_COMMAND : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? \PDO::MYSQL_ATTR_INIT_COMMAND : 1002);
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    $initCmdAttr => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ];

                $connection = new PDO($dsn, $this->dbUser, $this->dbPassword, $options);
                return $connection;
            } catch (PDOException $e) {
                $attempt++;
                $message = "MySQL Connection error (attempt {$attempt}/{$this->maxAttempts}): " . $e->getMessage();
                Logger::error($message);

                if (Config::$DEBUG && PHP_SAPI === 'cli') {
                    echo $message . PHP_EOL;
                }

                if ($attempt < $this->maxAttempts) {
                    sleep($attempt);
                } else {
                    Logger::error("Failed to connect to MySQL after {$this->maxAttempts} attempts.");
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Executes a parameterized query and returns the statement.
     * 
     * @param string $sql SQL query string with parameter placeholders
     * @param array $params Bound parameters array
     * @return PDOStatement|false
     * @example $stmt = \Core\Database::query('SELECT * FROM users WHERE id = ?', [1]);
     */
    public static function query(string $sql, array $params = []): PDOStatement|false
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            Logger::error("SQL Query execution failed: " . $e->getMessage() . " | SQL: {$sql}");
            return false;
        }
    }

    /**
     * Fetches a single row as an associative array.
     * 
     * @param string $sql SQL query string
     * @param array $params Bound parameters array
     * @return array|null
     * @example $user = \Core\Database::fetch('SELECT * FROM users WHERE email = ?', ['john@example.com']);
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        if (!$stmt) {
            return null;
        }
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetches all matching rows as an array of associative arrays.
     * 
     * @param string $sql SQL query string
     * @param array $params Bound parameters array
     * @return array
     * @example $users = \Core\Database::fetchAll('SELECT * FROM users WHERE active = ?', [1]);
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        if (!$stmt) {
            return [];
        }
        return $stmt->fetchAll();
    }

    /**
     * Inserts an associative array of data into a table and returns the last inserted ID.
     * 
     * @param string $table Target table name
     * @param array<string, mixed> $data Associative column-value data map
     * @return string|false Last inserted ID or false on error
     * @example $id = \Core\Database::insert('users', ['name' => 'Ana', 'email' => 'ana@example.com']);
     */
    public static function insert(string $table, array $data): string|false
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $quotedCols = array_map(fn($c) => "`{$c}`", $columns);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $quotedCols),
            implode(', ', $placeholders)
        );

        $stmt = self::query($sql, array_values($data));
        if ($stmt && self::getInstance()) {
            return self::getInstance()->lastInsertId();
        }
        return false;
    }

    /**
     * Updates rows in a table matching a WHERE clause.
     * 
     * @param string $table Target table name
     * @param array<string, mixed> $data Columns and values to update
     * @param string $where WHERE condition clause (e.g., 'id = ? AND status = ?')
     * @param array $whereParams Parameters for the WHERE condition
     * @return int Number of affected rows
     * @example $affected = \Core\Database::update('users', ['name' => 'Carl'], 'id = ?', [1]);
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            return 0;
        }

        $setClauses = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setClauses[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            $where
        );

        $params = array_merge($params, $whereParams);
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Deletes rows matching a condition.
     * 
     * @param string $table Target table name
     * @param string $where WHERE condition clause
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     * @example $deleted = \Core\Database::delete('users', 'id = ?', [1]);
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = self::query($sql, $whereParams);
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Creates a MySQL database if it does not already exist.
     * 
     * @param string $dbName Name of the database
     * @return bool
     * @example $db->createDatabase('lilaphp');
     */
    public function createDatabase(string $dbName): bool
    {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};charset=utf8mb4";
            $pdo = new PDO($dsn, $this->dbUser, $this->dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to create database {$dbName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a specific table exists in the active MySQL database.
     * 
     * @param string $tableName Table name
     * @return bool
     * @example if ($db->tableExists('users')) { ... }
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
            return $stmt && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            Logger::error("Failed to check table existence for {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a table from a schema definition array.
     * 
     * @param string $tableName Table name
     * @param array $schema Schema definition map
     * @return bool
     * @example $db->createTable('users', $schemaArray);
     */
    public function createTable(string $tableName, array $schema): bool
    {
        try {
            $columns = [];
            $primaryKeys = [];
            $indexes = [];
            $uniques = [];

            foreach ($schema as $columnName => $definition) {
                $columns[] = $this->buildColumnDefinition($columnName, $definition);

                if (!empty($definition['primaryKey'])) {
                    $primaryKeys[] = $columnName;
                }
                if (!empty($definition['index']) && empty($definition['primaryKey'])) {
                    $indexes[] = $columnName;
                }
                if (!empty($definition['unique']) && empty($definition['primaryKey'])) {
                    $uniques[] = $columnName;
                }
            }

            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n  ";
            $sql .= implode(",\n  ", $columns);

            if (!empty($primaryKeys)) {
                $quotedKeys = array_map(fn($k) => "`{$k}`", $primaryKeys);
                $sql .= ",\n  PRIMARY KEY (" . implode(', ', $quotedKeys) . ")";
            }

            foreach ($uniques as $uniqueCol) {
                $sql .= ",\n  UNIQUE KEY `unique_{$uniqueCol}` (`{$uniqueCol}`)";
            }

            foreach ($indexes as $indexCol) {
                $sql .= ",\n  KEY `idx_{$indexCol}` (`{$indexCol}`)";
            }

            $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to create table {$tableName}: " . $e->getMessage());
            if (Config::$DEBUG && PHP_SAPI === 'cli') {
                echo "SQL Error: " . $e->getMessage() . PHP_EOL;
            }
            return false;
        }
    }

    /**
     * Builds individual SQL column definition.
     * 
     * @param string $columnName Column name
     * @param array $definition Column attributes
     * @return string
     * @example $colSql = $this->buildColumnDefinition('email', ['type' => 'string', 'length' => 150]);
     */
    private function buildColumnDefinition(string $columnName, array $definition): string
    {
        $type = $this->mapColumnType($definition['type'] ?? 'string', $definition['length'] ?? null);
        $sql = "`{$columnName}` {$type}";

        if (!empty($definition['unsigned']) && in_array(strtolower($definition['type'] ?? ''), ['int', 'bigint', 'smallint', 'tinyint'], true)) {
            $sql .= " UNSIGNED";
        }

        if (empty($definition['nullable'])) {
            $sql .= " NOT NULL";
        } else {
            $sql .= " NULL";
        }

        if (!empty($definition['autoIncrement'])) {
            $sql .= " AUTO_INCREMENT";
        } elseif (isset($definition['default']) && $definition['default'] !== null) {
            if (in_array(strtoupper((string) $definition['default']), ['CURRENT_TIMESTAMP', 'NOW()'], true)) {
                $sql .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif (is_string($definition['default'])) {
                $sql .= " DEFAULT '" . addslashes($definition['default']) . "'";
            } else {
                $sql .= " DEFAULT " . $definition['default'];
            }
        }

        if (!empty($definition['comment'])) {
            $sql .= " COMMENT '" . addslashes($definition['comment']) . "'";
        }

        return $sql;
    }

    /**
     * Maps generic type names to MySQL data types.
     * 
     * @param string $type Generic type name
     * @param int|null $length Optional type length or precision
     * @return string
     * @example $mysqlType = $this->mapColumnType('string', 100);
     */
    private function mapColumnType(string $type, int|string|null $length): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'INT',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'tinyint' => 'TINYINT',
            'varchar', 'string' => 'VARCHAR(' . ($length ?? 255) . ')',
            'char' => 'CHAR(' . ($length ?? 1) . ')',
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'boolean', 'bool' => 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'decimal' => 'DECIMAL(' . (is_string($length) && str_contains($length, ',') ? $length : ($length ?? 10) . ',2') . ')',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'json' => 'JSON',
            default => strtoupper($type)
        };
    }

    /**
     * Returns metadata for all columns in a table.
     * 
     * @param string $tableName Table name
     * @return array
     * @example $cols = $db->getColumns('users');
     */
    /**
     * Drops a table if it exists.
     * 
     * @param string $tableName Table name
     * @return bool
     */
    public function dropTable(string $tableName): bool
    {
        try {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $this->db->exec("DROP TABLE IF EXISTS `{$tableName}`;");
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to drop table {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drops all tables in the active MySQL database.
     * 
     * @return bool
     */
    public function dropAllTables(): bool
    {
        try {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($tables as $table) {
                $this->db->exec("DROP TABLE IF EXISTS `{$table}`;");
            }
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to drop all tables: " . $e->getMessage());
            return false;
        }
    }
}
