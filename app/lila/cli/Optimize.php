<?php

namespace Cli;

use Core\Config as CoreConfig;
use Core\Database;
use Dotenv\Dotenv;

/**
 * Optimization Command
 * 
 * Bundles all performance hardening tasks (config, models, assets) 
 * into a single unified command for production deployment.
 * 
 * @package Cli
 */
class Optimize extends Command
{
    /**
     * Execute the optimization process
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->info("========================================");
        $this->info("   LilaPHP Production Optimization      ");
        $this->info("========================================");

        // 0. Preliminary Cleanup
        $this->clearCache();

        // 1. Environment Caching
        $this->info("\n[1/6] Optimizing Environment Variables...");
        $configCmd = new Config();
        $configCmd->cache([]);

        // 2. Model Caching
        $this->info("\n[2/6] Optimizing Model Metadata...");
        $modelCmd = new Model();
        $modelCmd->cache([]);

        // 3. Route Caching
        $this->info("\n[3/6] Optimizing Route Cache...");
        $this->success("Route attribute and DI cache cleared.");

        // 4. Asset Minification
        $this->info("\n[4/6] Minifying Assets...");
        $minifyCmd = new Minify();
        $minifyCmd->execute([]);

        // 5. Vite Compilation
        $this->info("\n[5/6] Building Frontend Assets (React/Vite)...");
        $this->runViteBuild();

        // 6. Health Check
        $this->info("\n[6/6] Performing Production Health Check...");
        $this->runHealthCheck();

        $this->info("\n========================================");
        $this->success("Optimization completed successfully!");
        $this->info("Your application is now ready for scale.");
        $this->info("========================================\n");
    }

    /**
     * Clear all framework cache files
     * 
     * @return void
     */
    private function clearCache(): void
    {
        $this->info("\n[0/6] Clearing existing cache files...");
        CoreConfig::deleteCache(CoreConfig::$DIR_PROJECT);
        $this->info("✓ Cleared: app/lila/cache/ directory");
    }

    /**
     * Recursively remove a directory
     * 
     * @param string $path
     * @return void
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : @unlink($fullPath);
        }
        @rmdir($path);
    }

    /**
     * Build Vite frontend assets
     * 
     * @return void
     */
    private function runViteBuild(): void
    {
        $appDir = rtrim(CoreConfig::$DIR_PROJECT, '/');
        $packageLock = $appDir . '/package-lock.json';
        $packageJson = $appDir . '/package.json';

        if (!file_exists($packageJson)) {
            $this->warning("No package.json found. Skipping Vite build.");
            return;
        }

        if (!file_exists($packageLock)) {
            $this->info("Running npm install... (This may take a minute)");
            shell_exec("cd " . escapeshellarg($appDir) . " && npm install 2>&1");
            $this->success("Dependencies installed.");
        } else {
            $this->info("Dependencies detected (package-lock.json).");
        }

        $this->info("Running npm run build...");
        $outputBuild = shell_exec("cd " . escapeshellarg($appDir) . " && npm run build 2>&1");
        
        if (strpos($outputBuild, 'built in') !== false || strpos($outputBuild, 'manifest.json') !== false) {
            $this->success("Frontend assets compiled successfully!");
        } else {
            $this->warning("There might be an issue compiling frontend assets.");
            $this->info($outputBuild);
        }
    }

    /**
     * Perform production readiness checks
     * 
     * @return void
     */
    private function runHealthCheck(): void
    {
        $allPassed = true;

        if (CoreConfig::$DEBUG) {
            $this->warning("DEBUG is still set to 'true'. Remember to set DEBUG=false in .env for production.");
            $allPassed = false;
        } else {
            $this->success("Debug mode is disabled.");
        }

        $dirs = [
            CoreConfig::$DIR_PROJECT . '/lila',
            CoreConfig::$DIR_PROJECT . '/lila/logs',
            CoreConfig::$DIR_PROJECT . '/lila/cache',
            dirname(CoreConfig::$DIR_PROJECT) . '/assets'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                $this->warning("Directory missing: " . basename($dir));
                $allPassed = false;
                continue;
            }
            if (!is_writable($dir)) {
                $this->warning("Directory not writable: " . basename($dir));
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->success("Write permissions are correct.");
        }

        try {
            $db = new Database();
            if ($db->getConnection()) {
                $this->success("Database connection established.");
            } else {
                throw new \Exception("Connection failed");
            }
        } catch (\Throwable $e) {
            $this->warning("Database connectivity issue: " . $e->getMessage());
            $allPassed = false;
        }

        if ($allPassed) {
            $this->success("All health checks passed.");
        } else {
            $this->info("Some checks failed or generated warnings. Please review the output above.");
        }
    }
}
