<?php

namespace Core;

/**
 * Unified Layout Engine and Asset Resolver for React SPA Integration (`Performance First`).
 * 
 * Renders server-side HTML shell with SEO metadata, Vite asset resolution,
 * route middleware guards, and APCu-cached template output for maximum RPS.
 * 
 * Production flow:
 *   Request → OPcache (bytecode) → APCu check
 *     ├── HIT  → echo $html (0.01ms) → exit
 *     └── MISS → render + flush → store APCu → exit
 * 
 * @package Core
 */
class ViewEngine
{
    /**
     * Renders the HTML document and boots the React SPA application.
     * 
     * @param array<string, array{
     *     title?: string,
     *     description?: string,
     *     keywords?: string,
     *     lang?: string,
     *     protected?: bool,
     *     redirectTo?: string,
     *     callback?: callable(): (bool|array),
     *     data?: array
     * }> $seoRoutes Map of URI paths to SEO metadata and server-side guards.
     * @param array{
     *     lang?: string,
     *     cache?: bool,
     *     cacheTtl?: int,
     *     viteHost?: string,
     *     fonts?: array<string>,
     *     preconnect?: array<string>,
     *     skeleton?: bool,
     *     defaultSeo?: array
     * } $options Global execution options and default overrides.
     * @return void
     */
    public static function render(array $seoRoutes, array $options = []): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $appEnv = Config::$APP_ENV ?? (getenv('APP_ENV') ?: 'development');
        $isDev = in_array(strtolower($appEnv), ['dev', 'development', 'local'], true);

        if (!empty($seoRoutes[$uri]['protected'])) {
            $hasCallback = !empty($seoRoutes[$uri]['callback']) && is_callable($seoRoutes[$uri]['callback']);
            if ($hasCallback) {
                $isAuthenticated = call_user_func($seoRoutes[$uri]['callback']);
                if (!$isAuthenticated) {
                    $redirect = $seoRoutes[$uri]['redirectTo'] ?? '/login';
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $hasSession = isset($_COOKIE['PHPSESSID'])
                    || isset($_COOKIE['user_token'])
                    || isset($_SERVER['HTTP_AUTHORIZATION']);
                if (!$hasSession && $uri !== '/login') {
                    $redirect = $seoRoutes[$uri]['redirectTo'] ?? '/login';
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        }

        $useCache = !$isDev && ($options['cache'] ?? true);
        if ($useCache) {
            $routeSeo = $seoRoutes[$uri] ?? [];
            unset($routeSeo['callback']); // Don't serialize closures
            $cacheKey = 'lila_view_' . md5($uri . serialize($routeSeo));
            $ttl = (int)($options['cacheTtl'] ?? 0);

            $html = Cache::api($cacheKey, function () use ($seoRoutes, $options, $uri, $appEnv, $isDev) {
                return self::buildHtml($seoRoutes, $options, $uri, $appEnv, $isDev);
            }, $ttl);

            header('Content-Type: text/html; charset=utf-8');
            header('X-Lila-View-Cache: HIT');
            echo $html;
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Lila-View-Cache: MISS');
        echo self::buildHtml($seoRoutes, $options, $uri, $appEnv, $isDev);
    }

    /**
     * Builds the complete HTML document string for a route.
     * 
     * @param array $seoRoutes Map of URI paths to SEO metadata.
     * @param array $options Global execution options.
     * @param string $uri Current request URI.
     * @param string $appEnv Application environment name.
     * @param bool $isDev Whether development mode is active.
     * @return string Complete HTML document string.
     */
    private static function buildHtml(array $seoRoutes, array $options, string $uri, string $appEnv, bool $isDev): string
    {
        $seo = $seoRoutes[$uri] ?? ($options['defaultSeo'] ?? []);
        $title = $seo['title'] ?? 'LilaPHP';
        $description = $seo['description'] ?? '';
        $keywords = $seo['keywords'] ?? '';
        $lang = $seo['lang'] ?? ($options['lang'] ?? 'en');
        $skeleton = $options['skeleton'] ?? true;
        $viteHost = $options['viteHost'] ?? 'http://localhost:5173';

        $initialData = array_merge([
            'env' => $appEnv,
            'uri' => $uri,
        ], $seo['data'] ?? [], $options['data'] ?? []);
        $scripts = [];
        $styles = [];

        if ($isDev) {
            $scripts[] = '<script type="module">'
                . 'import RefreshRuntime from "' . $viteHost . '/@react-refresh";'
                . 'RefreshRuntime.injectIntoGlobalHook(window);'
                . 'window.$RefreshReg$ = () => {};'
                . 'window.$RefreshSig$ = () => (type) => type;'
                . 'window.__vite_plugin_react_preamble_installed__ = true;'
                . '</script>';
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

        $html = '<!DOCTYPE html>' . "\n"
            . '<html lang="' . htmlspecialchars($lang) . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="UTF-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
            . '<title>' . htmlspecialchars($title) . '</title>' . "\n"
            . '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";

        if ($keywords !== '') {
            $html .= '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
        }

        if (!empty($options['preconnect'])) {
            foreach ($options['preconnect'] as $origin) {
                $html .= '<link rel="preconnect" href="' . htmlspecialchars($origin) . '">' . "\n";
            }
        }

        if (!empty($options['fonts'])) {
            foreach ($options['fonts'] as $fontUrl) {
                $html .= '<link rel="stylesheet" href="' . htmlspecialchars($fontUrl) . '">' . "\n";
            }
        }

        foreach ($styles as $styleTag) {
            $html .= $styleTag . "\n";
        }

        $html .= '</head>' . "\n"
            . '<body>' . "\n";

        if ($skeleton) {
            $html .= '<div id="root">'
                . '<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:system-ui,sans-serif">'
                . '<div style="text-align:center">'
                . '<div style="width:40px;height:40px;border:3px solid rgba(255,255,255,.1);border-top-color:rgba(255,255,255,.6);border-radius:50%;animation:lila-spin .6s linear infinite;margin:0 auto 16px"></div>'
                . '<p style="color:rgba(255,255,255,.4);font-size:14px;margin:0">Loading...</p>'
                . '</div></div>'
                . '<style>@keyframes lila-spin{to{transform:rotate(360deg)}}</style>'
                . '</div>' . "\n";
        } else {
            $html .= '<div id="root"></div>' . "\n";
        }

        $html .= '<script>window.__INITIAL_DATA__='
            . json_encode($initialData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES)
            . '</script>' . "\n";
        foreach ($scripts as $scriptTag) {
            $html .= $scriptTag . "\n";
        }

        $html .= '</body>' . "\n"
            . '</html>' . "\n";

        return $html;
    }
}
