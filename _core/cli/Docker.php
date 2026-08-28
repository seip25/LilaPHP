<?php

namespace Cli;

use Core\Config;

/**
 * Docker Orchestration Engine (`php cli.php docker [action]`).
 * 
 * Manages multi-container clusters (`nginx`, `php-fpm`, `mysql`, `redis`) with dynamic
 * port bindings (`HTTP_PORT`, `DB_PORT`, `REDIS_PORT`) and dedicated `APP_ENV` profiles.
 * Includes smart MySQL and Redis CLI shortcuts for common operations.
 * 
 * @package Cli
 */
class Docker extends Command
{
    private string $envFile = './backend/.env';

    /**
     * Executes Docker cluster management workflows.
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    public function run(array $args): int
    {
        $action = strtolower($args[0] ?? 'ps');
        $this->banner("LilaPHP Docker Cluster Orchestration ({$action})");

        return match ($action) {
            'dev' => $this->launchCluster('development'),
            'prod', 'production' => $this->launchCluster('production'),
            'stop', 'down' => $this->execShell("docker compose --env-file {$this->envFile} down"),
            'ps', 'status' => $this->showStatus(),
            'stats' => $this->showStats(),
            'logs' => $this->execShell("docker compose --env-file {$this->envFile} logs -f --tail=100"),
            'exec-php' => $this->execShell("docker compose --env-file {$this->envFile} exec php bash"),
            'exec-mysql' => $this->execMysql($args),
            'exec-redis', 'redis-cli' => $this->execRedis($args),
            'mysql' => $this->smartMysql($args),
            'redis' => $this->smartRedis($args),
            'clean' => $this->cleanCluster(),
            default => $this->printUsage()
        };
    }

    /**
     * Returns the TTY flag for Docker exec commands based on terminal detection.
     * 
     * @return string
     */
    private function ttyFlag(): string
    {
        return (function_exists('posix_isatty') && posix_isatty(STDOUT)) ? '' : '-T ';
    }

    /**
     * Builds a Docker exec command targeting the MySQL container with credentials.
     * 
     * @param string $suffix Additional mysql CLI arguments
     * @return string
     */
    private function buildMysqlCmd(string $suffix): string
    {
        $dbUser = Config::$DB_USER;
        $dbPass = Config::$DB_PASSWORD;
        $dbName = Config::$DB_NAME;
        $tty = $this->ttyFlag();

        return "docker compose --env-file {$this->envFile} exec -e MYSQL_PWD="
            . escapeshellarg($dbPass) . " {$tty}mysql mysql -u{$dbUser} {$dbName} {$suffix}";
    }

    /**
     * Builds a Docker exec command targeting the Redis container.
     * 
     * @param string $suffix Additional redis-cli arguments
     * @return string
     */
    private function buildRedisCmd(string $suffix = ''): string
    {
        $tty = $this->ttyFlag();
        $base = "docker compose --env-file {$this->envFile} exec {$tty}redis redis-cli";
        return $suffix !== '' ? "{$base} {$suffix}" : $base;
    }

