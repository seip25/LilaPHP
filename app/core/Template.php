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

        // self::$twig->addFunction(new TwigFunction('csrf_token', function (): string {
        //     return Security::generateCsrfToken();
        // }));
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
            $fullContext = self::getBaseContext($context);
            $html = $twig->render("$template.twig", $fullContext);
            $html = self::minifyHtml($html);

            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                header('Content-Encoding: gzip');
                echo gzencode($html, 9);
            } else {
                echo $html;
            }
        } catch (\Throwable $e) {
            Logger::error("Template render error: " . $e->getMessage());
            if (Config::$DEBUG) {
                Response::HTML("<pre>{$e->getMessage()}</pre>", 500);
            } else {
                Response::HTML("<h1>Error rendering template</h1>", 500);
            }
        }
    }
}
