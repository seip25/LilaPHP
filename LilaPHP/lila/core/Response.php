<?php

namespace Core;

use Core\Template;
use Core\Session;
use Core\Security;
use Core\Translate;

class Response
{
    /**
     * Render a Twig template
     * 
     * @param string $template Template name (without .twig extension)
     * @param array $context Variables to pass to the template
     * @param string|null $path Custom template directory path
     * @return void
     */
    public function render(string $template, array $context = [], ?string $path = null): void
    {
        Template::render(template: $template, context: $context, path: $path);
    }

    /**
     * Set a session variable
     * 
     * @param string $key Session key
     * @param mixed $value Value to store
     * @param bool $encrypt Encrypt value before storing
     * @return void
     */
    public function setSession(string $key, mixed $value, bool $encrypt = false): void
    {
        Session::set($key, $value, $encrypt);
    }

    /**
     * Get a session variable
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @param bool $decrypt Decrypt value
     * @return mixed Session value or default
     */
    public function getSession(string $key, mixed $default = null, bool $decrypt = false): mixed
    {
        return Session::get($key, $default, $decrypt);
    }

    /**
     * Check if a session variable exists
     * 
     * @param string $key Session key
     * @return bool True if exists
     */
    public function hasSession(string $key): bool
    {
        return Session::has($key);
    }

    /**
     * Remove a session variable
     * 
     * @param string $key Session key to remove
     * @return void
     */
    public function removeSession(string $key): void
    {
        Session::remove($key);
    }

    /**
     * Render a React full page
     * 
     * @param string $page Page React component name
     * @param array $props Props to pass to the island
     * @param array $options Layout options (lang, title, meta, scripts, styles)
     * @return void
     */
    public function renderReact(string $page, array $props = [], array $options = ["lang" => null, "title" => null, "meta" => [], "scripts" => [], "styles" => []]): void
    {
        Template::react(
            page: $page,
            props: $props,
            lang: $options['lang'] ?? null,
            title: $options['title'] ?? null,
            meta: $options['meta'] ?? [],
            scripts: $options['scripts'] ?? [],
            styles: $options['styles'] ?? []
        );
    }

    /**
     * Render a pure PHP HTML page
     * 
     * @param string|array $html HTML content
     * @param array $options Options (renderFull, cache, title, meta, scripts, styles, lang)
     * @return void
     */
    public function renderHtml(string|array $html, array $options = []): void
    {
        Template::renderHtml($html, $options);
    }

    /**
     * Redirect to a URL
     * 
     * @param string $url Target URL
     * @param int $status HTTP status code
     * @return void
     */
    public function redirect(string $url, bool $into_to_project = true, int $status = 302): void
    {
        http_response_code($status);
        if ($into_to_project) {
            $url = rtrim(Config::$URL_PROJECT, '/') . '/' . ltrim($url, '/');
        }
        header("Location: {$url}");
        exit;
    }

    /**
     * Send a JSON response
     * 
     * @param array $data Data to encode
     * @param int $status HTTP status code
     * @return void
     */
    public function jsonResponse(array $data, int $status = 200): void
    {
        self::JSON($data, $status);
    }

    public static function HTML(string $html, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
            header('Content-Encoding: gzip');
            echo gzencode($html, 6);
        } else {
            echo $html;
        }
    }

    public static function JSON(array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "JSON encoding failed";
            return;
        }
        echo $json;
    }

    public static function File(string $filePath, ?string $downloadName = null, bool $inline = false, int $status = 200): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not found: {$filePath}";
            return;
        }

        http_response_code($status);

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        header("Content-Type: {$mimeType}");

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $downloadName ?? basename($filePath);
        header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($filePath));

        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }


    public static function Stream(callable $callback, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): void
    {
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        $callback();
        flush();
    }



    public static function NoContent(): void
    {
        http_response_code(204);
    }

    public static function Text(string $text, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
    }

    /**
     * Cache the response
     * 
     * @param int $seconds Cache duration in seconds
     * @param string|null $tag Cache tag for invalidation
     * @return callable Middleware closure
     */
    public static function cacheResponse(int $seconds = 60, ?string $tag = null): callable
    {
        return function (array $req, $res) use ($seconds, $tag) {

            $lang = Config::$TRANSLATE ? (Session::get('lang') ?? Config::$LANG) : '';
            $cacheKey = md5($_SERVER['REQUEST_URI'] . json_encode($req) . $lang);
            $cacheDir = Config::$PATH_CACHE . '/responses';
            
            $prefix = '';
            if ($tag) {
                $parsedTag = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($matches) use ($req) {
                    return $req[$matches[1]] ?? $matches[0];
                }, $tag);
                $prefix = "{$parsedTag}_";
            }
            $cacheFile = $cacheDir . '/' . $prefix . $cacheKey . '.cache';

            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $seconds)) {
                $cached = @unserialize(file_get_contents($cacheFile));
                if ($cached) {
                    http_response_code($cached['status'] ?? 200);
                    foreach ($cached['headers'] ?? [] as $header) {
                        if (stripos($header, 'Content-Encoding') === false) {
                            header($header);
                        }
                    }
                    echo $cached['body'] ?? '';
                    exit;
                }
            }

            ob_start();

            register_shutdown_function(function () use ($cacheFile, $seconds) {
                $status = http_response_code();

                if ($status >= 200 && $status < 300) {
                    $body = ob_get_contents();
                    $headers = headers_list();

                    if (!is_dir(dirname($cacheFile))) {
                        mkdir(dirname($cacheFile), 0755, true);
                    }

                    file_put_contents($cacheFile, serialize([
                        'body' => $body,
                        'headers' => $headers,
                        'status' => $status
                    ]));
                }
            });
        };
    }

    /**
     * Clear cached responses by tag
     * 
     * @param string $tag Cache tag to clear
     * @return int Number of files deleted
     */
    public static function clearCache(string $tag): int
    {
        $cacheDir = Config::$PATH_CACHE . '/responses';
        $pattern = $cacheDir . '/' . $tag . '_*.cache';
        $files = glob($pattern);
        $count = 0;
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Generate a new CSRF token
     * 
     * @return string
     */
    public function generateCSRF(): string
    {
        return Security::generateCsrfToken();
    }

    /**
     * Get all currently loaded translations
     * 
     * @return array
     */
    public function translations(): array
    {
        return Translate::translations();
    }
}