    /**
     * Executes raw SQL queries or opens an interactive MySQL console.
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    private function execMysql(array $args): int
    {
        array_shift($args);
        $dbName = Config::$DB_NAME;

        if (!empty($args)) {
            $query = implode(' ', $args);
            $this->info("Executing query on MySQL database `{$dbName}`...");
            if (!str_starts_with(trim($query), '-')) {
                return $this->execShell($this->buildMysqlCmd('-e ' . escapeshellarg($query)));
            }
            return $this->execShell($this->buildMysqlCmd($query));
        }

        $this->info("Opening interactive MySQL console for database `{$dbName}`...");
        return $this->execShell($this->buildMysqlCmd(''));
    }

    /**
     * Executes raw Redis commands or opens an interactive redis-cli console.
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    private function execRedis(array $args): int
    {
        array_shift($args);

        if (!empty($args)) {
            $redisCmd = implode(' ', array_map('escapeshellarg', $args));
            $this->info("Executing Redis command...");
            return $this->execShell($this->buildRedisCmd($redisCmd));
        }

        $this->info("Opening interactive Redis console...");
        return $this->execShell($this->buildRedisCmd());
    }

    /**
     * Smart MySQL shortcuts that translate human-friendly subcommands into SQL queries.
     * 
     * Supported shortcuts:
     *   tables, describe <table>, count <table>, find <table> <id>,
     *   all <table> [limit], last <table> [n], first <table> [n],
     *   where <table> <col> <val>, delete <table> <id>, truncate <table>
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    private function smartMysql(array $args): int
    {
        array_shift($args);
        $sub = strtolower($args[0] ?? '');
        $table = $args[1] ?? '';

        $query = match ($sub) {
            'tables' => 'SHOW TABLES',
            'describe', 'desc', 'schema' => $table !== '' ? "DESCRIBE `{$table}`" : null,
            'count' => $table !== '' ? "SELECT COUNT(*) AS total FROM `{$table}`" : null,
            'find', 'get' => $this->buildFindQuery($table, $args[2] ?? null),
            'all', 'list' => $this->buildAllQuery($table, $args[2] ?? '25'),
            'last' => $this->buildLastQuery($table, $args[2] ?? '10'),
            'first' => $table !== '' ? "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT " . ((int)($args[2] ?? 10)) : null,
            'where' => $this->buildWhereQuery($table, $args[2] ?? '', $args[3] ?? ''),
            'delete' => $this->buildDeleteQuery($table, $args[2] ?? null),
            'truncate' => $table !== '' ? "TRUNCATE TABLE `{$table}`" : null,
            default => null
        };

        if ($query === null) {
            return $this->printMysqlShortcuts();
        }

        $this->info("Query: {$query}");
        return $this->execShell($this->buildMysqlCmd('-e ' . escapeshellarg($query)));
    }

    /**
     * Builds a SELECT query to find a record by primary key.
     * 
     * @param string $table Table name
     * @param string|null $id Record ID
     * @return string|null
     */
    private function buildFindQuery(string $table, ?string $id): ?string
    {
        if ($table === '' || $id === null) {
            return null;
        }
        return "SELECT * FROM `{$table}` WHERE id = " . ((int) $id) . " LIMIT 1";
    }

    /**
     * Builds a SELECT query to list all records with optional limit.
     * 
     * @param string $table Table name
     * @param string $limit Maximum number of rows (defaults to 25)
     * @return string|null
     */
    private function buildAllQuery(string $table, string $limit): ?string
    {
        if ($table === '') {
            return null;
        }
        return "SELECT * FROM `{$table}` LIMIT " . ((int) $limit ?: 25);
    }

    /**
     * Builds a SELECT query to retrieve the most recent records by ID descending.
     * 
     * @param string $table Table name
     * @param string $count Number of rows (defaults to 10)
     * @return string|null
     */
    private function buildLastQuery(string $table, string $count): ?string
    {
        if ($table === '') {
            return null;
        }
        return "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT " . ((int) $count ?: 10);
    }

    /**
     * Builds a SELECT query filtered by a column value.
     * 
     * @param string $table Table name
     * @param string $column Column name to filter
     * @param string $value Value to match
     * @return string|null
     */
    private function buildWhereQuery(string $table, string $column, string $value): ?string
    {
        if ($table === '' || $column === '' || $value === '') {
            return null;
        }
        $safeValue = addslashes($value);
        return "SELECT * FROM `{$table}` WHERE `{$column}` = '{$safeValue}' LIMIT 50";
    }

    /**
     * Builds a DELETE query for a specific record by primary key.
     * 
     * @param string $table Table name
     * @param string|null $id Record ID
     * @return string|null
     */
    private function buildDeleteQuery(string $table, ?string $id): ?string
    {
        if ($table === '' || $id === null) {
            return null;
        }
        return "DELETE FROM `{$table}` WHERE id = " . ((int) $id) . " LIMIT 1";
    }

