<?php

declare(strict_types=1);

namespace Core;

/**
 * Secure High-Performance File Upload & Inspection Helper (`Core\Upload`).
 * 
 * Protects against MIME spoofing, PHP script injection (`<?php`), double extensions,
 * and oversized files while assigning cryptographically random filenames.
 * 
 * @package Core
 */
class Upload
{
    private static array $defaultAllowedMimes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf'
    ];

    /**
     * Safely processes and saves an uploaded file (`$_FILES['key']`).
     * 
     * @param array $file $_FILES['input_name'] associative array
     * @param string|null $destinationDir Absolute storage directory (defaults to `backend/uploads/`)
     * @param array|null $allowedMimes Map of allowed MIME types => extension
     * @param int $maxSizeMax Max allowed bytes (default: 10MB)
     * @return string|false Saved filename or false on error/security failure
     */
    public static function save(
        array $file,
        ?string $destinationDir = null,
        ?array $allowedMimes = null,
        int $maxSizeMax = 10485760
    ): string|false {
        if (!isset($file['tmp_name'], $file['error'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
            Logger::warning('Upload failure: invalid file structure or upload error code ' . ($file['error'] ?? 'unknown'));
            return false;
        }

        if ($file['size'] > $maxSizeMax || $file['size'] <= 0) {
            Logger::warning("Upload rejected: size ({$file['size']} bytes) exceeds maximum limit ({$maxSizeMax} bytes).");
            return false;
        }

        $tmpPath = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            Logger::error('Upload security warning: file is not a legitimate HTTP POST upload (`is_uploaded_file` check failed).');
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }
        $realMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowed = $allowedMimes ?? self::$defaultAllowedMimes;
        if (!isset($allowed[$realMime])) {
            Logger::warning("Upload rejected: unallowed real MIME type `{$realMime}`.");
            return false;
        }

        $safeExtension = $allowed[$realMime];

        $chunk = file_get_contents($tmpPath, false, null, 0, 8192);
        if ($chunk !== false && (stripos($chunk, '<?php') !== false || stripos($chunk, '<?=') !== false || stripos($chunk, '<script') !== false)) {
            Logger::error('Upload security blocked: PHP or Script payload detected inside file bytes!');
            @unlink($tmpPath);
            return false;
        }

        $destDir = rtrim($destinationDir ?? (Config::$DIR_BACKEND . '/uploads'), '/');
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $newFilename = bin2hex(random_bytes(16)) . '.' . $safeExtension;
        $finalPath = "{$destDir}/{$newFilename}";

        if (move_uploaded_file($tmpPath, $finalPath)) {
            return $newFilename;
        }

        return false;
    }

    /**
     * Deletes a previously uploaded file from the storage directory.
     * 
     * @param string $filename Filename to delete
     * @param string|null $directory Storage directory (defaults to `backend/uploads/`)
     * @return bool
     */
    public static function delete(string $filename, ?string $directory = null): bool
    {
        $cleanName = basename($filename);
        $dir = rtrim($directory ?? (Config::$DIR_BACKEND . '/uploads'), '/');
        $path = "{$dir}/{$cleanName}";

        if (file_exists($path) && is_file($path)) {
            return @unlink($path);
        }
        return false;
    }
}
