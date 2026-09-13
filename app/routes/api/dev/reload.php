<?php

declare(strict_types=1);

use Core\Request;
use Core\Response;
use Core\Config;

Request::GET(function () {
    if (!Config::$DEBUG) {
        Response::error('Dev reload endpoint disabled in production', 403);
    }

    $dirs = [
        Config::$DIR_APP . '/views',
        Config::$DIR_APP . '/routes',
        Config::$DIR_PUBLIC . '/css',
        Config::$DIR_PUBLIC . '/js',
    ];

    $latest = 0;

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $mtime = $item->getMTime();
                if ($mtime > $latest) {
                    $latest = $mtime;
                }
            }
        }
    }

    Response::json([
        'status'    => 'ok',
        'timestamp' => $latest,
    ]);
});
