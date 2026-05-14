<?php

/**
 * LilaPHP Bootstrap
 * 
 * This file initializes the framework, loads the autoloader, 
 * and prepares the environment.
 */

namespace App;
require_once __DIR__ . "/vendor/autoload.php";

/*
Example App Initialization
$app = new \Core\App([
    'security' => [
        'cors' => false,
        'sanitize' => true,
        'logger' => true,
        'rateLimit' => 200
    ],
    'translate' => true
]);
*/
