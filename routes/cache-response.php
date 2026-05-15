<?php

/** @var \Core\App $app */
include_once __DIR__ . "/../index.php";

use Core\GET;
use Core\Cache;
use Core\App;
use Core\Response;


$app = new App();

#[GET]
#[Cache]
function CacheResponse(array $req, Response $res)
{

    sleep(6);

    return $res->jsonResponse([
        "message" => "Hi!",
        "time" => date("Y-m-d H:i:s")
    ]);
}
$app->add('CacheResponse');

$app->run();
