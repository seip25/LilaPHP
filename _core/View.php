<?php

declare(strict_types=1);

namespace Core;

/**
 * Ultra-fast Native PHP View Engine.
 * 
 * Supports layouts, partials, tiered caching (APCu -> Redis -> FileCache),
 * asset fingerprinting, SEO metadata, PWA capabilities, and development hot reload.
 * 
 * @package Core
 */
class View
{
    private static ?string $currentRenderingView = null;

    /**
     * Renders a view template with context variables and options.
     * 
     * @param string $viewName View name relative to app/views (e.g. 'home', 'about', 'admin/dashboard')
     * @param array<string, mixed> $data Variables exposed to the view
     * @param array<string, mixed> $options Rendering, caching, SEO, and layout options
     * @return void
     */
    public static function render(string $viewName, array $data = [], array $options = []): void
    {
        $appEnv = Config::$APP_ENV;
        $isDev = Config::$DEBUG && in_array(strtolower($appEnv), ['dev', 'development', 'local'], true);
        $useCache = !$isDev && !empty($options['cache']);
        $cacheTtl = (int)($options['cacheTtl'] ?? $options['ttl'] ?? 3600);
        $cacheDriver = (string)($options['driver'] ?? 'auto');

        $cacheKey = 'lila_view_' . md5($viewName . serialize($data) . serialize($options));

        if ($useCache) {
            $cached = Cache::get($cacheKey, null, $cacheDriver);
            if ($cached !== null && is_string($cached)) {
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=utf-8');
                    header('X-Lila-View-Cache: HIT');
                }
                echo $cached;
                return;
            }
        }

        $context = self::buildContext($data, $options);
        $viewFile = self::resolveViewPath($viewName);

        self::$currentRenderingView = $viewName;
        $content = self::evaluateFile($viewFile, $context);
        self::$currentRenderingView = null;

        $layoutName = self::resolveLayoutName($options);

        if ($layoutName !== null) {
            $layoutFile = self::resolveViewPath($layoutName);
            $layoutContext = array_merge($context, [
                'content'     => $content,
                'title'       => (string)($options['title'] ?? $data['title'] ?? Config::$APP_NAME),
                'description' => (string)($options['description'] ?? $data['description'] ?? ''),
                'keywords'    => (string)($options['keywords'] ?? $data['keywords'] ?? ''),
                'canonical'   => (string)($options['canonical'] ?? $data['canonical'] ?? ''),
                'jsonLD'      => $options['jsonLD'] ?? $data['jsonLD'] ?? null,
                'manifest'    => (string)($options['manifest'] ?? (file_exists(Config::$DIR_PUBLIC . '/manifest.json') ? '/manifest.json' : '')),
                'pwa'         => !empty($options['pwa']),
            ]);

            $finalHtml = self::evaluateFile($layoutFile, $layoutContext);
        } else {
            $finalHtml = $content;
        }

        if ($isDev) {
            $finalHtml = self::injectDevReloadScript($finalHtml);
        }

        if (!empty($options['pwa'])) {
            $finalHtml = self::injectPwaScript($finalHtml, (string)($options['manifest'] ?? '/manifest.json'));
        }

        if ($useCache) {
            Cache::set($cacheKey, $finalHtml, $cacheTtl, $cacheDriver);
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Lila-View-Cache: ' . ($useCache ? 'MISS' : 'BYPASS'));
        }

        if (!empty($options['stream'])) {
            self::stream($finalHtml);
            return;
        }

