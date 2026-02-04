<?php

/** @var \Core\App $app */

include_once "./app/index.php";


$app->get(callback: function ($req, $res) use ($app): mixed {
    //Example connect database
    //Execute command in terminal: php app/cli.php migrate:create 
    //Uncomment to test database connection
    // $db = $app->getDatabaseConnection();
    // $insert = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    // $random = rand(1, 100);
    // $email = "Jhon{$random}@email.com";
    // $insert->execute(['John Doe', $email, 'password']);
    // $query = $db->query("SELECT * FROM users");
    // $users = $query->fetchAll(PDO::FETCH_ASSOC); 

    $page = $req['page'] ?? 'index';

    if ($page === 'react') {
        //app/templates/react_integration.twig
        return $app->render(
            template: "react_integration",
            context: [
                "app" => [
                    "debug" => $app->getEnv("DEBUG")
                ]
            ]
        );
    }
    if ($page === 'react-page') {
        //app/resources/ReactExample.jsx
        return $app->renderReact(
            page: "ReactExample",
            props: [
                "app" => [
                    "debug" => $app->getEnv("DEBUG")
                ],
                "csrf" => $app->generateCSRF(),
                "translations" => $app->translations()
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
    return $app->render("index");
});


$app->run();
