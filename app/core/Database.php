<?php

namespace Core;

use PDO;
use PDOException;

/**
 * Database Connection and Migration Class
 * 
 * Handles PDO database connections with retry logic and provides
 * methods for database and table management for migrations.
 * 
 * @package Core
 */
class Database
{
    private ?PDO $db = null;
    private string $provider;
    private string $host;
    private string $dbName;
    private string $dbUser;
    private string $dbPassword;
    private string $port;
    private int $maxAttempts;

    /**
     * Initialize database connection
     * 
     * @param string $provider Database provider (mysql, pgsql, sqlite)
     * @param string $host Database host
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbName Database name
     * @param int $port Database port (default: 3306 for MySQL, 5432 for PostgreSQL)
     * @param int $maxAttempts Maximum connection retry attempts
     */
    public function __construct(
        string $provider = "mysql",
        ?string $host = 'localhost',
        ?string $dbUser = 'root',
        ?string $dbPassword = '',
        ?string $dbName = 'lila',
        ?int $port = 0,
        int $maxAttempts = 5
    ) {
        $this->provider = strtolower($provider);
        $this->host = $host;
        $this->dbUser = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbName = $dbName;
        $this->maxAttempts = $maxAttempts;
        $this->port = $port ?: ($this->provider === 'pgsql' ? 5432 : 3306);
        $this->db = $this->connectWithRetry();
    }

    /**
     * Get the PDO connection instance
     * 
     * @return PDO|null PDO connection or null on failure
     */
    public function getConnection(): ?PDO
    {
        return $this->db;
    }

    /**
     * Get DSN string for PDO connection
     * 
     * @return string DSN string
     * @throws PDOException If provider is not supported
     */
    private function getDsn(): string
    {
        return match ($this->provider) {
            'mysql' => "mysql:host={$this->host};dbname={$this->dbName};port={$this->port};charset=utf8mb4",
            'pgsql' => "pgsql:host={$this->host};dbname={$this->dbName};port={$this->port}",
            'sqlite' => "sqlite:" . Config::$DIR_PROJECT . "/lila/" . $this->dbName . ".sqlite",
            default => throw new PDOException("Unsupported provider: {$this->provider}")
        };
    }

