<?php

namespace App;

require_once __DIR__ . "/../vendor/autoload.php";

use Core\App;

$app = new App();

/*
Example 
$app = new App([
    'security' => [
        'cors' => false,
        'sanitize' => true,
        'logger' => true,
        'rateLimit' => 200
    ],
    'translate' => false
]);
*/