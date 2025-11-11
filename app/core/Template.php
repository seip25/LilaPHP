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
            return rtrim(Config::$URL_PROJECT, '/') . '/public/' . ltrim($file, '/');
        }));

        self::$twig->addFunction(new TwigFunction('url', function (string $path = ''): string {
            return rtrim(Config::$URL_PROJECT, '/') . '/' . ltrim($path, '/');
        }));


        self::$twig->addFunction(new TwigFunction('csrf_input', function (): string {
            $token = Security::generateCsrfToken();
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
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
                self::render("500", $context, $path);
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