    /**
     * Connect to database with retry logic
     * 
     * @return PDO|null PDO connection or null on failure
     */
    private function connectWithRetry(): ?PDO
    {
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            try {
                $dsn = $this->getDsn();
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $connection = new PDO(
                    dsn: $dsn,
                    username: $this->dbUser ?: null,
                    password: $this->dbPassword ?: null,
                    options: $options
                );

                return $connection;
            } catch (PDOException $e) {
                $attempt++;
                $dns = $this->getDsn();
                $file = $e->getFile();
                $code = $e->getTraceAsString();
                $message = $e->getMessage();
                $error = "{$message} \n{$dns} \n{$code}  {$file}\n\n\n";
                $msg = "Connection error (attempt $attempt/{$this->maxAttempts}): \n" . $error;
                Logger::error(message: $msg);

                if (Config::$DEBUG) {
                    echo $msg . PHP_EOL;
                }

                if ($attempt < $this->maxAttempts) {
                    sleep($attempt);
                } else {
                    Logger::error(message: "Failed to connect after {$this->maxAttempts} attempts.");
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Create database if it doesn't exist
     * 
     * @param string $dbName Database name to create
     * @return bool True on success, false on failure
     */
    public function createDatabase(string $dbName): bool
    {
        try {
            $dsn = match ($this->provider) {
                'mysql' => "mysql:host={$this->host};port={$this->port};charset=utf8mb4",
                'pgsql' => "pgsql:host={$this->host};port={$this->port}",
                'sqlite' => "sqlite:{$dbName}",
                default => throw new PDOException("Unsupported provider: {$this->provider}")
            };

            $pdo = new PDO($dsn, $this->dbUser, $this->dbPassword);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($this->provider === 'sqlite') {
                return true;
            }

            $sql = match ($this->provider) {
                'mysql' => "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
                'pgsql' => "CREATE DATABASE \"{$dbName}\" ENCODING 'UTF8'",
                default => throw new PDOException("Unsupported provider: {$this->provider}")
            };

            if ($this->provider === 'pgsql') {
                $result = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
                if ($result->rowCount() > 0) {
                    return true;
                }
            }

            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to create database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     * 
     * @param string $tableName Table name to check
     * @return bool True if table exists, false otherwise
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $sql = match ($this->provider) {
                'mysql' => "SHOW TABLES LIKE '{$tableName}'",
                'pgsql' => "SELECT 1 FROM information_schema.tables WHERE table_name = '{$tableName}'",
                'sqlite' => "SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$tableName}'",
                default => throw new PDOException("Unsupported provider: {$this->provider}")
            };

            $result = $this->db->query($sql);
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            Logger::error("Failed to check table existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create table from schema definition
     * 
     * @param string $tableName Table name
     * @param array $schema Schema definition from BaseModel::getSchema()
     * @return bool True on success, false on failure
     */
    public function createTable(string $tableName, array $schema): bool
    {
        try {
            $columns = [];
            $primaryKeys = [];
            $indexes = [];
            $uniques = [];

            foreach ($schema as $columnName => $definition) {
                $columnDef = $this->buildColumnDefinition($columnName, $definition);
                $columns[] = $columnDef;

                if ($definition['primaryKey']) {
                    $primaryKeys[] = $columnName;
                }

                if ($definition['index'] && !$definition['primaryKey']) {
                    $indexes[] = $columnName;
                }

                if ($definition['unique'] && !$definition['primaryKey']) {
                    $uniques[] = $columnName;
                }
            }

            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";

            $sql .= "  " . implode(",\n  ", $columns);


            if (!empty($primaryKeys) && $this->provider !== "sqlite") {
                $sql .= ",\n  PRIMARY KEY (" . implode(', ', array_map(fn($k) => "`{$k}`", $primaryKeys)) . ")";


                foreach ($uniques as $uniqueCol) {
                    $sql .= ",\n  UNIQUE KEY `unique_{$uniqueCol}` (`{$uniqueCol}`)";
                }

                foreach ($indexes as $indexCol) {
                    $sql .= ",\n  KEY `idx_{$indexCol}` (`{$indexCol}`)";
                }
            }
            $sql .= "\n)";

            if ($this->provider === 'mysql') {
                $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            }
            if ($this->provider === 'pgsql') {
                $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            }
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to create table {$tableName}: " . $e->getMessage());
            if (Config::$DEBUG) {
                echo "SQL Error: " . $e->getMessage() . PHP_EOL;
            }
            return false;
        }
    }

    /**
     * Build column definition SQL
     * 
     * @param string $columnName Column name
     * @param array $definition Column definition
     * @return string SQL column definition
     */
    private function buildColumnDefinition(string $columnName, array $definition): string
    {
        $type = $this->mapColumnType($definition['type'], $definition['length']);

        $sql = "`{$columnName}` {$type}";


        if (!$this->provider === "sqlite" && ($definition['unsigned'] && in_array($definition['type'], ['int', 'bigint', 'smallint', 'tinyint']))) {
            $sql .= " UNSIGNED";
        }

        if (!$definition['nullable']) {
            if ($this->provider === "sqlite") {

                $sql .= $definition['autoIncrement'] ? " " : " NOT NULL";
            } else {
                $sql .= " NOT NULL";
            }
        } else {
            $sql .= " NULL";
        }

        if ($definition['autoIncrement']) {
            $sql .= $this->provider === "sqlite" ? " PRIMARY KEY AUTOINCREMENT" : " AUTO_INCREMENT";
        } elseif ($definition['default'] !== null) {
            if ($this->provider === "sqlite") {
                $sql .= $definition["default"] == "TEXT" ? " DEFAULT ''" : " DEFAULT " . $definition['default'];
            } elseif (in_array(strtoupper($definition['default']), ['CURRENT_TIMESTAMP', 'NOW()'])) {
                $sql .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif (is_string($definition['default'])) {
                $sql .= " DEFAULT '" . addslashes($definition['default']) . "'";
            } else {
                $sql .= " DEFAULT " . $definition['default'];
            }
        }

        if ($definition['comment'] && $this->provider !== "sqlite") {
            $sql .= " COMMENT '" . addslashes($definition['comment']) . "'";
        }

        return $sql;
    }

    /**
     * Map generic column types to provider-specific types
     * 
     * @param string $type Generic type
     * @param int|null $length Column length
     * @return string Provider-specific type
     */
    private function mapColumnType(string $type, ?int $length): string
    {
        $type = strtolower($type);

        if ($this->provider === 'sqlite') {
            return match ($type) {
                'int', 'integer' => 'INTEGER',
                'bigint' => 'INTEGER',
                'smallint' => 'INTEGER',
                'tinyint' => 'INTEGER',
                'varchar', 'string' => 'TEXT',
                'char' => 'TEXT',
                'text' => 'TEXT',
                'longtext' => 'TEXT',
                'mediumtext' => 'TEXT',
                'boolean', 'bool' => 'INTEGER',
                'date' => 'TEXT',
                'datetime' => 'TEXT',
                'timestamp' => 'TEXT',
                'time' => 'TEXT',
                'decimal' => 'REAL',
                'json' => 'TEXT',
            };
        }

        return match ($type) {
            'int', 'integer' => 'INT',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'tinyint' => 'TINYINT',
            'varchar', 'string' => 'VARCHAR(' . ($length ?? 255) . ')',
            'char' => 'CHAR(' . ($length ?? 1) . ')',
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'boolean', 'bool' => $this->provider === 'pgsql' ? 'BOOLEAN' : 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'decimal' => 'DECIMAL(' . ($length ?? 10) . ',2)',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'json' => $this->provider === 'mysql' ? 'JSON' : 'TEXT',
            default => strtoupper($type)
        };
    }

    /**
     * Get table columns
     * 
     * @param string $tableName Table name
     * @return array Array of column information
     */
    public function getColumns(string $tableName): array
    {
        try {
            $sql = match ($this->provider) {
                'mysql' => "SHOW COLUMNS FROM `{$tableName}`",
                'pgsql' => "SELECT column_name, data_type, is_nullable, column_default 
                            FROM information_schema.columns 
                            WHERE table_name = '{$tableName}'",
                'sqlite' => "PRAGMA table_info({$tableName})",
                default => throw new PDOException("Unsupported provider: {$this->provider}")
            };

            $result = $this->db->query($sql);
            return $result->fetchAll();
        } catch (PDOException $e) {
            Logger::error("Failed to get columns for table {$tableName}: " . $e->getMessage());
            return [];
        }
    }
    private function replaceColumn(array|string $column, string|array $typeToChanged = "VARCHAR", string $typeToReplace = "TEXT"): string
    {
        if (is_array($typeToChanged)) {
            foreach ($typeToChanged as $type) {
                if (strpos($column, $type)) {
                    $column = str_ireplace($type, $typeToReplace, $column);
                }
            }
        } elseif (strpos($column, $typeToChanged)) {
            $column = str_ireplace($typeToChanged, $typeToReplace, $column);
        }
        $column = str_replace("TEXT", "TEXT", $column);
        return $column;
    }
}
