<?php

declare(strict_types=1);

/**
 * OPcache Preload Script for LilaPHP Production Mode (`Performance First`).
 * 
 * Pre-compiles core framework classes and models directly into OPcache shared memory
 * across all PHP-FPM workers for zero-disk-lookup execution.
 */

$coreDir = dirname(__DIR__) . '/_core';
$modelsDir = __DIR__ . '/models';

$coreFiles = [
    "{$coreDir}/Config.php",
    "{$coreDir}/helpers.php",
    "{$coreDir}/Database.php",
    "{$coreDir}/Request.php",
    "{$coreDir}/Response.php",
    "{$coreDir}/View.php",
    "{$coreDir}/Cache.php",
    "{$coreDir}/Validate.php",
    "{$coreDir}/Security.php",
    "{$coreDir}/Logger.php",
    "{$coreDir}/Dispatcher.php",
    "{$coreDir}/Upload.php",
    "{$coreDir}/Task.php",
    "{$coreDir}/Http.php",
    "{$coreDir}/AI.php",
    "{$coreDir}/Event.php",
    "{$coreDir}/Jwt.php",
    "{$coreDir}/bootstrap.php",
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}

if (is_dir($modelsDir)) {
    $models = glob("{$modelsDir}/*.php");
    if (is_array($models)) {
        foreach ($models as $modelFile) {
            if (file_exists($modelFile)) {
                opcache_compile_file($modelFile);
            }
        }
    }
}

$locales = glob("{$coreDir}/locales/*.php");
if (is_array($locales)) {
    foreach ($locales as $localeFile) {
        if (file_exists($localeFile)) {
            opcache_compile_file($localeFile);
        }
    }
}
