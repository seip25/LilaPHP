<?php

include_once "./app/index.php";
use Core\App;
use Core\Config;
use Core\Database;
use Core\GET;
use Core\Response;
use Core\Session;


$app = new App([
    "security" => [
        "logger" => false,
        "cors" => true,
        "rateLimit" => 200,
    ],
    "translate" => true
]);

#[GET]
function get($req, Response $res, Session $session, Config $config)
{


    //Example connect database
    //Execute command in terminal: php app/cli.php migrate:create 
    //Uncomment to test database connection and add Database $db in function parameters , Config $config,Database $db...){

    //$pdo = $db->getConnection();
    // $insert = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    // $random = rand(1, 100);
    // $email = "Jhon{$random}@email.com";
    // $insert->execute(['John Doe', $email, 'password']);
    // $query = $db->query("SELECT * FROM users");
    // $users = $query->fetchAll(PDO::FETCH_ASSOC); 
    $debug = $config::$DEBUG;
    $lang = $session::get("lang") ?? "eng";
    $page = $req['page'] ?? 'index';

    if ($page === 'react') {
        //app/templates/react_integration.twig
        return $res->render(
            template: "react_integration",
            context: [
                "app" => [
                    "debug" => $debug,
                    "lang" => $lang
                ]
            ]
        );
    }
    if ($page === 'react-page') {
        //app/resources/ReactExample.jsx
        return $res->renderReact(
            page: "ReactExample",
            props: [
                "app" => [
                    "debug" => $debug,
                    "lang" => $lang
                ],
                "csrf" => $res->generateCSRF(),
                "translations" => $res->translations()
            ],
            options: [
                "lang" => "es",
                "title" => "React full Page + LilaPHP",
                "meta" => [
                    ["name" => "description", "content" => "React full page render example meta description"]
                ],

                "scripts" => [
                    "https://cdn.tailwindcss.com"
                ]
            ]
        );
    }
    //app/tempaltes/index.twig
    return $res->render("index");
}


$app->add(callback: 'get');

$app->run();
