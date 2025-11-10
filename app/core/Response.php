<?php

namespace Core;

class Response
{
    public static function HTML(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
            header('Content-Encoding: gzip');
            echo gzencode($html, 9);
        } else {
            echo $html;
        }
    }

    public static function JSON(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

    public static function Redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
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
}
