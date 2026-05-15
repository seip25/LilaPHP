<?php

namespace Cli;

use Core\Config;
use Core\TestCase;

/**
 * Test Runner Command
 * 
 * Runs all Test suite files ending with Test.php
 * 
 * @package Cli
 */
class Test extends Command
{
    /**
     * Execute the command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $dir = dirname(Config::$DIR_PROJECT);
        $this->info("Running test suite in {$dir}...");

        $testFiles = $this->findTestFiles($dir);
        if (empty($testFiles)) {
            $this->warning("No test files found (*Test.php or test_*.php).");
            return;
        }

        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $assertions = 0;

        foreach ($testFiles as $file) {
            $classesBefore = get_declared_classes();
            require_once $file;
            $classesAfter = get_declared_classes();
            $newClasses = array_diff($classesAfter, $classesBefore);

            foreach ($newClasses as $class) {
                if (is_subclass_of($class, TestCase::class)) {
                    $this->line("\n\033[1mTesting {$class}...\033[0m");
                    /** @var TestCase $instance */
                    $instance = new $class();
                    $results = $instance->runTests();
                    $assertions += $instance->getAssertionCount();

                    foreach ($results as $res) {
                        $totalTests++;
                        if ($res['passed']) {
                            $totalPassed++;
                            $dur = round($res['duration'] * 1000, 2);
                            echo "  \033[32m✓ {$res['name']}\033[0m ({$dur}ms)\n";
                        } else {
                            $totalFailed++;
                            echo "  \033[31m✗ {$res['name']}\033[0m\n";
                            echo "    \033[31m{$res['error']}\033[0m\n";
                        }
                    }
                }
            }
        }

        $this->line("\n\033[1mTest Summary:\033[0m");
        if ($totalFailed > 0) {
            $this->error("Tests: {$totalTests}, Passed: {$totalPassed}, Failed: {$totalFailed}, Assertions: {$assertions}");
        } else {
            $this->success("OK ({$totalTests} tests, {$assertions} assertions)");
        }
    }

    private function findTestFiles(string $dir): array
    {
        $testFiles = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        
        foreach ($iterator as $info) {
            if ($info->isFile()) {
                $filename = $info->getFilename();
                $path = $info->getPathname();
                
                // Exclude core folders
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
                    str_contains($path, DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR) ||
                    str_contains($path, DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                if (str_ends_with($filename, 'Test.php') || str_starts_with($filename, 'test_')) {
                    $testFiles[] = $path;
                }
            }
        }
        return $testFiles;
    }
}
