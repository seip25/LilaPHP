<?php

namespace Core;

class Translate
{
    protected static array $translations = [];
    protected static string $lang = 'eng';

    public static function load(): void
    {
        self::$lang = Session::has(key: 'lang') ? Session::get('lang') : 'eng';
        $file = Config::$DIR_PROJECT . '/locales/' . self::$lang . '.php';
       
        if (file_exists(filename: $file)) {
            self::$translations = require $file;
        } else {
            error_log(message: "[Translate] Missing locale file: $file");
            self::$translations = [];
        }
    }

    public static function t(string $key): string
    {
        return self::$translations[$key] ?? $key;
    }
}
