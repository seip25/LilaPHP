<?php

declare(strict_types=1);

use Core\View;

$uri = View::escape($uri ?? ($_SERVER['REQUEST_URI'] ?? '/'));
?>
<div style="text-align: center; padding: 4rem 1rem;">
    <h1 style="font-size: 4rem; font-weight: 800; color: #38bdf8; margin-bottom: 1rem;">404</h1>
    <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #f8fafc;">Page Not Found</h2>
    <p style="color: #94a3b8; max-width: 500px; margin: 0 auto 2rem auto;">
        The requested path <code><?= $uri ?></code> does not exist on this server.
    </p>
    <a href="/" style="display: inline-block; padding: 0.75rem 1.5rem; background: #0284c7; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Return to Home</a>
</div>
