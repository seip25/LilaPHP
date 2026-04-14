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
        self::$twig->addFunction(new TwigFunction('url', function (string $path = ''): string {
            return rtrim(Config::$URL_PROJECT, '/') . '/' . ltrim($path, '/');
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
            $isDev = Config::$DEBUG;
            if ($isDev) {
                return '
                <script type="module">
                    import RefreshRuntime from "http://localhost:5173/@react-refresh";
                    RefreshRuntime.injectIntoGlobalHook(window);
                    window.$RefreshReg$ = () => {};
                    window.$RefreshSig$ = () => (type) => type;
                    window.__vite_plugin_react_preamble_installed__ = true;
                </script>
                <script type="module" src="http://localhost:5173/@vite/client"></script>
                <script type="module" src="http://localhost:5173/main.jsx"></script>';
            }
            $html = '<!-- Vite Manifest not found --><script>console.log("Vite Manifest not found ")</script>';

            $manifest = require Config::$DIR_PROJECT . '/lila/build_manifest.php';

            $file = $manifest['main.jsx']['file'] ?? "main.jsx";
            $css = $manifest['main.jsx']['css'] ?? [];

            $html = '<script type="module" src="' . rtrim(Config::$URL_PROJECT, '/') . '/assets/build/' . $file . '"></script>';
            foreach ($css as $cssFile) {
                $html .= '<link rel="stylesheet" href="' . rtrim(Config::$URL_PROJECT, '/') . '/assets/build/' . $cssFile . '">';
            }
            return $html;
        }, ['is_safe' => ['html']]));
    }

    public static function react(string $page, array $props = [], ?string $lang = null, ?string $title = null, array $meta = [], array $scripts = [], array $styles = []): void
    {

        $stylesHtml = "";
        $scriptsHtml = "";
        $metaHtml = "";
        $descriptionMeta = Config::$DESCRIPTIONMETA;
        $authorMeta = Config::$AUTHORMETA;
        $keywordsMeta = Config::$KEYWORDSMETA;
        $lang = is_null($lang) ? Config::$LANGHTML : $lang;
        $icon = rtrim(Config::$URL_PROJECT, '/') . "/favicon.ico";
        $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        foreach ($styles as $style) {
            $stylesHtml .= '<link rel="stylesheet" href="' . rtrim($style) . '" />';
        }
        foreach ($scripts as $script) {
            $scriptsHtml .= '<script src="' . rtrim($script) . '"></script>';
        }
        foreach ($meta as $meta_value) {
            if ($meta_value["name"] == "description")
                $descriptionMeta = $meta_value["content"];
            elseif ($meta_value["name"] == "author")
                $authorMeta = $meta_value["content"];
            elseif ($meta_value["name"] == "keywords")
                $keywordsMeta = $meta_value["content"];
            else
                $metaHtml .= '<meta name="' . $meta_value['name'] . '" content="' . $meta_value['content'] . '" />';
        }
        $titleHtml = $title ?? Config::$TITLE_PROJECT;

        $context = [
            "langHtml" => $lang,
            "titleHtml" => $titleHtml,
            "meta" => $metaHtml,
            "css" => $stylesHtml,
            "icon" => $icon,
            "scripts" => $scriptsHtml,
            "component" => $page,
            "props" => $propsJson,
            "descriptionMeta" => $descriptionMeta,
            "authorMeta" => $authorMeta,
            "keywordsMeta" => $keywordsMeta
        ];
        self::render(template: "lila/react_base", context: $context);
    }

    private static function getBaseContext(array $extra = []): array
    {
        return array_merge([
            "title" => Config::$TITLE_PROJECT,
            "descriptionMeta" => Config::$DESCRIPTIONMETA,
            "keywordsMeta" => Config::$KEYWORDSMETA,
            "authorMeta" => Config::$AUTHORMETA,
            "csrf_token" => Security::generateCsrfToken()
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
<html lang="en">
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
}
