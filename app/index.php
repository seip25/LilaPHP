<?php

declare(strict_types=1);

/**
 * LilaPHP Dedicated FastCGI Entry Point (`app/index.php`).
 */

require_once dirname(__DIR__) . '/_core/bootstrap.php';

use Core\Dispatcher;

Dispatcher::dispatch();
