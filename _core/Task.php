<?php

namespace Core;

/**
 * Background Task & Job Queue Engine (`Core\Task`).
 * 
 * Dispatches asynchronous jobs either into a Redis list (`lilaphp:jobs`) for background workers (`php cli.php task:work`)
 * or runs them detached via OS background process if Redis is unconfigured.
 * 
 * @package Core
 */
class Task
{
    private static string $queueKey = 'lilaphp:jobs';

    /**
     * Dispatches a background job payload asynchronously.
     * 
     * @param string $jobName Identifier or Class name of the job
     * @param array $payload Associative data payload for the job
     * @param bool $useRedis If true, attempts to push to Redis worker queue first
     * @return bool True if dispatched successfully
     * @example \Core\Task::dispatch('send_welcome_email', ['userId' => 42]);
     */
    public static function dispatch(string $jobName, array $payload = [], bool $useRedis = true): bool
    {
        $jobData = [
            'id' => bin2hex(random_bytes(8)),
            'job' => $jobName,
            'payload' => $payload,
            'created_at' => time()
        ];
        $encoded = json_encode($jobData, JSON_UNESCAPED_UNICODE);

        if ($useRedis) {
            $redis = Cache::getRedis();
            if ($redis !== null) {
                try {
                    $redis->lPush(self::$queueKey, $encoded);
                    Logger::info("Job `{$jobName}` queued to Redis (`" . self::$queueKey . "`).", ['id' => $jobData['id']]);
                    return true;
                } catch (\Throwable $e) {
                    Logger::warning("Redis queue push failed, falling back to detached background process: " . $e->getMessage());
                }
            }
        }

        // Fallback: Detached async process spawning via CLI
        $cliPath = Config::$DIR_PROJECT !== '' ? Config::$DIR_PROJECT . '/cli.php' : dirname(__DIR__) . '/cli.php';
        $cmd = sprintf('php %s task:run %s %s > /dev/null 2>&1 &', escapeshellarg($cliPath), escapeshellarg($jobName), escapeshellarg(base64_encode($encoded)));

        @exec($cmd);
        Logger::info("Job `{$jobName}` dispatched as detached OS process.", ['id' => $jobData['id']]);
        return true;
    }

    /**
     * Consumes and processes jobs from the Redis queue.
     * 
     * @param callable|null $handler Custom job processor function `fn(string $jobName, array $payload): bool`
     * @param int $maxJobs Limit of jobs to process before exiting (0 for continuous loop)
     * @return int Number of processed jobs
     * @example \Core\Task::work(fn($job, $data) => ...);
     */
    public static function work(?callable $handler = null, int $maxJobs = 0): int
    {
        $redis = Cache::getRedis();
        if ($redis === null) {
            Logger::error("Task worker cannot start: Redis cluster connection unavailable.");
            return 0;
        }

        $processed = 0;
        while (true) {
            try {
                $raw = $redis->rPop(self::$queueKey);
                if (!$raw) {
                    if ($maxJobs > 0 && $processed >= $maxJobs) {
                        break;
                    }
                    usleep(500000); // Sleep 500ms when queue is empty
                    continue;
                }

                $jobData = json_decode($raw, true);
                if (!is_array($jobData) || !isset($jobData['job'])) {
                    continue;
                }

                $jobName = (string) $jobData['job'];
                $payload = (array) ($jobData['payload'] ?? []);

                if ($handler !== null) {
                    $handler($jobName, $payload);
                } else {
                    self::execute($jobName, $payload);
                }

                $processed++;
                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    break;
                }
            } catch (\Throwable $e) {
                Logger::error("Task worker execution exception: " . $e->getMessage());
                usleep(1000000);
            }
        }

        return $processed;
    }

    /**
     * Internal executor resolving jobs from `backend/jobs/` or handler class.
     * 
     * @param string $jobName Name of job script or class
     * @param array $payload Data payload
     * @return bool
     */
    public static function execute(string $jobName, array $payload): bool
    {
        Logger::info("Executing task `{$jobName}`...", ['payload' => $payload]);

        $jobScript = Config::$DIR_BACKEND . "/jobs/{$jobName}.php";
        if (file_exists($jobScript)) {
            try {
                require $jobScript;
                return true;
            } catch (\Throwable $e) {
                Logger::error("Job `{$jobName}` failed: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }
}
