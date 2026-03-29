<?php

/** @var \Core\App $app */
include_once "../app/index.php";

use Core\GET;
use Core\Cache;
use Core\App;


$app = new App();

#[GET]
#[Cache]
function CacheResponse()
{
    global $app;

    sleep(6);

    return $app->jsonResponse([
        "message" => "Hi!",
        "time" => date("Y-m-d H:i:s")
    ]);
}
$app->add('CacheResponse');

$app->run();
