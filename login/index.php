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

    //Run php app/cli.php migrate:create and descomment code
    //     $db = $app->getDatabaseConnection();
    //     $q = <<<SQL
    //     SELECT id,name,password FROM users 
    //     WHERE email = ? 
    //     AND is_active = 1
    // SQL;
    //     $email = $req["email"] ?? "";
    //     $email = trim($email);
    //     $params = [$email];
    //     $select = $db->prepare(query: $q);
    //     $select->execute($params);
    //     $user = $select->fetchObject();
    //     if ($user) {
    //         $passwordDB = $user->password;
    //         unset($user->password);
    //         $password = trim($req["password"]);
    //         if (password_verify($password, $passwordDB)) {
    //             session_regenerate_id(true);
    //             $app->setSession(key: "auth", value: $user, encrypt: true); //encrypt session and secure
    //             return $app->jsonResponse(data: ["success" => true]);
    //         }
    //     }
    //     return $app->jsonResponse(data: ["success" => false], code: 401);
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
