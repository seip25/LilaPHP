<?php

namespace Core;

/**
 * Base TestCase for the Custom CLI Test Runner
 * 
 * Provides assertions and test environment.
 * 
 * @package Core
 */
abstract class TestCase
{
    private int $assertions = 0;

    /**
     * Setup executed before each test
     */
    protected function setUp(): void
    {
    }

    /**
     * Teardown executed after each test
     */
    protected function tearDown(): void
    {
    }

    /**
     * Run all methods starting with 'test'
     */
    public function runTests(): array
    {
        $ref = new \ReflectionClass($this);
        $results = [];

        foreach ($ref->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'test')) {
                $this->setUp();
                $time = microtime(true);
                $error = null;
                $passed = false;

                try {
                    $method->invoke($this);
                    $passed = true;
                } catch (\Throwable $e) {
                    $error = $e->getMessage() . " on line " . $e->getLine();
                }

                $duration = microtime(true) - $time;
                $this->tearDown();

                $results[] = [
                    'name' => $method->getName(),
                    'passed' => $passed,
                    'error' => $error,
                    'duration' => $duration
                ];
            }
        }

        return $results;
    }

    protected function assertTrue(bool $condition, string $message = 'Failed asserting that value is true'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \Exception($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Failed asserting that value is false'): void
    {
        $this->assertions++;
        if ($condition) {
            throw new \Exception($message);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = 'Failed asserting that values are equal'): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new \Exception($message . " Expected " . json_encode($expected) . " but got " . json_encode($actual));
        }
    }

    protected function assertContains(mixed $needle, array|string $haystack, string $message = 'Failed asserting that item is in array/string'): void
    {
        $this->assertions++;
        if (is_array($haystack) && !in_array($needle, $haystack)) {
            throw new \Exception($message);
        }
        if (is_string($haystack) && is_string($needle) && strpos($haystack, $needle) === false) {
            throw new \Exception($message);
        }
    }

    public function getAssertionCount(): int
    {
        return $this->assertions;
    }
}
