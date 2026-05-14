<?php

namespace Cli;

/**
 * App Initialization Command
 * 
 * Copies scaffolding files from lila/scaffold/ to the project root.
 * 
 * @package Cli
 */
class Init extends Command
{
    /**
     * Execute the initialization command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->info("Initializing LilaPHP application scaffolding...");

        $source = __DIR__ . '/../scaffold';
        $destination = dirname(__DIR__, 3);

        if (!is_dir($source)) {
            $this->error("Scaffold directory not found at: {$source}");
            return;
        }

        $this->copyRecursive($source, $destination);

        $this->deleteRecursive($source);

        $this->success("Scaffolding files copied to project root and scaffold directory removed.");
        $this->info("You can now start your server and access the application.");
    }

    /**
     * Delete directory recursively
     * 
     * @param string $path
     * @return void
     */
    private function deleteRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$path/$file")) ? $this->deleteRecursive("$path/$file") : unlink("$path/$file");
        }
        rmdir($path);
    }

    /**
     * Copy directory recursively
     * 
     * @param string $source
     * @param string $destination
     * @return void
     */
    private function copyRecursive(string $source, string $destination): void
    {
        $dir = opendir($source);
        @mkdir($destination);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $this->copyRecursive($source . '/' . $file, $destination . '/' . $file);
                } else {
                    if (copy($source . '/' . $file, $destination . '/' . $file)) {
                        $this->line("  \033[32m✓\033[0m Copied: {$file}");
                    }
                }
            }
        }
        closedir($dir);
    }
}
