<?php

include_once "./app/index.php";
use Core\App;
use Core\Config;
use Core\Database;
use Core\GET;
use Core\Response;
use Core\Session;
use Core\Translate;
use Core\SEO;


$app = new App();

#[GET]
#[SEO(key: "index")]// key in app/locales/seo.php
function get($req, Response $res, Session $session, Config $config, Translate $translate)
{

    //Example connect database
    //Execute command in terminal: php app/cli.php migrate:create 
    //Uncomment to test database connection and add Database $db in function parameters , Config $config,Database $db...){

    //$pdo = $db->getConnection();
    // $insert = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    // $random = rand(1, 100);
    // $email = "Jhon{$random}@email.com";
    // $insert->execute(['John Doe', $email, 'password']);
    // $query = $pdo->query("SELECT * FROM users");
    // $users = $query->fetchAll(PDO::FETCH_ASSOC); 
    $debug = $config::$DEBUG;
    $lang = $session::get(key: "lang") ?? $translate::getLang();
    $page = $req['page'] ?? 'index';

    if ($page === 'react') {
        //app/resources/templates/react_integration.twig
        return $res->render(
            template: "react_integration",
            context: [
                "app" => [
                    "debug" => $debug,
                ]
            ]
        );
    }
    if ($page === 'react-page') {
        //app/resources/js/pages/ReactExample.jsx
        return $res->renderReact(
            page: "ReactExample",
            props: [
                "app" => [
                    "debug" => $debug,
                ],
                "csrf" => $res->generateCSRF(),
                "translations" => $res->translations()
            ],
            options: [
                "scripts" => [
                    "https://cdn.tailwindcss.com"
                ]
            ]
        );
    }
    //app/resources/templates/index.twig
    return $res->render("index");
}


$app->add(callback: 'get');

$app->run();
