<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;

/**
 * Dynamic Sitemap Generator CLI (`php cli.php sitemap`).
 * 
 * Inspects all web routes in app/routes/ (excluding API endpoints)
 * and generates a standard public/sitemap.xml file.
 * 
 * @package Cli
 */
class Sitemap extends Command
{
    /**
     * Executes sitemap generation.
     * 
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner("LilaPHP Dynamic Sitemap Generator");

        $routesDir = Config::$DIR_APP . '/routes';
        $publicDir = Config::$DIR_PUBLIC;
        $baseUrl = rtrim(Config::$APP_URL, '/');

        if (!is_dir($routesDir)) {
            $this->error("Routes directory `{$routesDir}` not found.");
            return 1;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routesDir, \FilesystemIterator::SKIP_DOTS)
        );

        $urls = [];
        $date = date('Y-m-d');

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
                continue;
            }

            $cleanRoute = preg_replace('/\.php$/', '', $relPath);
            if ($cleanRoute === 'index') {
                $cleanRoute = '';
            }

            $url = $baseUrl . '/' . ltrim($cleanRoute, '/');
            $urls[] = $url;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $u) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($u, ENT_XML1, 'UTF-8') . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $date . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . ($u === $baseUrl . '/' ? '1.0' : '0.8') . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>' . PHP_EOL;

        $targetFile = $publicDir . '/sitemap.xml';
        if (file_put_contents($targetFile, $xml) === false) {
            $this->error("Failed to write sitemap to `{$targetFile}`.");
            return 1;
        }

        $this->success("Generated sitemap with " . count($urls) . " URLs -> `public/sitemap.xml`");
        return 0;
    }
}
