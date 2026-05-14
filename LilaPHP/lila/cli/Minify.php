<?php

namespace Cli;

use Core\Config;

/**
 * Minify Command
 * 
 * Compresses CSS and JS assets to save bandwidth.
 * 
 * @package Cli
 */
class Minify extends Command
{
    /**
     * Execute the command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $dir = Config::$DIR_PROJECT . '/../assets';
        if (!is_dir($dir)) {
            $this->error("Assets directory not found: {$dir}");
            return;
        }

        $this->info("Starting minification in {$dir}...");
        $this->processDirectory($dir);
        $this->success("Minification complete.");
    }

    private function processDirectory(string $dir): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->processDirectory($path);
            } elseif (is_file($path)) {
                if (str_ends_with($file, '.min.css') || str_ends_with($file, '.min.js')) {
                    continue; // Skip already minified
                }

                if (str_ends_with($file, '.css')) {
                    $this->minifyCss($path);
                } elseif (str_ends_with($file, '.js')) {
                    $this->minifyJs($path);
                }
            }
        }
    }

    private function minifyCss(string $path): void
    {
        $content = file_get_contents($path);

        // Remove comments
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        // Remove whitespace
        $content = str_replace(["\r\n", "\r", "\n", "\t"], '', $content);
        $content = preg_replace('/ {2,}/', ' ', $content);
        // Remove spaces around brackets and colons
        $content = str_replace([' {', '{ '], '{', $content);
        $content = str_replace([' }', '} '], '}', $content);
        $content = str_replace([' :', ': '], ':', $content);
        $content = str_replace([' ;', '; '], ';', $content);

        $minPath = substr($path, 0, -4) . '.min.css';
        file_put_contents($minPath, trim($content));
        $this->success("Minified CSS: " . basename($minPath));
    }

    private function minifyJs(string $path): void
    {
        $content = file_get_contents($path);

        // Remove multi-line comments
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);

        $lines = explode("\n", $content);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t !== '' && !str_starts_with($t, '//')) {
                // Inline comments are hard to strip safely without AST, 
                // just preserve basic lines trimmed 
                $out[] = $t;
            }
        }

        $minPath = substr($path, 0, -3) . '.min.js';
        file_put_contents($minPath, implode("\n", $out));
        $this->success("Minified JS: " . basename($minPath));
    }
}