        echo $finalHtml;
    }

    /**
     * Renders or returns an HTML string directly with optional caching and layout.
     * 
     * @param string|callable $htmlOrCallable HTML string or closure returning HTML string
     * @param array<string, mixed> $options Rendering and caching options
     * @return string
     */
    public static function html(string|callable $htmlOrCallable, array $options = []): string
    {
        $html = is_callable($htmlOrCallable) ? (string)$htmlOrCallable() : $htmlOrCallable;

        if (self::$currentRenderingView !== null) {
            return $html;
        }

        if (!empty($options['direct'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        return $html;
    }

    /**
     * Evaluates and returns the HTML output of a reusable partial view.
     * 
     * @param string $name Partial name relative to app/views/partials/ or app/views/
     * @param array<string, mixed> $data Variables to pass to the partial
     * @return string
     */
    public static function partial(string $name, array $data = []): string
    {
        $context = self::buildContext($data);
        $candidates = [
            Config::$DIR_APP . "/views/partials/{$name}.php",
            Config::$DIR_APP . "/views/{$name}.php",
            Config::$DIR_APP . "/views/{$name}/index.php",
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return self::evaluateFile($candidate, $context);
            }
        }

        throw new \RuntimeException("Partial view `{$name}` not found.");
    }

    /**
     * Generates a version-fingerprinted public asset URL for robust cache busting.
     * 
     * @param string $path Asset path relative to public/ (e.g. '/css/bluebird.css')
     * @return string
     */
    public static function asset(string $path): string
    {
        $cleanPath = '/' . ltrim($path, '/');
        $filePath = Config::$DIR_PUBLIC . $cleanPath;

        if (file_exists($filePath)) {
            $hash = substr(md5_file($filePath) ?: (string)filemtime($filePath), 0, 8);
            return "{$cleanPath}?v={$hash}";
        }

        return $cleanPath;
    }

    /**
     * Escapes a value safely for HTML output to prevent XSS.
     * 
     * @param mixed $value Value to escape
     * @return string
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Streams large HTML responses in memory-efficient chunks.
     * 
     * @param string $html Full HTML payload
     * @param int $chunkSize Buffer size per chunk in bytes
     * @return void
     */
    public static function stream(string $html, int $chunkSize = 8192): void
    {
        $length = strlen($html);
        for ($i = 0; $i < $length; $i += $chunkSize) {
            echo substr($html, $i, $chunkSize);
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    }

    /**
     * Builds default context variables including CSRF and application metadata.
     * 
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function buildContext(array $data = [], array $options = []): array
    {
        $csrfToken = Security::getCsrfToken();

        $defaults = [
            'csrf_token' => $csrfToken,
            'csrf_input' => '<input type="hidden" id="_csrf" name="_csrf" value="' . self::escape($csrfToken) . '">',
            'csrf_meta'  => '<meta name="csrf-token" id="_csrf" content="' . self::escape($csrfToken) . '">',
            'app_name'   => Config::$APP_NAME,
            'app_env'    => Config::$APP_ENV,
            'app_url'    => Config::$APP_URL,
        ];

        return array_merge($defaults, $data);
    }

    /**
     * Resolves the view file path supporting direct files and directory indexes.
     * 
     * @param string $viewName
     * @return string
     */
    private static function resolveViewPath(string $viewName): string
    {
        $baseDir = Config::$DIR_APP . '/views';
        $directFile = "{$baseDir}/{$viewName}.php";
        $indexFile = "{$baseDir}/{$viewName}/index.php";

        if (file_exists($directFile)) {
            return $directFile;
        }

        if (file_exists($indexFile)) {
            return $indexFile;
        }

        throw new \RuntimeException("View template `{$viewName}` not found in `{$baseDir}`.");
    }

    /**
     * Determines the active layout name based on options and filesystem presence.
     * 
     * @param array<string, mixed> $options
     * @return string|null
     */
    private static function resolveLayoutName(array $options): ?string
    {
        if (array_key_exists('layout', $options)) {
            if ($options['layout'] === false || $options['layout'] === null || $options['layout'] === '') {
                return null;
            }
            return (string)$options['layout'];
        }

        $defaultLayout = Config::$DIR_APP . '/views/layout.php';
        if (file_exists($defaultLayout)) {
            return 'layout';
        }

        return null;
    }

    /**
     * Evaluates a PHP template file in an isolated variable scope.
     * 
     * @param string $filePath
     * @param array<string, mixed> $context
     * @return string
     */
    private static function evaluateFile(string $filePath, array $context): string
    {
        extract($context, EXTR_SKIP);

        ob_start();
        try {
            require $filePath;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Injects client development hot reload script.
     * 
     * @param string $html
     * @return string
     */
    private static function injectDevReloadScript(string $html): string
    {
        $script = <<<'HTML'
<script>
(function() {
    let lastModified = null;
    async function checkReload() {
        try {
            const res = await fetch('/api/dev/reload', { cache: 'no-store' });
            if (res.ok) {
                const data = await res.json();
                if (lastModified === null) {
                    lastModified = data.timestamp;
                } else if (data.timestamp > lastModified) {
                    location.reload();
                }
            }
        } catch (e) {}
    }
    setInterval(checkReload, 1000);
})();
</script>
HTML;

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script . '</body>', $html);
        }

        return $html . $script;
    }

    /**
     * Injects PWA install handler script.
     * 
     * @param string $html
     * @param string $manifestPath
     * @return string
     */
    private static function injectPwaScript(string $html, string $manifestPath): string
    {
        $manifestLink = '<link rel="manifest" href="' . self::escape($manifestPath) . '">';
        $script = <<<'HTML'
<script>
(function() {
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        window.LilaPWA = {
            install: async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const choice = await deferredPrompt.userChoice;
                    deferredPrompt = null;
                    return choice;
                }
            }
        };
        window.dispatchEvent(new CustomEvent('lilapwa:ready'));
    });
})();
</script>
HTML;

        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', $manifestLink . '</head>', $html);
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script . '</body>', $html);
        }

        return $html . $script;
    }
}
