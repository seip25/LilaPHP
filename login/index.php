<?php

include_once "../app/index.php";

use Core\BaseModel;
use Core\Database;
use Core\Field;
use Core\CSRF;
use Core\GET;
use Core\POST;
use Core\Session;
use Core\Validate;
use Core\App;
use Core\Response;


$app = new App();

class LoginModel extends BaseModel
{
    #[Field(required: true, format: "email")]
    public string $email;

    #[Field(required: true, min_length: 6)]
    public string $password;
}

$lang = $app->getSession(key: "lang", default: "es");

#[GET]
function login($req, Response $res)
{
    return $res->render("login");
}
$app->add(callback: 'login');

#[POST]
#[CSRF]
#[Validate(LoginModel::class)]
function loginPost($req, Response $res, Database $database, Session $session)
{
    //Run php app/cli.php migrate:create and descomment code
    $db = $database->getConnection();
    $q = <<<SQL
        SELECT id,name,password FROM users 
        WHERE email = ? 
        AND is_active = 1
    SQL;
    $email = $req["email"] ?? "";
    $email = trim($email);
    $params = [$email];
    $select = $db->prepare(query: $q);
    $select->execute($params);
    $user = $select->fetchObject();
    $password = trim($req["password"]);
    if ($user) {
        $passwordDB = $user->password;
        unset($user->password);
        if (password_verify($password, $passwordDB)) {
            session_regenerate_id(true);
            $session::set(key: "auth", value: $user, encrypt: true); //encrypt session and secure
            return $res->jsonResponse(data: ["success" => true]);
        }
    }
    // Always verify password even if user not found
    // to prevent user enumeration timing attacks
    $fakeHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $fakeVerify = password_verify($password, $fakeHash) && $user;
    return $res->jsonResponse(data: ["success" => false], status: 401);

    return $res->jsonResponse(["success" => true, "email" => $req["email"]]);
}
$app->add(callback: 'loginPost');

$app->addMiddlewares(middlewares: [
    'before' => [
        fn($req, $res) => error_log(message: "Custom before route")
    ],
    'after' => [
        fn($req, $res) => error_log(message: "Custom after route")
    ]
]);

$app->run();
