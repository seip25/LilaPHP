<?php

use Core\Request;
use Core\Response;
use Core\Debug;

Request::GET(function () {
    Response::json(Debug::getHealth());
});
