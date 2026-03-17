<?php

/** @var \Core\App $app */
include_once "../app/index.php";

use Core\GET;
use Core\Cache;

#[GET]
#[Cache]
function CacheResponse(){
    global $app;

    sleep(6); 

    return $app->jsonResponse(["Hi!"]);
}
$app->add('CacheResponse');
 
$app->run();
