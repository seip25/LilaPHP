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
                'cache' => Config::$DEBUG ? false : Config::$PATH_CACHE,
                'debug' => Config::$DEBUG,
                'autoescape' => 'html'
            ]);

            self::registerFunctions();
        }
        return self::$twig;
    }

    private static function registerFunctions(): void
    {
        self::$twig->addFunction(new TwigFunction('url', function (string $path = '', bool $ignoreLang = false): string {
            $baseUrl = rtrim(Config::$URL_PROJECT, '/');
            $fullPath = ltrim($path, '/');
            $isAssetsOrFavicon = str_starts_with(haystack: strtolower($fullPath), needle: 'assets') || str_contains(haystack: strtolower($fullPath), needle: 'favicon.ico');
            if ($isAssetsOrFavicon) {
                return $baseUrl . '/' . $path;
            }
            if (!$ignoreLang && Config::$TRANSLATE) {
                $lang = Session::get('lang') ?? Config::$LANG;

                $isAdmin = str_starts_with(haystack: strtolower($fullPath), needle: 'admin') || str_contains(haystack: strtolower($_SERVER['REQUEST_URI'] ?? ''), needle: '/admin');
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
                $isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

                if (!$isAdmin && !$isAjax && $isGet) {
                    return $baseUrl . '/' . $lang . '/' . $fullPath;
                }
            }

            return $baseUrl . '/' . $fullPath;
        }));


        self::$twig->addFunction(new TwigFunction('csrf_input', function (): string {
            $token = Security::generateCsrfToken();
            return '<input type="hidden" id="_csrf" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        self::$twig->addFunction(new TwigFunction('image', function (string $file, int $width = 800, int $height = 0, int $quality = 70, string $type = 'webp'): string {
            $url = ImageOptimizer::getOptimized($file, $type, $width, $height, $quality);
            return $url ?? (rtrim(Config::$URL_PROJECT, '/') . '/assets/' . ltrim($file, '/'));
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

        self::$twig->addFunction(new TwigFunction('csrf_token', function (): string {
            return Security::generateCsrfToken();
        }));

        self::$twig->addFunction(new TwigFunction('vite_assets', function (): string {
            $assets = self::getViteAssetsData();
            $html = '';
            foreach ($assets['css'] as $href) {
                $html .= '<link rel="stylesheet" href="' . $href . '">';
            }
            foreach ($assets['scripts'] as $script) {
                if (isset($script['src'])) {
                    $html .= '<script type="' . ($script['type'] ?? 'text/javascript') . '" src="' . $script['src'] . '"></script>';
                } elseif (isset($script['content'])) {
                    $html .= '<script type="' . ($script['type'] ?? 'text/javascript') . '">' . $script['content'] . '</script>';
                }
            }
            return $html;
        }, ['is_safe' => ['html']]));
        self::$twig->addFunction(new TwigFunction('hot_reload', function (): string {
            if (Config::$DEBUG) {
                return self::hotReload();
            }
            return '';
        }, ['is_safe' => ['html']]));
    }

    /**
     * Check if the current request is a frontend SPA request
     * 
     * @return bool
     */
    private static function isFrontendRequest(): bool
    {
        return isset($_GET['source']) && $_GET['source'] === 'frontend';
    }

    /**
     * Render a JSON response for SPA navigation
     * 
     * @param string $body Rendered HTML body
     * @param array $context Context containing metadata and assets
     * @return void
     */
    private static function renderJsonResponse(string $body, array $context): void
    {
        $response = [
            "meta" => [
                "title" => $context['titleHtml'] ?? $context['title'] ?? Config::$TITLE_PROJECT,
                "description" => $context['descriptionMeta'] ?? Config::$DESCRIPTIONMETA,
                "keywords" => $context['keywordsMeta'] ?? Config::$KEYWORDSMETA,
                "author" => $context['authorMeta'] ?? Config::$AUTHORMETA
            ],
            "lang" => $context['langHtml'] ?? $context['lang'] ?? Config::$LANG,
            "scripts" => $context['scripts_array'] ?? [],
            "css" => $context['styles_array'] ?? [],
            "fonts" => [],
            "body" => $body,
            "props" => $context['props_array'] ?? [],
            "translations" => Translate::translations()
        ];

        Response::JSON(data: $response);

    }

    public static function hotReload(): string
    {
        $appDir = rtrim(Config::$DIR_PROJECT, '/');
        $packageLock = $appDir . '/package-lock.json';
        if (file_exists($packageLock)) {
            return '<script type="module" src="http://localhost:5173/@vite/client"></script>';
        }
        return '';
    }

    /**
     * Render a React full page
     * 
     * @param string $page Page component name
     * @param array $props Props to pass
     * @param string|null $lang Language
     * @param string|null $title Page title
     * @param array $meta Meta tags
     * @param array $scripts External scripts
     * @param array $styles External styles
     * @return void
     */
    public static function react(string $page, array $props = [], ?string $lang = null, ?string $title = null, array $meta = [], array $scripts = [], array $styles = []): void
    {
        $stylesHtml = "";
        $scriptsHtml = "";
        $metaHtml = "";
        $seo = App::$activeRoute['seo'] ?? null;
        if (isset($seo['key']) && $seo['key'] !== null) {
            $seoDynamic = Translate::getSeo($seo['key']);
            if ($seoDynamic) {
                $seo['title'] = $seoDynamic['title'] ?? $seo['title'];
                $seo['description'] = $seoDynamic['descriptionMeta'] ?? $seoDynamic['description'] ?? $seo['description'];
                $seo['keywords'] = $seoDynamic['keywordsMeta'] ?? $seoDynamic['keywords'] ?? $seo['keywords'];
            }
        }
        $titleHtml = $title ?? ($seo['title'] ?? Config::$TITLE_PROJECT);
        $descriptionMeta = $seo['description'] ?? Config::$DESCRIPTIONMETA;
        $authorMeta = Config::$AUTHORMETA;
        $keywordsMeta = $seo['keywords'] ?? Config::$KEYWORDSMETA;
        $lang = is_null($lang) ? (Session::get('lang') ?? Config::$LANGHTML) : $lang;
        $icon = rtrim(Config::$URL_PROJECT, '/') . "/favicon.ico";

        foreach ($styles as $style) {
            $stylesHtml .= '<link rel="stylesheet" href="' . rtrim($style) . '" />';
        }
        foreach ($scripts as $script) {
            $scriptsHtml .= '<script src="' . rtrim($script) . '"></script>';
        }
        foreach ($meta as $meta_value) {
            $metaHtml .= '<meta name="' . $meta_value['name'] . '" content="' . $meta_value['content'] . '" />';
        }

        $context = [
            "langHtml" => $lang,
            "titleHtml" => $titleHtml,
            "meta" => $metaHtml,
            "css" => $stylesHtml,
            "icon" => $icon,
            "scripts" => $scriptsHtml,
            "component" => $page,
            "descriptionMeta" => $descriptionMeta,
            "authorMeta" => $authorMeta,
            "keywordsMeta" => $keywordsMeta,
            "styles_array" => $styles,
            "scripts_array" => $scripts,
            "props_array" => $props
        ];

        if (self::isFrontendRequest()) {
            $viteAssets = self::getViteAssetsData();
            $context['scripts_array'] = array_merge($context['scripts_array'], $viteAssets['scripts']);
            $context['styles_array'] = array_merge($context['styles_array'], $viteAssets['css']);

            $bodyHtml = "<div id=\"root\" data-react-page=\"{$page}\" data-props='" . htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8') . "'></div>";
            self::renderJsonResponse($bodyHtml, $context);
            return;
        }

        $context["props"] = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        self::render(template: "lila/react_base", context: $context);
    }

    private static function getBaseContext(array $extra = []): array
    {
        $seo = App::$activeRoute['seo'] ?? null;
        if (isset($seo['key']) && $seo['key'] !== null) {
            $seoDynamic = Translate::getSeo($seo['key']);
            if ($seoDynamic) {
                $seo['title'] = $seoDynamic['title'] ?? $seo['title'];
                $seo['description'] = $seoDynamic['descriptionMeta'] ?? $seoDynamic['description'] ?? $seo['description'];
                $seo['keywords'] = $seoDynamic['keywordsMeta'] ?? $seoDynamic['keywords'] ?? $seo['keywords'];
            }
        }
        return array_merge([
            "title" => $seo['title'] ?? Config::$TITLE_PROJECT,
            "descriptionMeta" => $seo['description'] ?? Config::$DESCRIPTIONMETA,
            "keywordsMeta" => $seo['keywords'] ?? Config::$KEYWORDSMETA,
            "authorMeta" => Config::$AUTHORMETA,
            "csrf_token" => Security::generateCsrfToken(),
            "lang" => Session::get('lang') ?? Config::$LANG
        ], $extra);
    }

    public static function minifyHtml(string $buffer): string
    {
        $buffer = preg_replace('/<!--(?!\[if).*?-->/', '', $buffer);
        $buffer = preg_replace('/>\s+</', '><', $buffer);
        $buffer = preg_replace('/\s{2,}/', ' ', $buffer);
        return trim($buffer);
    }

    /**
     * Render a Twig template
     * 
     * @param string $template Template name
     * @param array $context Variables
     * @param string|null $path Custom path
     * @return void
     */
    public static function render(string $template, array $context = [], ?string $path = null): void
    {
        try {
            $isFrontend = self::isFrontendRequest();
            $twig = self::loadTwig($path ? Config::$DIR_PROJECT . $path : Config::$DIR_PROJECT . "/resources/templates");

            $context['layout'] = $isFrontend ? "lila/empty.twig" : "base.twig";
            $fullContext = self::getBaseContext(extra: $context);

            $html = $twig->render("$template.twig", $fullContext);
            $html = self::minifyHtml($html);

            if ($isFrontend) {
                self::renderJsonResponse($html, $fullContext);
                return;
            }

            header('Cache-Control: no-cache, must-revalidate');

            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                header('Content-Encoding: gzip');
                echo gzencode($html, 6);
            } else {
                echo $html;
            }
        } catch (\Throwable $exc) {
            $message = "General error";
            if (Config::$DEBUG) {
                $message = $exc->getMessage();
                $file = $exc->getFile();
                $trace = $exc->getTraceAsString();
                $time = date('Y-m-d H:i:s');
                $content = <<<HTML
     <div class="main-header">
        <h1>An unexpected error occurred</h1>
    </div>

    <div class="main-body">
        <p><strong>Message:</strong> {$message}</p>

        <div class="main-meta">
            <div><strong>File:</strong> {$file}</div> 
            <div><strong>Time:</strong> {$time}</div>
        </div>

        <div>
             <pre>{$trace}</pre>
        </div>
    </div>

    <div class="footer">
        Debug mode enabled
    </div>
HTML;
            }
            Logger::error("Template render error: " . $message);
            $context = ["error" => $message];
            Response::HTML(self::templateLilaHTML($content));
            exit;
        }
    }

    static function templateLilaHTML(string $content, string $title = "Application Error"): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="{{ lang }}">
<head> 
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <title>$title</title>
    <style>
        body {
            background: #f6f7f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 40px;
            color: #333;
        }
        .main-container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .main-header {
            background: #ff4d4f;
            color: #fff;
            padding: 20px;
        }
        .main-header h1 {
            margin: 0;
            font-size: 22px;
        }
        .main-body{
            padding: 20px;
        }
        .main-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .footer {
            padding: 15px;
            font-size: 12px;
            color: #999;
            background: #fafafa;
            border-top: 1px solid #eee;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="main-container">
    $content
   
</div>

</div>

</body>
</html>
HTML;
        return $html;
    }

    public static function getViteAssetsData(): array
    {
        $isDev = Config::$DEBUG;
        $data = ['scripts' => [], 'css' => []];

        if ($isDev) {
            $data['scripts'][] = ['src' => 'http://localhost:5173/@vite/client', 'type' => 'module'];
            $data['scripts'][] = [
                'content' => '
                    import RefreshRuntime from "http://localhost:5173/@react-refresh";
                    RefreshRuntime.injectIntoGlobalHook(window);
                    window.$RefreshReg$ = () => {};
                    window.$RefreshSig$ = () => (type) => type;
                    window.__vite_plugin_react_preamble_installed__ = true;
                ',
                'type' => 'module'
            ];
            $data['scripts'][] = ['src' => 'http://localhost:5173/js/main.jsx', 'type' => 'module'];
            return $data;
        }

        $manifestFile = Config::$DIR_PROJECT . '/lila/build_manifest.php';
        if (!file_exists($manifestFile)) return $data;

        $manifest = require $manifestFile;
        $file = $manifest['js/main.jsx']['file'] ?? "js/main.jsx";
        $css = $manifest['js/main.jsx']['css'] ?? [];

        $data['scripts'][] = ['src' => rtrim(Config::$URL_PROJECT, '/') . '/assets/build/' . $file, 'type' => 'module'];
        foreach ($css as $cssFile) {
            $data['css'][] = rtrim(Config::$URL_PROJECT, '/') . '/assets/build/' . $cssFile;
        }
        return $data;
    }
}
