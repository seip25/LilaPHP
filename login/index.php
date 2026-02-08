<?php

/** @var \Core\App $app */
include_once "../app/index.php";

use Core\BaseModel;
use Core\Field;
use Core\CSRF;
use Core\GET;
use Core\POST;


class LoginModel extends BaseModel
{
    #[Field(required: true, format: "email")]
    public string $email;

    #[Field(required: true, min_length: 6)]
    public string $password;
}

$lang = $app->getSession(key: "lang", default: "es");

#[GET]
function login($req, $res)
{
    global $app;
    return $app->render("login");
}
$app->add(callback: 'login');

#[POST]
#[CSRF]
function loginPost($req, $res)
{
    global $app;
    return $app->jsonResponse(["success" => true]);
}
$app->add(callback: 'loginPost', middlewares: [fn($req, $res) => new LoginModel(data: $req, lang: $lang)]);

$app->addMiddlewares(middlewares: [
    'before' => [
        fn($req, $res) => error_log(message: "Custom before route")
    ],
    'after' => [
        fn($req, $res) => error_log(message: "Custom after route")
    ]
]);

$app->run();
