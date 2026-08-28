<?php

declare(strict_types=1);

/**
 * LilaPHP Dedicated API Entry Point (`backend/index.php`).
 * 
 * Invoked by Nginx FastCGI for all `/api/*` routes.
 */

require_once dirname(__DIR__) . '/_core/bootstrap.php';

use Core\Dispatcher;

Dispatcher::dispatch();
