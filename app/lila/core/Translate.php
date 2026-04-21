<?php

namespace Core;

class Translate
{
    protected static array $translations = [];
    protected static string $lang = 'en';

    protected static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded)
            return;
        self::$lang = Session::has(key: 'lang') ? Session::get('lang') : Config::$LANG;
        $file = Config::$DIR_PROJECT . '/locales/' . self::$lang . '.php';

        if (file_exists(filename: $file)) {
            self::$translations = require $file;
        } else {
            $default = require Config::$DIR_PROJECT . '/locales/en.php';
            self::$translations = $default;
        }
        self::$loaded = true;
    }

    public static function t(string $key): string
    {
        if (!self::$loaded)
            self::load();
        return self::$translations[$key] ?? $key;
    }
    public static function translations(): array
    {
        if (!self::$loaded)
            self::load();
        return self::$translations ?? [];
    }
    public static function getAll(): array
    {
        if (!self::$loaded)
            self::load();
        return self::$translations ?? [];
    }
    public static function getLang(): string
    {
        if (!self::$loaded)
            self::load();
        return self::$lang ?? "en";
    }
    public static function get(string $key, $default = null): string
    {
        if (!self::$loaded)
            self::load();
        return self::$translations[$key] ?? ($default ?? $key);
    }
}
