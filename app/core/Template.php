<?php

namespace Core;

use Core\Response;
use Core\Logger;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Template
{
    private static ?Environment $twig = null;

    private static function loadTwig(string $pathHtml): Environment
    {
        if (self::$twig === null) {
            $loader = new FilesystemLoader($pathHtml);
            self::$twig = new Environment($loader, [
                'cache' => Config::$DEBUG ? false : Config::$DIR_PROJECT . '/cache/twig',
                'debug' => Config::$DEBUG,
                'autoescape' => 'html'
            ]);

            self::registerFunctions();
        }
        return self::$twig;
    }

    private static function registerFunctions(): void
    {
        self::$twig->addFunction(new TwigFunction('asset', function (string $file): string {
            $urlBase = rtrim(Config::$URL_PROJECT, '/') . '/public/';
            $publicDir = realpath(Config::$DIR_PROJECT . '/../public/') . '/';
            $fullPath = $publicDir . ltrim($file, '/');

            if (!file_exists($fullPath)) {
                error_log("[Asset] File not found: $fullPath");
                return $urlBase . ltrim($file, '/');
            }

            if (Config::$DEBUG) {
                return $urlBase . ltrim($file, '/');
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['css', 'js'])) {
                return $urlBase . ltrim($file, '/');
            }

            $cacheDir = $publicDir . 'cache/';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            $minFile = $cacheDir . md5($file . filemtime($fullPath)) . ".min.$ext";
            $minUrl = $urlBase . 'cache/' . basename($minFile);

            if (file_exists($minFile)) {
                return $minUrl;
            }

            $content = file_get_contents($fullPath);

            if ($ext === 'css') {
                $minified = preg_replace(['!/\*.*?\*/!s', '/\n\s*/', '/\s{2,}/', '/ ?([,:;{}]) ?/'], ['', '', ' ', '$1'], $content);
            } else {
                $minified = preg_replace(['!//.*!', '!/\*.*?\*/!s', '/\s{2,}/', '/\n+/'], ['', '', ' ', ''], $content);
            }

            file_put_contents($minFile, trim($minified));

            return $minUrl;
        }));

        self::$twig->addFunction(new TwigFunction('url', function (string $path = ''): string {
            return rtrim(Config::$URL_PROJECT, '/') . '/' . ltrim($path, '/');
        }));


        self::$twig->addFunction(new TwigFunction('csrf_input', function (): string {
            $token = Security::generateCsrfToken();
            return '<input type="hidden" id="_csrf" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        self::$twig->addFunction(new TwigFunction('image', function (string $file, int $width = 800, int $height = 0, int $quality = 70, string $type = 'webp'): string {
            $url = ImageOptimizer::getOptimized($file, $type, $width, $height, $quality);
            return $url ?? (rtrim(Config::$URL_PROJECT, '/') . '/public/' . ltrim($file, '/'));
        }));
        self::$twig->addFunction(new TwigFunction('translate', function (string $key): string {
            return Translate::t($key);
        }));

        self::$twig->addFunction(new TwigFunction('__', function (string $key): string {
            return Translate::t($key);
        }));

        self::$twig->addFunction(new TwigFunction('react', function (string $component, array $props = []): string {
            $id = 'react-' . uniqid();
            $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
            return "<div id=\"{$id}\" data-react-component=\"{$component}\" data-props='{$propsJson}'></div>";
        }, ['is_safe' => ['html']]));

        self::$twig->addFunction(new TwigFunction('vite_assets', function (): string {
            $isDev = Config::$DEBUG;

            if ($isDev) {
                return '
                <script type="module">
                    import RefreshRuntime from "http://localhost:5173/build/@react-refresh";
                    RefreshRuntime.injectIntoGlobalHook(window);
                    window.$RefreshReg$ = () => {};
                    window.$RefreshSig$ = () => (type) => type;
                    window.__vite_plugin_react_preamble_installed__ = true;
                </script>
                <script type="module" src="http://localhost:5173/build/@vite/client"></script>
                <script type="module" src="http://localhost:5173/build/main.jsx"></script>';
            }

            $manifestPath = Config::$DIR_PROJECT . '/../public/build/manifest.json';

            if (!file_exists($manifestPath)) {
                $manifestPath = Config::$DIR_PROJECT . '/../public/build/.vite/manifest.json';
            }
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                //manifest example {"main.jsx":{"file":"assets\/main-D0GVAlEg.js","name":"main","src":"main.jsx","isEntry":true}}
                if (isset($manifest['main.jsx'])) {
                    $file = $manifest['main.jsx']['file'];
                    $css = $manifest['main.jsx']['css'] ?? [];

                    $html = '<script type="module" src="' . rtrim(Config::$URL_PROJECT, '/') . '/public/build/' . $file . '"></script>';
                    foreach ($css as $cssFile) {
                        $html .= '<link rel="stylesheet" href="' . rtrim(Config::$URL_PROJECT, '/') . '/public/build/' . $cssFile . '">';
                    }
                    return $html;
                }
                return '<!-- Vite Manifest not found -->';
            }

            return '<!-- Vite Manifest not found -->';
        }, ['is_safe' => ['html']]));
    }

    public static function react(string $island, array $props = [], ?string $lang = null, ?string $title = null, array $meta = [], array $scripts = [], array $styles = []): string
    {
        $isDev = Config::$DEBUG;
        $id = 'react-' . uniqid();
        $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        $component = "<div id=\"{$id}\" data-react-component=\"{$island}\" data-props='{$propsJson}'></div>";
        $html = "";
        $scriptsReact = "";
        if ($isDev) {
            $scriptsReact .= '
                <script type="module">
                    import RefreshRuntime from "http://localhost:5173/build/@react-refresh";
                    RefreshRuntime.injectIntoGlobalHook(window);
                    window.$RefreshReg$ = () => {};
                    window.$RefreshSig$ = () => (type) => type;
                    window.__vite_plugin_react_preamble_installed__ = true;
                </script>
                <script type="module" src="http://localhost:5173/build/@vite/client"></script>
                <script type="module" src="http://localhost:5173/build/main.jsx"></script>';
        }

        $manifestPath = Config::$DIR_PROJECT . '/../public/build/manifest.json';

        if (!file_exists($manifestPath)) {
            $manifestPath = Config::$DIR_PROJECT . '/../public/build/.vite/manifest.json';
        }
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (isset($manifest['main.jsx'])) {
                $file = $manifest['main.jsx']['file'];
                $css = $manifest['main.jsx']['css'] ?? [];

                $scriptsReact .= '<script type="module" src="' . rtrim(Config::$URL_PROJECT, '/') . '/public/build/' . $file . '"></script>';
                foreach ($css as $cssFile) {
                    $scriptsReact .= '<link rel="stylesheet" href="' . rtrim(Config::$URL_PROJECT, '/') . '/public/build/' . $cssFile . '">';
                }
            }
        }
        $stylesHtml = "";
        foreach ($styles as $style) {
            $stylesHtml .= '<link rel="stylesheet" href="' . rtrim($style) . '">';
        }
        $scriptsHtml = "";
        foreach ($scripts as $script) {
            $scriptsHtml .= '<script src="' . rtrim($script) . '"></script>';
        }
        $metaHtml = "";
        foreach ($meta as $meta) {
            $metaHtml .= '<meta name="' . $meta['name'] . '" content="' . $meta['content'] . '">';
        }
        $titleHtml = "";
        if ($title) {
            $titleHtml = '<title>' . $title . ' | ' . Config::$TITLE_PROJECT . '</title>';
        }
        $lang = is_null($lang) ? Config::$LANGHTML : $lang;

        $icon = rtrim(Config::$URL_PROJECT, '/') . "/favicon.ico";

        $html = <<<HTML
        <html  lang="{$lang}">
        <head>
            {$titleHtml}
            <link rel="icon" type="image/ico" href="$icon">
            {$metaHtml}
            {$scriptsReact}
            {$stylesHtml}
            {$scriptsHtml}
        </head>
        <body>
            {$component}
        </body>    
            
        
