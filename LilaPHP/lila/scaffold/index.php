<?php

/**
 * LilaPHP - Front Controller
 * 
 * This file is the entry point for all requests. 
 * It delegates routing and language management to the LilaPHP Dispatcher.
 */

require_once __DIR__ . "/LilaPHP/index.php";

\Core\Dispatcher::run();
