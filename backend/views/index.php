<?php

/**
 * LilaPHP Default Home View
 */
$title = 'LilaPHP | High-Performance Native SSR Engine';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="Ultra-fast PHP 8.4 SSR engine.">
    <link href="/css/styles.css" rel="stylesheet">
</head>

<body class="bg-dark text-light">
    <div class="container py-5 text-center mt-5">
        <h1 class="display-4 fw-bold text-primary mb-3">LilaPHP</h1>
        <p class="lead text-secondary mb-5">Native PHP Server-Side Rendering powered by OPcache and APCu.</p>

        <div class="d-flex justify-content-center gap-3">
            <a href="/api/health" class="btn btn-outline-success btn-lg">Check API Health</a>
            <a href="/debug" class="btn btn-outline-info btn-lg">View Diagnostics</a>
        </div>
    </div>
</body>

</html>