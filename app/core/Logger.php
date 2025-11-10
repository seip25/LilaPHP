<?php

namespace Core;

class Logger
{
    private static string $logDir = '';

    private static function ensureDir(): string
    {
        if (!self::$logDir) {
            $baseDir =  Config::$PATH_LOGS;
            $year    = date('Y');
            $month   = date('m');
            $day     = date('d');
            $fullPath = "$baseDir/$year/$month/$day";

            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
            }

            self::$logDir = $fullPath;
        }

        return self::$logDir;
    }

    private static function write(string $type, string $message)
    {
        $logDir = self::ensureDir();
        $file = $logDir . "/$type.log";
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($file, "[$timestamp] [$type] $message\n", FILE_APPEND);
    }

    public static function info(string $message, string $name = "info")
    {
        self::write($name, $message);
    }
    public static function warning(string $message, string $name = "warning")
    {
        self::write($name, $message);
    }
    public static function error(string $message, string $name = "error")
    {
        self::write($name, $message);
    }
}