    /**
     * Displays available MySQL smart shortcuts.
     * 
     * @return int
     */
    private function printMysqlShortcuts(): int
    {
        echo PHP_EOL;
        echo "\033[33mMySQL Smart Shortcuts:\033[0m" . PHP_EOL;
        echo "  \033[36mtables\033[0m                         Show all tables" . PHP_EOL;
        echo "  \033[36mdescribe\033[0m <table>               Describe table structure" . PHP_EOL;
        echo "  \033[36mcount\033[0m <table>                  Count records in table" . PHP_EOL;
        echo "  \033[36mfind\033[0m <table> <id>              Find record by ID" . PHP_EOL;
        echo "  \033[36mall\033[0m <table> [limit]            List records (default: 25)" . PHP_EOL;
        echo "  \033[36mlast\033[0m <table> [n]               Show last N records (default: 10)" . PHP_EOL;
        echo "  \033[36mfirst\033[0m <table> [n]              Show first N records (default: 10)" . PHP_EOL;
        echo "  \033[36mwhere\033[0m <table> <col> <value>    Filter by column value" . PHP_EOL;
        echo "  \033[36mdelete\033[0m <table> <id>            Delete record by ID" . PHP_EOL;
        echo "  \033[36mtruncate\033[0m <table>               Empty entire table" . PHP_EOL;
        echo PHP_EOL;
        echo "\033[2mExamples:\033[0m" . PHP_EOL;
        echo "  php cli.php docker mysql tables" . PHP_EOL;
        echo "  php cli.php docker mysql find users 3" . PHP_EOL;
        echo "  php cli.php docker mysql where users role admin" . PHP_EOL;
        echo "  php cli.php docker mysql last orders 20" . PHP_EOL;
        echo PHP_EOL;
        return 1;
    }

    /**
     * Smart Redis shortcuts that translate human-friendly subcommands into Redis commands.
     * 
     * Supported shortcuts:
     *   ping, keys [pattern], get <key>, set <key> <value>, del <key>,
     *   info [section], flush, ttl <key>, type <key>, logs [n], jobs [n],
     *   dbsize, monitor
     * 
     * @param array $args CLI arguments list
     * @return int
     */
    private function smartRedis(array $args): int
    {
        array_shift($args);
        $sub = strtolower($args[0] ?? '');

        $redisCmd = match ($sub) {
            'ping' => 'PING',
            'keys' => 'KEYS ' . escapeshellarg($args[1] ?? '*'),
            'get' => isset($args[1]) ? 'GET ' . escapeshellarg($args[1]) : null,
            'set' => isset($args[1], $args[2]) ? 'SET ' . escapeshellarg($args[1]) . ' ' . escapeshellarg($args[2]) : null,
            'del', 'delete', 'rm' => isset($args[1]) ? 'DEL ' . escapeshellarg($args[1]) : null,
            'info' => 'INFO' . (isset($args[1]) ? ' ' . escapeshellarg($args[1]) : ''),
            'flush' => 'FLUSHDB',
            'ttl' => isset($args[1]) ? 'TTL ' . escapeshellarg($args[1]) : null,
            'type' => isset($args[1]) ? 'TYPE ' . escapeshellarg($args[1]) : null,
            'logs' => 'LRANGE lilaphp:logs 0 ' . ((int) ($args[1] ?? 50) - 1),
            'jobs' => 'LRANGE lilaphp:jobs 0 ' . ((int) ($args[1] ?? 20) - 1),
            'dbsize' => 'DBSIZE',
            'monitor' => 'MONITOR',
            default => null
        };

        if ($redisCmd === null) {
            return $this->printRedisShortcuts();
        }

        $this->info("Redis: {$redisCmd}");
        return $this->execShell($this->buildRedisCmd($redisCmd));
    }

