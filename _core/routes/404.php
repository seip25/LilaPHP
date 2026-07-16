<?php

/**
 * Standard API 404 Not Found fallback response.
 * 
 * Executed when an endpoint file is not found in `backend/routes/`.
 */

use Core\Response;

Response::error('Endpoint not found', 404);
