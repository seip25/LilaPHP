<?php

declare(strict_types=1);

use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('about', [
        'title'       => 'About LilaPHP Framework',
        'description' => 'Architecture, performance principles, and design philosophy of LilaPHP.',
        'keywords'    => 'lilaphp about, php framework, architecture, kiss, performance',
    ]);
});
