<?php

/**
 * LilaPHP - Front Controller & Dispatcher
 * 
 * This file handles all incoming requests, manages language prefixes,
 * and routes the request to the appropriate file in LilaPHP/routes/
 */


require_once __DIR__ . "/LilaPHP/index.php";

use Core\Session;
use Core\Translate;

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('index.php', '', $scriptName);

$path = substr($uri, strlen($basePath));
$path = explode('?', $path)[0];
$path = trim($path, '/');

$languages = ['en', 'es', 'pt', 'pt-br'];
$lang = null;

$parts = explode('/', $path);
if (!empty($parts[0]) && in_array($parts[0], $languages)) {
    $lang = array_shift($parts);
    $path = implode('/', $parts);
    Session::set('lang', $lang);
}


$targetFile = null;
$routesDir = __DIR__ . "/LilaPHP/routes";

if (empty($path)) {
    $targetFile = $routesDir . "/index.php";
} else {
    if (file_exists($routesDir . "/" . $path . ".php")) {
        $targetFile = $routesDir . "/" . $path . ".php";
    } elseif (is_dir($routesDir . "/" . $path) && file_exists($routesDir . "/" . $path . "/index.php")) {
        $targetFile = $routesDir . "/" . $path . "/index.php";
    }
}

if ($targetFile && file_exists($targetFile)) {
    require_once $targetFile;
} else {
    http_response_code(404);

    if (file_exists($routesDir . "/404.php")) {
        require_once $routesDir . "/404.php";
    } else {
        echo "<h1>404 Not Found</h1>";
        echo "<p>The requested route <strong>/{$path}</strong> was not found on this server.</p>";
    }
}