    /**
     * Displays available Redis smart shortcuts.
     * 
     * @return int
     */
    private function printRedisShortcuts(): int
    {
        echo PHP_EOL;
        echo "\033[33mRedis Smart Shortcuts:\033[0m" . PHP_EOL;
        echo "  \033[36mping\033[0m                           Test connection" . PHP_EOL;
        echo "  \033[36mkeys\033[0m [pattern]                 List keys (default: *)" . PHP_EOL;
        echo "  \033[36mget\033[0m <key>                      Get value by key" . PHP_EOL;
        echo "  \033[36mset\033[0m <key> <value>              Set key-value pair" . PHP_EOL;
        echo "  \033[36mdel\033[0m <key>                      Delete a key" . PHP_EOL;
        echo "  \033[36mttl\033[0m <key>                      Check time-to-live" . PHP_EOL;
        echo "  \033[36mtype\033[0m <key>                     Check key data type" . PHP_EOL;
        echo "  \033[36minfo\033[0m [section]                 Server info (memory, stats, etc.)" . PHP_EOL;
        echo "  \033[36mdbsize\033[0m                         Total number of keys" . PHP_EOL;
        echo "  \033[36mlogs\033[0m [n]                       Show last N app logs (default: 50)" . PHP_EOL;
        echo "  \033[36mjobs\033[0m [n]                       Show pending job queue (default: 20)" . PHP_EOL;
        echo "  \033[36mmonitor\033[0m                        Stream all Redis commands in real-time" . PHP_EOL;
        echo "  \033[36mflush\033[0m                          Flush current database" . PHP_EOL;
        echo PHP_EOL;
        echo "\033[2mExamples:\033[0m" . PHP_EOL;
        echo "  php cli.php docker redis ping" . PHP_EOL;
        echo "  php cli.php docker redis keys \"session:*\"" . PHP_EOL;
        echo "  php cli.php docker redis get app:config" . PHP_EOL;
        echo "  php cli.php docker redis logs 100" . PHP_EOL;
        echo PHP_EOL;
        return 1;
    }

    /**
     * Boots the Docker cluster under a specific environment mode.
     * 
     * @param string $env Target environment ('development' or 'production')
     * @return int
     */
    private function launchCluster(string $env): int
    {
        $shortEnv = $env === 'production' ? 'prod' : 'dev';
        
        if ($env === 'production') {
            if (Config::$DEBUG_LOGGING_ENABLED) {
                echo PHP_EOL;
                $this->warning("⚠️  WARNING: `DEBUG_LOGGING_ENABLED` is currently set to TRUE in backend/.env!");
                $this->warning("   Debug logging will capture request metrics into Redis during production mode.");
                echo "\033[1;33mDo you want to proceed with production deployment anyway? [y/N or s/n]: \033[0m";
                $handle = fopen("php://stdin", "r");
                $answer = trim(fgets($handle) ?: '');
                $answerLower = strtolower($answer);
                if (!in_array($answerLower, ['y', 'yes', 's', 'si'], true)) {
                    $this->error("Deployment aborted by user. Disable `DEBUG_LOGGING_ENABLED=false` in backend/.env to remove this warning.");
                    return 1;
                }
                echo PHP_EOL;
            }

            $this->info("Optimizing environment configuration for production...");
            $this->execShell(PHP_BINARY . ' cli.php optimize');

            $this->info("Building React frontend assets for production...");
            $this->execShell("docker run --rm -v " . escapeshellarg(getcwd()) . ":/app -w /app node:20-alpine sh -c 'npm install && npm run build'");
            $this->execShell(PHP_BINARY . ' cli.php build:react');
        }

        $this->info("Starting cluster in `{$env}` mode using `docker/php/Dockerfile.{$shortEnv}`...");
        $this->info("Dynamic Ports Assigned: HTTP=" . Config::$HTTP_PORT . " | MySQL=" . Config::$DB_PORT . " | Redis=" . Config::$REDIS_PORT);

        // Pre-Flight Port Conflict Verification
        $runningContainers = trim((string) @shell_exec("docker compose --env-file {$this->envFile} ps -q 2>/dev/null"));
        if ($runningContainers === '') {
            $ports = [
                'HTTP Web' => Config::$HTTP_PORT,
                'MySQL Database' => Config::$DB_PORT,
                'Redis Cache' => Config::$REDIS_PORT
            ];
            foreach ($ports as $label => $port) {
                if (Doctor::isPortOccupied($port)) {
                    $this->warning("⚠️  Pre-Flight Warning: Port {$port} ({$label}) is occupied on host!");
                    $this->warning("   If this port is bound by a non-Docker service (e.g. Apache/local MySQL), container startup will fail.");
                }
            }
        }

        putenv("APP_ENV={$shortEnv}");
        $cmd = "docker compose --env-file {$this->envFile} up -d --build";
        $status = $this->execShell($cmd);

        if ($status === 0) {
            $this->success("Cluster deployed successfully.");
            $this->showStatus();
        }

        return $status;
    }

