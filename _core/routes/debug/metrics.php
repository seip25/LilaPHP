<?php

use Core\Request;
use Core\Response;
use Core\Debug;

Request::GET(function () {
    $limit = (int) Request::input('limit', 100);
    Response::json(Debug::getMetrics($limit));
});
