<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;

/**
 * Robots.txt Generator CLI (`php cli.php robots`).
 * 
 * Generates public/robots.txt referencing the dynamic sitemap
 * and disallowing API routes and protected web endpoints.
 * 
 * @package Cli
 */
class Robots extends Command
{
    /**
     * Executes robots.txt generation.
     * 
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Robots.txt Generator");

        $routesDir = Config::$DIR_APP . '/routes';
        $publicDir = Config::$DIR_PUBLIC;
        $baseUrl = rtrim(Config::$APP_URL, '/');

        $disallowed = [
            '/api/',
        ];

        if (is_dir($routesDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($routesDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->getExtension() !== 'php') {
                    continue;
                }

                $relPath = str_replace([$routesDir . DIRECTORY_SEPARATOR, $routesDir . '/'], '', $item->getPathname());
                $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

                if (str_starts_with($relPath, 'api/')) {
                    continue;
                }

                $routeCode = (string)file_get_contents($item->getPathname());
                if (
                    preg_match('/\$noIndex\s*=\s*true/i', $routeCode) ||
                    preg_match('/\$protectedRoute\s*=\s*true/i', $routeCode)
                ) {
                    $cleanRoute = preg_replace('/\.php$/', '', $relPath);
                    $disallowed[] = '/' . ltrim($cleanRoute, '/');
                }
            }
        }

        $lines = [
            'User-agent: *',
        ];

        foreach (array_unique($disallowed) as $dis) {
            $lines[] = 'Disallow: ' . $dis;
        }

        $lines[] = 'Allow: /';
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $baseUrl . '/sitemap.xml';
        $lines[] = '';

        $content = implode(PHP_EOL, $lines);
        $targetFile = $publicDir . '/robots.txt';

        if (file_put_contents($targetFile, $content) === false) {
            $this->error("Failed to write robots.txt to `{$targetFile}`.");
            return 1;
        }

        $this->success("Generated robots file -> `public/robots.txt`");
        return 0;
    }
}
