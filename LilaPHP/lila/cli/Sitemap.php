<?php

namespace Cli;

use Core\Config;

/**
 * Sitemap Generator Command
 * 
 * Automatically discovers routes and generates a multilingual sitemap.xml
 * 
 * @package Cli
 */
class Sitemap extends Command
{
    /**
     * Execute sitemap command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->run($args);
    }

    /**
     * Run sitemap generation
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function run(array $args): void
    {
        $this->info("Generating sitemap...");

        $baseUrl = rtrim(Config::$URL_PROJECT, '/');
        $locales = Config::$TRANSLATE ? $this->discoverLocales() : [Config::$LANG];
        $routes = $this->discoverRoutes();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . PHP_EOL;

        foreach ($routes as $route) {
            foreach ($locales as $lang) {
                $url = $baseUrl . '/' . (Config::$TRANSLATE ? $lang . '/' : '') . ltrim($route, '/');
                $xml .= '  <url>' . PHP_EOL;
                $xml .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
                $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
                $xml .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
                $xml .= '    <priority>0.8</priority>' . PHP_EOL;

                if (Config::$TRANSLATE) {
                    foreach ($locales as $altLang) {
                        $altUrl = $baseUrl . '/' . $altLang . '/' . ltrim($route, '/');
                        $xml .= '    <xhtml:link rel="alternate" hreflang="' . $altLang . '" href="' . htmlspecialchars($altUrl) . '" />' . PHP_EOL;
                    }

                    $defaultUrl = $baseUrl . '/' . Config::$LANG . '/' . ltrim($route, '/');
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl) . '" />' . PHP_EOL;
                }

                $xml .= '  </url>' . PHP_EOL;
            }
        }

        $xml .= '</urlset>';

        $outputPath = dirname(Config::$DIR_PROJECT) . '/sitemap.xml';
        if (file_put_contents($outputPath, $xml)) {
            $this->success("Sitemap successfully generated at: " . $outputPath);
            $this->info("Total routes indexed: " . count($routes));
            $this->info("Total localized URLs: " . (count($routes) * count($locales)));
        } else {
            $this->error("Failed to write sitemap.xml to " . $outputPath);
        }
    }

    /**
     * Discover all localized locales from locales
     * 
     * @return array Array of language codes
     */
    private function discoverLocales(): array
    {
        $localesDir = Config::$DIR_PROJECT . DIRECTORY_SEPARATOR . 'locales';
        $locales = [];
        if (is_dir($localesDir)) {
            $files = glob($localesDir . DIRECTORY_SEPARATOR . '*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    if (str_starts_with($filename, 'validation_') || $filename === 'seo.php')
                        continue;
                    $lang = str_replace('.php', '', $filename);
                    $locales[] = $lang;
                }
            }
        }
        return !empty($locales) ? $locales : [Config::$LANG];
    }

    /**
     * Discover all routes from LilaPHP/routes
     * 
     * @return array Array of relative URL paths
     */
    private function discoverRoutes(): array
    {
        $routes = ['/']; // Start with root
        $routesDir = Config::$DIR_PROJECT . DIRECTORY_SEPARATOR . 'routes';

        if (!is_dir($routesDir)) {
            return $routes;
        }

        $it = new \RecursiveDirectoryIterator($routesDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace([$routesDir, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname());
                $relativePath = ltrim($relativePath, '/');

                // If it's index.php, the route is the directory path
                if (basename($relativePath) === 'index.php') {
                    $route = dirname($relativePath);
                    if ($route === '.') {
                        continue; // Already added as root
                    }
                } else {
                    // Otherwise, the route is the file path without .php
                    $route = str_replace('.php', '', $relativePath);
                }

                $routes[] = $route;
            }
        }

        return array_unique($routes);
    }
}
