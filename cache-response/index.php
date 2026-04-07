<?php

/** @var \Core\App $app */
include_once "../app/index.php";

use Core\GET;
use Core\Cache;
use Core\App;


$app = new App();

#[GET]
#[Cache]
function CacheResponse($req, $res)
{

    sleep(6);

    return $res->jsonResponse([
        "message" => "Hi!",
        "time" => date("Y-m-d H:i:s")
    ]);
}
$app->add('CacheResponse');

$app->run();