HTML;
        return $html;
    }

    private static function getBaseContext(array $extra = []): array
    {
        return array_merge([
            "title" => Config::$TITLE_PROJECT,
        ], $extra);
    }

    public static function minifyHtml(string $buffer): string
    {
        $buffer = preg_replace('/<!--(?!\[if).*?-->/', '', $buffer);
        $buffer = preg_replace('/>\s+</', '><', $buffer);
        $buffer = preg_replace('/\s{2,}/', ' ', $buffer);
        return trim($buffer);
    }

    public static function render(string $template, array $context = [], ?string $path = null): void
    {
        try {
            $twig = self::loadTwig($path ? Config::$DIR_PROJECT . $path : Config::$DIR_PROJECT . "/templates");
            $fullContext = self::getBaseContext(extra: $context);
            $html = $twig->render("$template.twig", $fullContext);
            $html = self::minifyHtml($html);
            header('Cache-Control: public, max-age=604800, immutable');

            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                header('Content-Encoding: gzip');
                echo gzencode($html, 9);
            } else {
                echo $html;
            }
        } catch (\Throwable $e) {
            $error = Config::$DEBUG ? $e->getMessage() : "General error";
            if ($template != "500") {
                Logger::error("Template render error: " . $e->getMessage());
                $context = ["error" => $error];
                self::render("lila/500", $context, $path);
            } else {
                $html = <<<HTML
<main style="min-height: 100vh; display: flex; flex-direction: column; background-color: #f9fafb;">
    <div style="display: flex; justify-content: center;">
        <article style="max-width: 600px; margin-top: 2rem; padding: 2rem; background-color: #fef2f2; border-radius: 8px;">
            <p style="color: #ef4444; font-family: sans-serif; font-size: 1rem; text-align: center;">
                $error
            </p>
        </article>
    </div>
</main>
HTML;
                Response::HTML($html, 500);
            }
            exit;
        }
    }
}
