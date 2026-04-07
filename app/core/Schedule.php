<?php

namespace Core;

class Schedule
{
    private static array $tasks = [];
    
    private mixed $task;
    private bool $shouldRun = false;

    private function __construct(mixed $task)
    {
        $this->task = $task;
    }

    public static function call(callable $callback): self
    {
        $instance = new self($callback);
        self::$tasks[] = $instance;
        return $instance;
    }

    public static function command(string $command): self
    {
        $instance = new self(function () use ($command) {
            $cliPath = dirname(__DIR__) . '/cli.php';
            exec("php {$cliPath} {$command}");
        });
        self::$tasks[] = $instance;
        return $instance;
    }

    public function everyMinute(): self
    {
        $this->shouldRun = true;
        return $this;
    }

    public function hourly(): self
    {
        $this->shouldRun = (date('i') === '00');
        return $this;
    }

    public function dailyAt(string $time): self
    {
        $this->shouldRun = (date('H:i') === $time);
        return $this;
    }

    public static function run(): array
    {
        $results = [];
        foreach (self::$tasks as $task) {
            if ($task->shouldRun) {
                try {
                    $callback = $task->task;
                    $callback();
                    $results[] = true;
                } catch (\Throwable $e) {
                    Logger::error("Schedule Task Error: " . $e->getMessage());
                    $results[] = false;
                }
            }
        }
        return $results;
    }
}
