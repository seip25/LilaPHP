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

$lang = $app->getSession(key: "lang", default: "es");

$app->get(callback: function ($req, $res) use ($app) {
    return $app->render("login");
});

$app->post(callback: function ($req, $res) use ($app) {
    global $lang;
    return $app->jsonResponse(["success" => true, "login" => false,"session"=>$lang]);
 }, 
middlewares: [
    fn($req, $res) => new LoginModel(data: $req, lang: $lang),
]
);

$app->addMiddlewares(middlewares: [
    'before' => [
        fn($req, $res) => error_log(message: "Custom before route")
    ],
    'after' => [
        fn($req, $res) => error_log(message: "Custom after route")
    ]
]);

$app->run();
