<?php

namespace Core;

/**
 * Native PHP View Engine.
 * 
 * Renders PHP templates located in backend/views.
 * Supports passing data variables and caching the final output via APCu/Redis.
 * 
 * @package Core
 */
class View
{
    /**
     * Renders a view file.
     * 
     * @param string $viewName The name of the view file (without .php) inside backend/views.
     * @param array $data Variables to extract into the view's scope.
     * @param array $options Configuration options (cache, cacheTtl).
     * @return void
     */
    public static function render(string $viewName, array $data = [], array $options = []): void
    {
        $appEnv = Config::$APP_ENV ?? (getenv('APP_ENV') ?: 'development');
        $isDebug = Config::$DEBUG ?? filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $isDev = in_array(strtolower($appEnv), ['dev', 'development', 'local'], true) && $isDebug;

        $useCache = !$isDev && ($options['cache'] ?? false);
        
        if ($useCache) {
            $cacheKey = 'lila_view_' . md5($viewName . serialize($data));
            $ttl = (int)($options['cacheTtl'] ?? 0);

            $html = Cache::api($cacheKey, function () use ($viewName, $data) {
                return self::evaluate($viewName, $data);
            }, $ttl);

            header('Content-Type: text/html; charset=utf-8');
            header('X-Lila-View-Cache: HIT');
            echo $html;
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Lila-View-Cache: MISS');
        echo self::evaluate($viewName, $data);
    }

    /**
     * Evaluates a view file and captures its output.
     * 
     * @param string $viewName View file name.
     * @param array $data Variables to extract.
     * @return string The rendered HTML.
     * @throws \Exception If the view file is not found.
     */
    private static function evaluate(string $viewName, array $data): string
    {
        $viewPath = Config::$DIR_BACKEND . '/views/' . $viewName . '.php';

        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$viewName}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return ob_get_clean();
    }
}
