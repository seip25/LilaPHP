<?php

/**
 * Route level 404 handler for Nginx try_files redirection.
 */

use Core\Response;

Response::error('Endpoint not found', 404);
