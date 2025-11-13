<?php
/** @var \Core\App $app */
include_once "./app/index.php";


$app->get(callback: function($req, $res) use ($app): mixed {
    return $app->render("index");
});

$app->post(callback: function($req, $res) use ($app): mixed {
    return $app->jsonResponse(["success" => true]);
});

$app->run();