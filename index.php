<?php

/** @var \Core\App $app */

use Core\Security;
use Core\Translate;

include_once "./app/index.php";


$app->get(callback: function ($req, $res) use ($app): mixed {
    $page = $req['page'] ?? 'index';

    if ($page === 'react') {
        //app/templates/react_integration.twig
        return $app->render(template:"react_integration", 
        context:
        [
            "app" => [
                "debug" => $app->getEnv("DEBUG")
            ]
        ]);
    }
    if ($page === 'react-page') {
        //app/resources/ReactExample.jsx
        return $app->renderReact(
            page: "ReactExample",
            props: [
                "app" => [
                    "debug" => $app->getEnv("DEBUG")
                ],
                "csrf"=>Security::generateCsrfToken() ,
                "translations"=>Translate::translations()
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
