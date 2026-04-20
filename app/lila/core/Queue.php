<?php

namespace Core;

use Models\JobModel;

/**
 * Queue System
 * 
 * Helper for dispatching jobs to the background queue.
 * Requires JobModel and 'jobs' table migrated.
 * 
 * @package Core
 */
class Queue
{
    /**
     * Push a new job onto the queue
     * 
     * @param string $handlerClass Class name that contains a `handle(array $payload)` method
     * @param array $payloadData Data to pass to the handler
     * @return bool True if successfully pushed
     * 
     * @example
     * ```php
     * Queue::push(SendEmailJob::class, ['to' => 'test@test.com', 'subject' => 'Welcome']);
     * ```
     */
    public static function push(string $handlerClass, array $payloadData = []): bool
    {
        $job = new JobModel(data: [
            'handler' => $handlerClass,
            'payload' => json_encode(value: $payloadData),
            'attempts' => 0,
            'status' => 0
        ]);
        
        return $job->save();
    }
}
