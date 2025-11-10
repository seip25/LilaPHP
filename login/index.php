<?php
include_once "../app/index.php";

$app->get(function ($req, $res) use ($app) {
    return $app->render("login");
});

$app->post(function ($req, $res) use ($app) {
    return $app->jsonResponse(["success" => true, "login" => false]);
});

$app->middleware([
    'before' => [
        fn($req, $res) => error_log("Custom before route")
    ],
    'after' => [
        fn($req, $res) => error_log("Custom after route")
    ]
]);

$app->run();
