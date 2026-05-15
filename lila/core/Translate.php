<?php

namespace Core;

class Translate
{
    protected static array $translations = [];
    protected static ?array $seoTranslations = null;
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

    /**
     * Retrieves SEO configuration based on a key and the current language.
     * 
     * @param string $key The SEO configuration key
     * @return array|null The SEO data for the current language, or null if not found
     */
    public static function getSeo(string $key): ?array
    {
        if (self::$seoTranslations === null) {
            $file = Config::$DIR_PROJECT . '/locales/seo.php';
            self::$seoTranslations = file_exists($file) ? (require $file) : [];
        }

        $lang = self::getLang();
        
        return self::$seoTranslations[$key][$lang] ?? self::$seoTranslations[$key]['en'] ?? null;
    }
}
