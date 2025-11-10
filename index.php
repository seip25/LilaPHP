<?php
include_once "./app/index.php";


$app->get(function($req, $res) use ($app) {
    return $app->render("index");
});

$app->post(function($req, $res) use ($app) {
    return $app->jsonResponse(["success" => true]);
});

$app->run();