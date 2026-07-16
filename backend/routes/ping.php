<?php

/**
 * API Endpoint `/api/ping`
 */

use Core\Response;
use Core\Validate;

// Sanitize & Validate Payload
// $clean = Validate::assert($_REQUEST, ['example_field' => 'required']);

Response::json([
    'status' => 'success',
    'endpoint' => '/api/ping',
    'timestamp' => time()
]);