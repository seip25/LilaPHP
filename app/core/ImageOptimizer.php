<?php

namespace Core;

class ImageOptimizer
{
    public static function getOptimized(
        string $path,
        string $type = 'webp',
        int $width = 800,
        int $height = 0,
        int $quality = 70
    ): ?string {
        $publicDir = realpath(Config::$DIR_PROJECT . '/../public/') . '/';
        $cacheDir = $publicDir . 'cache/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $fullPath = $publicDir . ltrim($path, '/');

        if (!file_exists($fullPath)) {
            error_log("[ImageOptimizer] File not found: $fullPath");
            return null;
        }

        $info = pathinfo($fullPath);
        $extension = strtolower($info['extension']);
        $baseName = $info['filename'];

        $optimizedName = match ($type) {
            'ico' => "{$baseName}_{$width}x{$height}.ico",
            default => "{$baseName}_{$width}x{$height}.webp"
        };

        $optimizedPath = $cacheDir . $optimizedName;

        if (file_exists($optimizedPath)) {
            return rtrim(Config::$URL_PROJECT, '/') . '/public/cache/' . $optimizedName;
        }

        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($fullPath),
            'png' => @imagecreatefrompng($fullPath),
            'gif' => @imagecreatefromgif($fullPath),
            default => null
        };

        if (!$image) {
            error_log("[ImageOptimizer] Failed to create image from: $fullPath");
            return null;
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($height === 0) {
            $height = intval(($width / $origWidth) * $origHeight);
        }

        $resized = imagecreatetruecolor($width, $height);

        if (in_array($extension, ['png', 'gif'])) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);

        $ok = match ($type) {
            'ico' => self::saveAsIco($resized, $optimizedPath),
            default => imagewebp($resized, $optimizedPath, $quality)
        };

        imagedestroy($image);
        imagedestroy($resized);

        if (!$ok) {
            error_log("[ImageOptimizer] Failed to save optimized file: $optimizedPath");
            return null;
        }

        return rtrim(Config::$URL_PROJECT, '/') . '/public/cache/' . $optimizedName;
    }

    protected static function saveAsIco($image, string $path): bool
    {
        $tmpPng = str_replace('.ico', '.png', $path);
        if (!imagepng($image, $tmpPng)) {
            return false;
        }
        return rename($tmpPng, $path);
    }
}
