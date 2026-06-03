<?php

require_once __DIR__ . '/../app/bootstrap.php';
use Core\App;
use Core\GET;
use Core\Response;


$app = new App();

#[GET]
function api($req, Response $res)
{
    return $res->json([
        "message" => "Welcome to the API Test in LilaPHP"
    ]);
}

$app->add(callback: 'api');
$app->run();