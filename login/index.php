<?php
include_once "../app/index.php";

use Core\BaseModel;
use Core\Field;

class LoginModel extends BaseModel
{
    #[Field(required: true, format: "email")]
    public string $email;

    #[Field(required: true, min_length: 6)]
    public string $password;
}

$app->get(function ($req, $res) use ($app) {
    return $app->render("login");
});

$app->post(function ($req, $res) use ($app) {
    return $app->jsonResponse(["success" => true, "login" => false]);
}, [
    fn($req, $res) => new LoginModel($req, "es"),
]);

$app->addMiddlewares([
    'before' => [
        fn($req, $res) => error_log("Custom before route")
    ],
    'after' => [
        fn($req, $res) => error_log("Custom after route")
    ]


]);

$app->run();
