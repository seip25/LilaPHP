<?php

/**
 * LilaPHP Bootstrap
 * 
 * This file initializes the framework, loads the autoloader, 
 * and prepares the environment.
 */

namespace App;
require_once __DIR__ . "/vendor/autoload.php";
\Core\Config::load();

