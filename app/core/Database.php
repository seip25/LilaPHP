<?php

namespace Core;

use PDO;
use PDOException;

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

    public function __construct(
        string $provider="mysql",
        string $host = 'localhost',
        string $dbUser = 'root',
        string $dbPassword = '',
        string $dbName = 'db_test',
        int $port = 0,
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

    public function getConnection(): ?PDO
    {
        return $this->db;
    }

    private function getDsn(): string
    {
        return match ($this->provider) {
            'mysql' => "mysql:host={$this->host};dbname={$this->dbName};port={$this->port};charset=utf8mb4",
            'pgsql' => "pgsql:host={$this->host};dbname={$this->dbName};port={$this->port}",
            'sqlite' => "sqlite:{$this->dbName}",
            default => throw new PDOException("Unsupported provider: {$this->provider}")
        };
    }

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
                $msg = "Connection error (attempt $attempt/{$this->maxAttempts}): " . $e->getMessage();
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
}
