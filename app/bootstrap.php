<?php

/**
 * LilaPHP Bootstrap
 * 
 * This file initializes the framework, loads the autoloader, 
 * and prepares the environment.
 * 
 * Every page script should require this file:
 *   require_once __DIR__ . '/app/bootstrap.php';
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Core\Config::load();
