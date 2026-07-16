<?php

namespace Cli;

use Core\Task;

/**
 * CLI Background Worker Command (`php cli.php task:work` or `task:run`).
 * 
 * Consumes Redis job queues (`lilaphp:jobs`) or executes single dispatched background tasks.
 * 
 * @package Cli
 */
class TaskWork extends Command
{
    /**
     * Executes the task worker loop or individual task.
     * 
     * @param array $args Command arguments (`work` or `run`)
     * @return int
     */
    public function run(array $args): int
    {
        $command = strtolower($_SERVER['argv'][1] ?? 'task:work');

        if ($command === 'task:run' || ($args[0] ?? '') === 'run') {
            $isRunKeyword = ($args[0] ?? '') === 'run';
            $jobName = trim($isRunKeyword ? ($args[1] ?? '') : ($args[0] ?? ''));
            $encodedPayload = trim($isRunKeyword ? ($args[2] ?? '') : ($args[1] ?? ''));
            if ($jobName === '') {
                $this->error("Missing job name for `task:run`.");
                return 1;
            }
            $payloadData = [];
            if ($encodedPayload !== '') {
                $decoded = base64_decode($encodedPayload, true);
                if ($decoded !== false) {
                    $json = json_decode($decoded, true);
                    $payloadData = is_array($json) ? ($json['payload'] ?? $json) : [];
                }
            }
            $this->info("Executing detached job `{$jobName}`...");
            return Task::execute($jobName, $payloadData) ? 0 : 1;
        }

        $this->banner("LilaPHP Background Task Worker");
        $this->info("Listening for background jobs on Redis queue `lilaphp:jobs`... (Press Ctrl+C to exit)");

        $count = Task::work(null, 0);
        $this->success("Worker stopped. Processed {$count} jobs.");
        return 0;
    }
}
