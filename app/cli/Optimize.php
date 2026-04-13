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

        // 1. Environment Caching
        $this->info("\n[1/4] Optimizing Environment Variables...");
        $configCmd = new Config();
        $configCmd->cache([]);

        // 2. Model Caching
        $this->info("\n[2/4] Optimizing Model Metadata...");
        $modelCmd = new Model();
        $modelCmd->cache([]);

        // 3. Asset Minification
        $this->info("\n[3/4] Minifying Assets...");
        $minifyCmd = new Minify();
        $minifyCmd->execute([]);

        // 4. Health Check
        $this->info("\n[4/4] Performing Production Health Check...");
        $this->runHealthCheck();

        $this->info("\n========================================");
        $this->success("Optimization completed successfully!");
        $this->info("Your application is now ready for scale.");
        $this->info("========================================\n");
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
            CoreConfig::$DIR_PROJECT . '/logs',
            CoreConfig::$DIR_PROJECT . '/cache',
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
