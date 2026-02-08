<?php

/** @var \Core\App $app */
include_once "../app/index.php";

use Core\GET;

#[GET]
function setLang($req, $res)
{
    global $app;
    $newLang = $_GET["lang"] ?? $app->getLangDefault();
    $app->setSession(key: "lang", value: $newLang);
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $app->redirect($back);
}
$app->add(callback: 'setLang');

$app->run();
