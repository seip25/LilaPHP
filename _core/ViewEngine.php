<?php

namespace Core;

/**
 * Unified Layout Engine and Asset Resolver for React SPA Integration.
 * 
 * Handles server-side route guards, SEO metadata injection, Vite development HMR
 * preambles, and production asset resolution from _core/cache/build_cache.php.
 * 
 * @package Core
 */
class ViewEngine
{
    /**
     * Renders the HTML document structure and boots the React SPA application.
     * 
     * @param array<string, array{
     *     title?: string,
     *     description?: string,
     *     keywords?: string,
     *     protected?: bool,
     *     redirectTo?: string,
     *     callback?: callable(): (bool|array)
     * }> $seoRoutes Map of URI paths to SEO metadata and server-side authentication guards/callbacks.
     * @param array $options Additional execution options and default overrides.
     * @return void
     */
    public static function render(array $seoRoutes, array $options = []): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
        $isDev = in_array(strtolower($appEnv), ['dev', 'development', 'local'], true);

        if (!empty($seoRoutes[$uri]['protected'])) {
            $hasCallback = !empty($seoRoutes[$uri]['callback']) && is_callable($seoRoutes[$uri]['callback']);
            if ($hasCallback) {
                $isAuthenticated = call_user_func($seoRoutes[$uri]['callback']);
                if (!$isAuthenticated) {
                    $redirect = $seoRoutes[$uri]['redirectTo'] ?? '/login';
                    if ($redirect) {
                        header('Location: ' . $redirect);
                        exit;
                    }
                }
            } else {
                $hasSession = isset($_COOKIE['PHPSESSID']) || isset($_COOKIE['user_token']) || isset($_SERVER['HTTP_AUTHORIZATION']);
                if (!$hasSession && $uri !== '/login') {
                    $redirect = $seoRoutes[$uri]['redirectTo'] ?? '/login';
                    if ($redirect) {
                        header('Location: ' . $redirect);
                        exit;
                    }
                }
            }
        }

        if (!$isDev && ($options['cache'] ?? true)) {
            $cacheKey = 'view_engine_html_' . md5($uri . json_encode($seoRoutes[$uri] ?? []));
            $ttl = (int) ($options['cacheTtl'] ?? 3600);

            $html = Cache::api($cacheKey, function () use ($seoRoutes, $options, $uri, $appEnv, $isDev) {
                return self::generateHtml($seoRoutes, $options, $uri, $appEnv, $isDev);
            }, $ttl);

            echo $html;
            return;
        }

        echo self::generateHtml($seoRoutes, $options, $uri, $appEnv, $isDev);
    }

    /**
     * Generates and returns the complete HTML document string for a route.
     * 
     * @param array $seoRoutes Map of URI paths to SEO metadata.
     * @param array $options Additional execution options.
     * @param string $uri Current request URI.
     * @param string $appEnv Application environment name.
     * @param bool $isDev Whether development mode is active.
     * @return string Complete HTML document string.
     */
    private static function generateHtml(array $seoRoutes, array $options, string $uri, string $appEnv, bool $isDev): string
    {
        $seo = $seoRoutes[$uri] ?? ($options['defaultSeo'] ?? [
            'title' => 'LilaPHP Framework',
            'description' => 'High-Performance API & React Engine',
            'keywords' => '',
        ]);

        $lang = $options['lang'] ?? 'en';
        $viteHost = $options['viteHost'] ?? 'http://localhost:5173';

        $scripts = [];
        $styles = [];

        if ($isDev) {
            $scripts[] = '<script type="module">
        import RefreshRuntime from "' . $viteHost . '/@react-refresh";
        RefreshRuntime.injectIntoGlobalHook(window);
        window.$RefreshReg$ = () => {};
        window.$RefreshSig$ = () => (type) => type;
        window.__vite_plugin_react_preamble_installed__ = true;
    </script>';
            $scripts[] = '<script type="module" src="' . $viteHost . '/@vite/client"></script>';
            $scripts[] = '<script type="module" src="' . $viteHost . '/frontend/src/main.jsx"></script>';
        } else {
            $cacheFile = __DIR__ . '/cache/build_cache.php';
            if (file_exists($cacheFile)) {
                $buildAssets = require $cacheFile;
                $scripts = $buildAssets['scripts'] ?? [];
                $styles = $buildAssets['styles'] ?? [];
            } else {
                $manifestPath = __DIR__ . '/../frontend/js/.vite/manifest.json';
                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                    $entry = $manifest['frontend/src/main.jsx'] ?? null;
                    if ($entry) {
                        if (!empty($entry['file'])) {
                            $scripts[] = '<script type="module" src="/frontend/js/.vite/' . htmlspecialchars($entry['file']) . '"></script>';
                        }
                        if (!empty($entry['css'])) {
                            foreach ($entry['css'] as $cssFile) {
                                $styles[] = '<link rel="stylesheet" href="/frontend/js/.vite/' . htmlspecialchars($cssFile) . '">';
                            }
                        }
                    }
                }
            }
        }

        $initialGlobals = [
            'env' => $appEnv,
            'uri' => $uri,
            'user' => null,
        ];

        ob_start();
        echo '<!DOCTYPE html>' . PHP_EOL;
        echo '<html lang="' . htmlspecialchars($lang) . '">' . PHP_EOL;
        echo '<head>' . PHP_EOL;
        echo '    <meta charset="UTF-8">' . PHP_EOL;
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
        echo '    <title>' . htmlspecialchars($seo['title'] ?? 'LilaPHP') . '</title>' . PHP_EOL;
        echo '    <meta name="description" content="' . htmlspecialchars($seo['description'] ?? '') . '">' . PHP_EOL;
        if (!empty($seo['keywords'])) {
            echo '    <meta name="keywords" content="' . htmlspecialchars($seo['keywords']) . '">' . PHP_EOL;
        }
        echo '    <link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL;
        echo '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL;
        echo '    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">' . PHP_EOL;
        foreach ($styles as $styleTag) {
            echo '    ' . $styleTag . PHP_EOL;
        }
        echo '</head>' . PHP_EOL;
        echo '<body>' . PHP_EOL;
        echo '    <div id="root"></div>' . PHP_EOL;
        echo '    <script>' . PHP_EOL;
        echo '        window.__INITIAL_DATA__ = ' . json_encode($initialGlobals, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) . ';' . PHP_EOL;
        echo '    </script>' . PHP_EOL;
        foreach ($scripts as $scriptTag) {
            echo '    ' . $scriptTag . PHP_EOL;
        }
        echo '</body>' . PHP_EOL;
        echo '</html>' . PHP_EOL;

        return ob_get_clean();
    }
}
