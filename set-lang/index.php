<?php
 /** @var \Core\App $app */
include_once "../app/index.php";

$app->get(callback: function ($req, $res) use ($app) {
    $newLang = $_GET["lang"] ?? $app->getLangDefault();
    $app->setSession(key: "lang", value: $newLang);
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $app->redirect($back);
});

$app->run();