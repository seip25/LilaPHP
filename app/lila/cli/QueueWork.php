<?php

namespace Cli;

use Models\JobModel;
use Core\Database;

/**
 * Queue Worker Command
 * 
 * Consumes jobs from the jobs table and executes them in the background.
 * 
 * @package Cli
 */
class QueueWork extends Command
{
    /**
     * Execute the worker process
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->info("========================================");
        $this->info(" LilaPHP Queue Worker ");
        $this->info(" Waiting for background jobs... ");
        $this->info("========================================\n");

        if (!$this->db) {
            $this->connectDatabase(true);
        }

        if (!class_exists('Models\JobModel')) {
            $this->error("JobModel not found! Run 'php app/cli.php optimize' or create the model manually.");
            return;
        }
        
        // Wait for the migration to be ready
        try {
            JobModel::getTableName();
        } catch (\Throwable $e) {
            $this->error("Error interacting with JobModel. Make sure your database is connected and 'php app/cli.php migrate:create' was run.");
            return;
        }

        while (true) {
            try {
                // Find pending jobs
                $jobs = JobModel::where('status', '=', 0, false);
                
                if (empty($jobs)) {
                    sleep(3);
                    continue;
                }

                foreach ($jobs as $job) {
                    $this->processJob($job);
                }
                
            } catch (\PDOException $e) {
                // Database connection might have dropped during sleep
                $this->error("Database connection error: " . $e->getMessage());
                sleep(5); 
                $this->connectDatabase(true); // Re-connect
            } catch (\Throwable $e) {
                $this->error("Worker error: " . $e->getMessage());
                sleep(3);
            }
        }
    }

    /**
     * Process a single job
     * 
     * @param JobModel $job
     * @return void
     */
    private function processJob(JobModel $job): void
    {
        $this->info("[".date('Y-m-d H:i:s')."] Processing Job #{$job->id}: {$job->handler}");
        
        // Lock the job to prevent duplicate picking if multiple workers exist
        $job->status = 1; // Processing
        $job->save();

        try {
            $handlerClass = $job->handler;
            if (class_exists($handlerClass) && method_exists($handlerClass, 'handle')) {
                $handler = new $handlerClass();
                $payload = json_decode($job->payload, true) ?? [];
                
                // Call handle method
                $handler->handle($payload);

                // Assuming success if no exception was thrown
                $job->delete(logic: false); 
                $this->success("Job #{$job->id} processed successfully.");
            } else {
                throw new \Exception("Handler class {$handlerClass} not found or missing handle() method.");
            }
        } catch (\Throwable $e) {
            $job->status = 2; // Failed
            $job->error = $e->getMessage() . "\n" . $e->getTraceAsString();
            $job->attempts += 1;
            $job->save();
            
            $this->error("Job #{$job->id} failed: " . $e->getMessage());
        }
    }
}
