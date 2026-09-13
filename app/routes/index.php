<?php

declare(strict_types=1);

use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('index', [
        'title'       => 'LilaPHP - Ultra-fast PHP 8.4 Full-Stack Framework',
        'description' => 'High-performance PHP 8.4 framework with native view engine, tiered cache, and Bluebird CSS.',
        'keywords'    => 'lilaphp, php 8.4, view engine, rest api, bluebird css, performance',
    ]);
});