    /**
     * Displays running containers and port mappings.
     * 
     * @return int
     */
    private function showStatus(): int
    {
        $this->info("Current Cluster Status (`docker compose ps`):");
        $status = $this->execShell("docker compose --env-file {$this->envFile} ps");

        echo PHP_EOL;
        $this->info("🌐 Live Endpoints:");
        echo "  - Landing Dashboard: http://localhost:" . Config::$HTTP_PORT . "/" . PHP_EOL;
        echo "  - API Status JSON  : http://localhost:" . Config::$HTTP_PORT . "/api/" . PHP_EOL;
        echo PHP_EOL;

        return $status;
    }

    /**
     * Streams real-time Docker stats filtered specifically for LilaPHP project containers.
     * 
     * @return int
     */
    private function showStats(): int
    {
        $appName = strtolower(Config::$APP_NAME);
        $this->info("Streaming real-time Docker resource consumption filtered for `{$appName}` containers...");
        
        $cmd = "docker stats \$(docker compose --env-file {$this->envFile} ps -q 2>/dev/null)";
        return $this->execShell($cmd);
    }

    /**
     * Completely removes containers, volumes, and dangling images.
     * 
     * @return int
     */
    private function cleanCluster(): int
    {
        $this->warning("Stopping and cleaning all cluster containers and volumes...");
        return $this->execShell("docker compose --env-file {$this->envFile} down -v --remove-orphans");
    }

    /**
     * Displays complete usage instructions for all docker subcommands.
     * 
     * @return int
     */
    private function printUsage(): int
    {
        $this->error("Unknown docker action.");
        echo PHP_EOL;

        echo "\033[33m📦 Cluster Management:\033[0m" . PHP_EOL;
        echo "  \033[36mdev\033[0m                            Launch in Development Mode" . PHP_EOL;
        echo "  \033[36mprod\033[0m                           Launch in Production Mode" . PHP_EOL;
        echo "  \033[36mstop\033[0m                           Stop cluster containers" . PHP_EOL;
        echo "  \033[36mps\033[0m                             Show running containers and ports" . PHP_EOL;
        echo "  \033[36mstats\033[0m                          Stream real-time CPU/RAM stats" . PHP_EOL;
        echo "  \033[36mlogs\033[0m                           Tail real-time cluster logs" . PHP_EOL;
        echo "  \033[36mclean\033[0m                          Destroy containers and reset volumes" . PHP_EOL;
        echo PHP_EOL;

        echo "\033[33m🔌 Container Access:\033[0m" . PHP_EOL;
        echo "  \033[36mexec-php\033[0m                       Open bash inside PHP container" . PHP_EOL;
        echo "  \033[36mexec-mysql\033[0m [query]             Interactive MySQL console or raw SQL" . PHP_EOL;
        echo "  \033[36mexec-redis\033[0m [command]           Interactive Redis console or raw command" . PHP_EOL;
        echo PHP_EOL;

        echo "\033[33m🗃️  Smart MySQL (php cli.php docker mysql <shortcut>):\033[0m" . PHP_EOL;
        echo "  \033[36mtables\033[0m / \033[36mdescribe\033[0m / \033[36mcount\033[0m / \033[36mfind\033[0m / \033[36mall\033[0m / \033[36mlast\033[0m / \033[36mwhere\033[0m / \033[36mdelete\033[0m / \033[36mtruncate\033[0m" . PHP_EOL;
        echo "  Run \033[36mphp cli.php docker mysql\033[0m for full details" . PHP_EOL;
        echo PHP_EOL;

        echo "\033[33m🔴 Smart Redis (php cli.php docker redis <shortcut>):\033[0m" . PHP_EOL;
        echo "  \033[36mping\033[0m / \033[36mkeys\033[0m / \033[36mget\033[0m / \033[36mset\033[0m / \033[36mdel\033[0m / \033[36mttl\033[0m / \033[36minfo\033[0m / \033[36mlogs\033[0m / \033[36mjobs\033[0m / \033[36mmonitor\033[0m" . PHP_EOL;
        echo "  Run \033[36mphp cli.php docker redis\033[0m for full details" . PHP_EOL;
        echo PHP_EOL;

        return 1;
    }
}
